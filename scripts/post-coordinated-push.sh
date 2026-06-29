#!/usr/bin/env bash
# post-coordinated-push.sh — Auto-advance release cycles after a push.
#
# Usage:
#   bash scripts/post-coordinated-push.sh
#   bash scripts/post-coordinated-push.sh <team-id> [release-id]
#
# Run this immediately after a production push completes (Gate 4).
# With no args it preserves the legacy all-teams behavior.
# With <team-id>, it advances only that team's release lane.
#
# Idempotent — safe to re-run.

set -euo pipefail
ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
TEAM_SCOPE="${1:-}"
RELEASE_SCOPE="${2:-}"

TEAMS_JSON="${ROOT_DIR}/org-chart/products/product-teams.json"
RUNTIME_DIR="${ROOT_DIR}/tmp/release-cycle-active"

if [ -n "$TEAM_SCOPE" ]; then
    echo "=== post-coordinated-push: team-scoped mode — skipping pre-push validation ==="
else
    echo "=== post-coordinated-push: running pre-push validation ==="

    # ════════════════════════════════════════════════════════════════════════
    # Full multi-team mode still performs pre-push validation because it is
    # used at the shipping boundary. Team-scoped mode is post-push recovery /
    # reconciliation and must not be blocked by unrelated workspace dirt.
    # ════════════════════════════════════════════════════════════════════════
    if ! bash "${ROOT_DIR}/scripts/pre-push-validation.sh"; then
        echo "❌ PRE-PUSH VALIDATION FAILED"
        echo "Fix issues and retry:"
        echo "  1. Run 'git status' to see changes"
        echo "  2. Commit outstanding changes: git add + git commit"
        echo "  3. Fix malformed escalations (run scripts/lib/escalations.sh validate)"
        echo "  4. Re-run post-coordinated-push.sh"
        exit 1
    fi

    echo "✅ Pre-push validation passed"
fi
echo "=== post-coordinated-push: advancing team release cycles ==="

python3 - "$TEAMS_JSON" "$RUNTIME_DIR" "$ROOT_DIR" "$TEAM_SCOPE" "$RELEASE_SCOPE" <<'PY'
import sys, subprocess
from datetime import datetime, timezone
from pathlib import Path

teams_json, runtime_dir, root = Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3])
team_scope = (sys.argv[4] or "").strip()
release_scope = (sys.argv[5] or "").strip()
sys.path.insert(0, str(root / "scripts" / "lib"))
from release_cycle_helpers import (  # noqa: E402
    archive_stale_pm_release_items,
    combined_release_marker_key,
    coordinated_teams,
    next_release_id_after,
)

coord_teams = coordinated_teams(teams_json)
if team_scope:
    coord_teams = [team for team in coord_teams if team.get("id") == team_scope]
    if not coord_teams:
        print(f"ERROR: unknown or unmanaged team scope: {team_scope}")
        raise SystemExit(2)

team_release_ids = {}

# Step 1 — file any missing team-scoped signoffs
for team in sorted(coord_teams, key=lambda t: t['id']):
    team_id   = team['id']
    pm_agent  = team['pm_agent']
    rid_file  = runtime_dir / f"{team_id}.release_id"
    if not rid_file.exists():
        print(f"SKIP {team_id}: no active release_id in tmp/release-cycle-active/")
        continue

    release_id = rid_file.read_text().strip()
    if release_scope and release_id != release_scope:
        print(f"SKIP {team_id}: active release {release_id} does not match requested {release_scope}")
        continue
    team_release_ids[team_id] = release_id
    signoff = root / 'sessions' / pm_agent / 'artifacts' / 'release-signoffs' / f"{release_id}.md"

    if signoff.exists():
        print(f"OK   {team_id}: {release_id} already signed off")
        continue

    print(f"RUN  {team_id}: filing signoff for {release_id} ...")
    sys.stdout.flush()
    result = subprocess.run(
        ['bash', str(root / 'scripts' / 'release-signoff.sh'), team_id, release_id],
        capture_output=False,
        cwd=str(root),
    )
    if result.returncode != 0:
        print(f"WARN {team_id}: release-signoff.sh exited {result.returncode} — check Gate 2 evidence")
    else:
        print(f"DONE {team_id}: {release_id} signed off")

# Step 2 — write the orchestrator marker file so _coordinated_push_step() does not re-deploy.
if team_release_ids:
    combined_key = combined_release_marker_key(team_release_ids, coord_teams)
    pushed_dir = root / 'tmp' / 'auto-push-dispatched'
    pushed_dir.mkdir(parents=True, exist_ok=True)
    marker = pushed_dir / f"{combined_key}.pushed"
    marker_preexisting = marker.exists()
    current_matches_latest_advance = True
    for team in sorted(coord_teams, key=lambda t: t['id']):
        team_id = team['id']
        current_rid = team_release_ids.get(team_id, "")
        latest_advance_sentinel = pushed_dir / f"{team_id}.advanced"
        if not latest_advance_sentinel.exists():
            current_matches_latest_advance = False
            break
        latest_advanced = latest_advance_sentinel.read_text(encoding='utf-8').strip()
        if current_rid != latest_advanced:
            current_matches_latest_advance = False
            break

    if not marker_preexisting and current_matches_latest_advance:
        print(
            "MARKER skipped: current release_ids already match the latest advanced "
            "pair; no new coordinated push detected"
        )
    elif not marker_preexisting:
        marker.write_text(datetime.now(timezone.utc).isoformat() + "\n")
        print(f"MARKER written: {marker.name}")
    else:
        print(f"MARKER already exists: {marker.name}")

    def seed_handoff(team_id: str, current_release: str, next_release: str) -> None:
        seed_result = subprocess.run(
            ['bash', str(root / 'scripts' / 'release-cycle-start.sh'), team_id, current_release, next_release],
            capture_output=True,
            text=True,
            cwd=str(root),
        )
        if seed_result.returncode != 0:
            print(f"WARN {team_id}: release-cycle-start.sh exited {seed_result.returncode} after advance")
            stderr = (seed_result.stderr or "").strip()
            stdout = (seed_result.stdout or "").strip()
            if stderr:
                print(stderr)
            elif stdout:
                print(stdout)
        else:
            print(f"SEED {team_id}: release-cycle-start.sh queued current/next handoff")

    def reconcile_runtime_boundary(team_id: str, current_release: str, next_release: str) -> None:
        (runtime_dir / f"{team_id}.release_id").write_text(current_release + "\n", encoding='utf-8')
        (runtime_dir / f"{team_id}.next_release_id").write_text(next_release + "\n", encoding='utf-8')
        (runtime_dir / f"{team_id}.started_at").write_text(
            datetime.now(timezone.utc).isoformat() + "\n", encoding='utf-8'
        )

    # Step 3 — advance each team's release_id to next_release_id.
    # Runs unconditionally (not tied to marker creation) so a re-run of this
    # script after a pre-existing signoff doesn't skip the advance.
    # Idempotency uses two sentinels:
    # - <combined_key>.<team_id>.advanced records that this exact pushed pair was advanced.
    # - <team_id>.advanced records the latest release_id we advanced to.
    #
    # The pair-scoped sentinel prevents a stale per-team sentinel from blocking a
    # newer coordinated push whose current release_id happens to match the prior
    # advancement target.
    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    new_current_release_ids = {}
    advanced_team_ids = set()
    for team in sorted(coord_teams, key=lambda t: t['id']):
        team_id = team['id']
        current_rid = (runtime_dir / f"{team_id}.release_id").read_text(encoding='utf-8').strip()
        next_rid_file = runtime_dir / f"{team_id}.next_release_id"
        if not next_rid_file.exists():
            print(f"WARN {team_id}: no next_release_id file — skipping release_id advancement")
            continue
        new_current = next_rid_file.read_text(encoding='utf-8').strip()
        if not new_current:
            print(f"WARN {team_id}: next_release_id file is empty — skipping release_id advancement")
            continue
        new_current_release_ids[team_id] = new_current
        # Idempotency: if this exact pushed pair was already advanced, skip.
        pair_advance_sentinel = pushed_dir / f"{combined_key}.{team_id}.advanced"
        latest_advance_sentinel = pushed_dir / f"{team_id}.advanced"
        if not marker_preexisting and current_matches_latest_advance:
            sentinel_val = latest_advance_sentinel.read_text(encoding='utf-8').strip()
            seeded_next = next_release_id_after(sentinel_val, team_id, today)
            reconcile_runtime_boundary(team_id, sentinel_val, seeded_next)
            print(f"SKIP {team_id}: current release already reflects latest advance {sentinel_val}")
            seed_handoff(team_id, sentinel_val, seeded_next)
            continue
        if pair_advance_sentinel.exists():
            sentinel_val = pair_advance_sentinel.read_text(encoding='utf-8').strip()
            seeded_next = next_release_id_after(sentinel_val, team_id, today)
            reconcile_runtime_boundary(team_id, sentinel_val, seeded_next)
            print(f"SKIP {team_id}: pushed pair already advanced to {sentinel_val}")
            # Re-seed from the already-advanced release, not from the stale
            # current->next pair that originally triggered the push.
            seed_handoff(team_id, sentinel_val, seeded_next)
            continue
        # Manual rerun safety: if we had to create the push marker in this run and
        # the proposed advancement target already equals the last advancement target,
        # we already processed this transition — do not advance again.
        # BUG-FIX (2026-04-19): compare new_current (proposed target) to sentinel,
        # NOT current_rid. current_rid equals the sentinel on EVERY legitimate new push
        # (it is what the previous push advanced TO), so the old check incorrectly
        # skipped valid new pushes, preventing cycle advancement and causing the groom
        # dispatch off-by-one bug.
        if not marker_preexisting and latest_advance_sentinel.exists():
            sentinel_val = latest_advance_sentinel.read_text(encoding='utf-8').strip()
            if new_current == sentinel_val:
                seeded_next = next_release_id_after(sentinel_val, team_id, today)
                reconcile_runtime_boundary(team_id, sentinel_val, seeded_next)
                print(f"SKIP {team_id}: release_id already advanced to {sentinel_val}")
                # Re-seed from the already-advanced release so retries do not
                # restore the stale pre-push release_id into runtime state.
                seed_handoff(team_id, sentinel_val, seeded_next)
                continue
        new_next = next_release_id_after(new_current, team_id, today)
        (runtime_dir / f"{team_id}.release_id").write_text(new_current + "\n", encoding='utf-8')
        (runtime_dir / f"{team_id}.next_release_id").write_text(new_next + "\n", encoding='utf-8')
        (runtime_dir / f"{team_id}.started_at").write_text(
            datetime.now(timezone.utc).isoformat() + "\n", encoding='utf-8'
        )
        pair_advance_sentinel.write_text(new_current + "\n", encoding='utf-8')
        latest_advance_sentinel.write_text(new_current + "\n", encoding='utf-8')
        print(f"ADVANCE {team_id}: release_id={new_current} next_release_id={new_next}")
        seed_handoff(team_id, new_current, new_next)
        advanced_team_ids.add(team_id)
    old_release_ids = list(team_release_ids.values())
    for team in sorted(coord_teams, key=lambda t: t['id']):
        team_id = team['id']
        if team_id not in advanced_team_ids:
            continue
        pm_agent = team['pm_agent']
        current_release_id = new_current_release_ids.get(team_id, "")
        archived = archive_stale_pm_release_items(
            root,
            pm_agent,
            old_release_ids=old_release_ids,
            current_release_id=current_release_id,
        )
        if archived:
            print(f"ARCHIVE {pm_agent}: {', '.join(archived)}")

    reconcile_failures = []
    for team in sorted(coord_teams, key=lambda t: t['id']):
        team_id = team['id']
        release_id = team_release_ids.get(team_id, "").strip()
        if not release_id:
            continue
        print(f"RECONCILE {team_id}: reconciling shipped truth for {release_id}")
        result = subprocess.run(
            ['python3', str(root / 'scripts' / 'release-reconcile-shipped.py'), team_id, release_id],
            capture_output=True,
            text=True,
            cwd=str(root),
        )
        if result.stdout.strip():
            print(result.stdout.strip())
        if result.returncode != 0:
            if result.stderr.strip():
                print(result.stderr.strip())
            reconcile_failures.append((team_id, release_id, result.returncode))
else:
    print("WARNING: no active team releases found — nothing to push")
    reconcile_failures = []

if reconcile_failures:
    for team_id, release_id, code in reconcile_failures:
        print(f"WARN {team_id}: release reconciliation failed for {release_id} (exit {code})")
    raise SystemExit(1)
PY

echo
if [ -z "$TEAM_SCOPE" ]; then
    bash "${ROOT_DIR}/scripts/ceo-release-boundary-health.sh"
else
    echo "Skipping global boundary health in team-scoped mode."
fi

# Dispatch Gate R5 production audit inbox item for CEO (ISSUE-011 fix).
# This ensures the post-push audit runs within the 1h WARN / 4h FAIL window
# rather than waiting for the CEO to discover it manually.
AUDIT_DATE=$(date -u +%Y%m%d-%H%M%S)
CEO_INBOX="${ROOT_DIR}/sessions/ceo-copilot-2/inbox"
mkdir -p "${CEO_INBOX}"

if [ -n "$TEAM_SCOPE" ] && [ -n "$RELEASE_SCOPE" ]; then
    PUSHED_RELEASES="$RELEASE_SCOPE"
else
    PUSHED_RELEASES="$(cat "${ROOT_DIR}"/tmp/release-cycle-active/*.release_id 2>/dev/null | sort -u)"
fi

for pushed_rid in $PUSHED_RELEASES; do
    AUDIT_ITEM="${CEO_INBOX}/${AUDIT_DATE}-gate-r5-audit-${pushed_rid}"
    if [ ! -d "${AUDIT_ITEM}" ]; then
        mkdir -p "${AUDIT_ITEM}"
        SITE=$(echo "${pushed_rid}" | sed 's/^[0-9]*-//;s/-release-[a-z0-9]*$//')
        cat > "${AUDIT_ITEM}/command.md" <<ITEMEOF
# Gate R5 Production Audit — ${pushed_rid}

**Trigger:** Automated — dispatched by post-coordinated-push.sh immediately after production push.
**Site:** ${SITE}
**Release:** ${pushed_rid}
**Required within:** 1h (WARN) / 4h (FAIL) of push

## Task

Run the Gate R5 production smoke audit:

\`\`\`bash
ALLOW_PROD_QA=1 FORSETI_BASE_URL=https://forseti.life bash scripts/site-audit-run.sh forseti-life
\`\`\`

Review output for regressions. If clean, mark this item done. If issues found, create incident and block next release.

## Priority
- ROI: 90
- Rationale: R5 is the only post-push regression check; a 6h delay (as in release-r) leaves production issues undetected.
ITEMEOF
        cp "${AUDIT_ITEM}/command.md" "${AUDIT_ITEM}/README.md"
        echo "GATE-R5: dispatched audit item for ${pushed_rid} → ${AUDIT_ITEM}"
    else
        echo "GATE-R5: audit item already exists for ${pushed_rid}, skipping"
    fi
done

echo "=== post-coordinated-push complete ==="
