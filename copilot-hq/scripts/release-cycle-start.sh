#!/usr/bin/env bash
# release-cycle-start.sh — Start a release cycle for a site/product team
#
# Usage:
#   ./scripts/release-cycle-start.sh <site> <current-release-id> <next-release-id>
#
# Both release IDs are required. The org always has two releases defined:
#   current: Dev executing, QA verifying (Stage 3–7)
#   next:    PM grooming, QA writing test plans (parallel, Stage 3 of current)
#
# Examples:
#   ./scripts/release-cycle-start.sh forseti.life 20260226-forseti-r1 20260226-forseti-r2
#   ./scripts/release-cycle-start.sh dungeoncrawler 20260226-dc-r1 20260226-dc-r2
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

site="${1:-}"
release_id="${2:-}"
next_release_id="${3:-}"

if [ -z "$site" ] || [ -z "$release_id" ] || [ -z "$next_release_id" ]; then
  echo "Usage: $0 <site> <current-release-id> <next-release-id>" >&2
  echo "" >&2
  echo "Both release IDs are required. The org always has two releases defined:" >&2
  echo "  current: Dev executing + QA verifying (Stage 3–7)" >&2
  echo "  next:    PM grooming + QA test plan design (parallel)" >&2
  echo "" >&2
  echo "Examples:" >&2
  echo "  $0 forseti.life 20260226-forseti-r1 20260226-forseti-r2" >&2
  exit 2
fi

if ! lookup_result="$(python3 - "$PRODUCT_TEAMS_JSON" "$site" <<'PY'
import sys
from pathlib import Path

cfg_path = Path(sys.argv[1])
query = sys.argv[2]

sys.path.insert(0, str(cfg_path.parent.parent.parent / "scripts" / "lib"))
from release_cycle_helpers import TeamLookupError, lookup_active_team  # noqa: E402

try:
    team = lookup_active_team(cfg_path, query)
except TeamLookupError as exc:
    print(f"ERROR: {exc}", file=sys.stderr)
    raise SystemExit(2)

print(
    f"{str(team.get('id') or '').strip()}\t"
    f"{str(team.get('site') or '').strip()}\t"
    f"{str(team.get('qa_agent') or '').strip()}\t"
    f"{str(team.get('pm_agent') or '').strip()}"
)
PY
  2>&1)"; then
  echo "$lookup_result" >&2
  exit 2
fi

IFS=$'\t' read -r team_id site qa_agent pm_agent <<<"$lookup_result"

today="$(date +%Y%m%d)"
slug="$(echo "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"

item_id="${today}-release-preflight-test-suite-${slug}"
inbox_dir="sessions/${qa_agent}/inbox/${item_id}"
outbox_file="sessions/${qa_agent}/outbox/${item_id}.md"
preflight_skip_reason=""

if [ -d "$inbox_dir" ] || [ -f "$outbox_file" ]; then
  preflight_skip_reason="OK: already queued/completed: ${qa_agent} ${item_id}"
fi

# GAP-QA-PREFLIGHT-DEDUP-01: suppress redundant preflight dispatches.
# If a preflight outbox was written for this qa_agent within the last 4 hours
# AND no QA-scoped commits (qa-suites/, qa-permissions.json, 03-test-plan.md)
# have landed since that outbox was written, skip this dispatch with a log message.
# This eliminates ~80% of preflight slot consumption when the release is advancing
# rapidly but QA configuration has not changed.
_recent_outbox=""
_four_hours_ago=$(date -d "4 hours ago" +%s 2>/dev/null || date -v-4H +%s 2>/dev/null || echo "0")
for _f in "sessions/${qa_agent}/outbox/"*-release-preflight-test-suite-*.md; do
  [ -f "$_f" ] || continue
  _fmtime=$(stat -c %Y "$_f" 2>/dev/null || stat -f %m "$_f" 2>/dev/null || echo "0")
  if [ "$_fmtime" -ge "$_four_hours_ago" ]; then
    # Pick the most-recent file; stat output is already epoch so we can compare
    if [ -z "$_recent_outbox" ]; then
      _recent_outbox="$_f"
      _recent_mtime="$_fmtime"
    elif [ "$_fmtime" -gt "$_recent_mtime" ]; then
      _recent_outbox="$_f"
      _recent_mtime="$_fmtime"
    fi
  fi
done
if [ -n "$_recent_outbox" ]; then
  _outbox_iso=$(date -d "@${_recent_mtime}" -Iseconds 2>/dev/null || date -r "$_recent_mtime" -Iseconds 2>/dev/null || echo "")
  _qa_commits=""
  if [ -n "$_outbox_iso" ]; then
    _qa_commits=$(git -C "$ROOT_DIR" log --since="$_outbox_iso" --oneline \
      -- "qa-suites/" "org-chart/sites/*/qa-permissions.json" "features/**/03-test-plan.md" \
      2>/dev/null | head -1 || true)
  fi
  if [ -z "$_qa_commits" ] && [ -z "$preflight_skip_reason" ]; then
    preflight_skip_reason="PREFLIGHT-SUPPRESSED: recent outbox exists (${_recent_outbox}), no QA-scoped commits since ${_outbox_iso}; skipping dispatch."
  fi
fi

# Always write state files first — before any early exits.
# BUG-FIX: state must be persisted before conditional dispatches so the orchestrator
# release_cycle step sees the new release_id on the next tick regardless of whether
# a QA preflight item is dispatched (GAP-AGE-PREFLIGHT-01 exit was skipping this).
mkdir -p tmp/release-cycle-active 2>/dev/null || true
printf '%s\n' "$release_id"      > "tmp/release-cycle-active/${team_id}.release_id"
printf '%s\n' "$next_release_id" > "tmp/release-cycle-active/${team_id}.next_release_id"
printf '%s\n' "$(date -Iseconds)" > "tmp/release-cycle-active/${team_id}.started_at"

# GAP-AGE-PREFLIGHT-01: suppress preflight when no features are activated for this release.
# Count features with Status: in_progress AND Release: <release_id> — if zero, skip dispatch.
_pf_feat_count=$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
  | xargs grep -l "^- Release:.*${release_id}" 2>/dev/null | wc -l | tr -d '[:space:]' || echo 0)
if [ -n "$preflight_skip_reason" ]; then
  echo "$preflight_skip_reason"
  qa_status_line="$preflight_skip_reason"
elif [ "${_pf_feat_count:-0}" -eq 0 ]; then
  qa_status_line="PREFLIGHT-SUPPRESSED: no features activated for release ${release_id}; skipping preflight dispatch."
  echo "$qa_status_line"
else

mkdir -p "$inbox_dir" 2>/dev/null || true
printf '%s\n' "9" >"$inbox_dir/roi.txt" 2>/dev/null || true

cat >"$inbox_dir/command.md" <<MD
- command: |
    Release-cycle QA preflight (run once per release cycle).

    - Site: ${site}
    - Product team: ${team_id}
    - Release id: ${release_id}

    Goal:
    - As the FIRST QA task of this release cycle, review + refactor the QA test automation scripts and configs.
    - This is a process improvement step that happens once per release cycle.

    Required review/refactor targets (scripted; no GenAI required):
    - scripts/site-audit-run.sh
    - scripts/site-full-audit.py
    - scripts/site-validate-urls.py
    - scripts/drupal-custom-routes-audit.py
    - scripts/role-permissions-validate.py
    - org-chart/sites/${site}/qa-permissions.json (role matrix + cookie env vars)

    Expectations:
    - Confirm newly discovered URLs from scans are included in validation (union validation).
    - Confirm role coverage matches all relevant Drupal roles for this site (update qa-permissions.json roles list as needed).
    - Keep production audits gated behind ALLOW_PROD_QA=1.

    Deliverables:
    - If changes are needed: commit fixes to HQ (git add/commit) and reference commit hash in your outbox.
    - If no changes: outbox should explicitly state "preflight complete; no changes needed".

    Then proceed with normal QA verification work for release-bound items.
MD

fi  # end PREFLIGHT-SUPPRESSED gate

if [ -z "${qa_status_line:-}" ]; then
  qa_status_line="QUEUED: ${qa_agent} ${item_id} (current release: ${release_id})"
fi

# Create PM grooming task for the next release (parallel to Dev execution this cycle)
if [ -n "$pm_agent" ]; then
  next_slug="$(echo "$next_release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"
  pm_item_id="${today}-groom-${next_slug}"
  pm_inbox_dir="sessions/${pm_agent}/inbox/${pm_item_id}"
  pm_outbox_file="sessions/${pm_agent}/outbox/${pm_item_id}.md"

  if [ -d "$pm_inbox_dir" ] || [ -f "$pm_outbox_file" ]; then
    echo "OK: PM grooming task already queued/completed: ${pm_agent} ${pm_item_id}"
  else
    mkdir -p "$pm_inbox_dir" 2>/dev/null || true
    # Keep next-release grooming warm enough to use spare slots without outranking
    # urgent current-release execution/signoff work.
    printf '%s\n' "25" >"$pm_inbox_dir/roi.txt" 2>/dev/null || true

    cat >"$pm_inbox_dir/command.md" <<MD
# Groom Next Release: ${next_release_id}

- Site: ${site}
- Current release (Dev executing): ${release_id}
- Next release (your target): ${next_release_id}

The org always has two releases defined simultaneously:
- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.
- **Next release** — You groom the backlog so Stage 0 of ${next_release_id} is instant scope selection.

This task does NOT touch the current release. All work here is for ${next_release_id} only.

## Steps

### 1. Audit the existing next-release backlog first
\`\`\`bash
python3 - <<'PY'
import pathlib, re
site = "${site}"
for fm in sorted(pathlib.Path("features").glob("*/feature.md")):
    text = fm.read_text(encoding="utf-8")
    if f"- Website: {site}" not in text:
        continue
    m = re.search(r"^- Status:\s*(.+)$", text, re.MULTILINE)
    if not m:
        continue
    status = m.group(1).strip()
    if status not in {"planned", "ready", "in_progress"}:
        continue
    ac = fm.with_name("01-acceptance-criteria.md").exists()
    tp = fm.with_name("03-test-plan.md").exists()
    if not (ac and tp):
        print(f"{fm.parent.name}: status={status} ac={ac} testplan={tp}")
PY
\`\`\`
If this prints any features, finish those backlog items before treating suggestion intake as done.

### 2. Pull community suggestions
\`\`\`bash
./scripts/suggestion-intake.sh ${team_id}
\`\`\`

### 3. Triage each suggestion
\`\`\`bash
./scripts/suggestion-triage.sh ${team_id} <nid> accept <feature-id>
./scripts/suggestion-triage.sh ${team_id} <nid> defer
./scripts/suggestion-triage.sh ${team_id} <nid> decline
./scripts/suggestion-triage.sh ${team_id} <nid> escalate
\`\`\`

Mandatory gate: if a suggestion clearly requests security abuse, release-integrity bypass, intentional crash/data-destruction behavior,
or a major architecture replatform/rewrite,
do NOT accept at PM level. Use \`escalate\` so it is reviewed at human board level first.
Otherwise continue normal PM triage so the majority of valid product requests can flow.

### 4. Write or complete Acceptance Criteria
  features/<feature-id>/01-acceptance-criteria.md  (from templates/01-acceptance-criteria.md)
  Any accepted or already-tracked next-release feature missing AC must be completed before handing to QA.

### 5. Hand off to QA for test plan design
\`\`\`bash
./scripts/pm-qa-handoff.sh ${team_id} <feature-id>
\`\`\`
Any next-release feature that has AC but is missing \`03-test-plan.md\` must be handed off.
QA writes features/<id>/03-test-plan.md (spec only — NOT added to suite.json until Stage 0).
QA signals back via qa-pm-testgen-complete.sh when done.

### 6. When next Stage 0 starts: activate scoped features
For each feature selected into ${next_release_id}:
\`\`\`bash
./scripts/pm-scope-activate.sh ${team_id} <feature-id>
\`\`\`
This sends QA the activation task to add tests to suite.json for the live release.

## Groomed/ready gate (required for Stage 0 eligibility)
A feature is ready when ALL THREE exist:
- features/<id>/feature.md          (status: ready)
- features/<id>/01-acceptance-criteria.md
- features/<id>/03-test-plan.md

If suggestion intake returns nothing, the grooming task is still not done until the backlog audit above is clean.

Security override: any feature requiring board-security review is ineligible until explicit board approval is documented.

Anything not groomed when Stage 0 of ${next_release_id} starts is automatically deferred.

## References
- runbooks/feature-intake.md
- runbooks/intake-to-qa-handoff.md
MD
    echo "QUEUED: ${pm_agent} ${pm_item_id} (next release: ${next_release_id})"
  fi
fi

# Queue BA reference scan task (Stage 3 parallel track — generates pre-triage feature stubs from reference docs)
if [ -f "scripts/ba-reference-scan.sh" ]; then
  bash scripts/ba-reference-scan.sh "${team_id}" "${next_release_id}" || true
fi

# Queue code-review inbox item for agent-code-review (GAP-CR-1 fix)
cr_item_id="${today}-code-review-${site}-${slug}"
cr_inbox_dir="sessions/agent-code-review/inbox/${cr_item_id}"
cr_outbox_file="sessions/agent-code-review/outbox/${cr_item_id}.md"

if [ -d "$cr_inbox_dir" ] || [ -f "$cr_outbox_file" ]; then
  echo "OK: code-review task already queued/completed: agent-code-review ${cr_item_id}"
else
  mkdir -p "$cr_inbox_dir" 2>/dev/null || true
  printf '%s\n' "10" >"$cr_inbox_dir/roi.txt" 2>/dev/null || true

  cat >"$cr_inbox_dir/command.md" <<MD
- Agent: agent-code-review
- Status: pending
- command: |
    Pre-ship code review for ${site} release ${release_id}.
    Review all commits in this release cycle against the code-review checklist in
    \`org-chart/agents/instructions/agent-code-review.instructions.md\`.
    Focus on: CSRF protection on new POST routes, authorization bypass risks,
    schema hook pairing (hook_schema + hook_update_N both present), stale
    private duplicates of canonical data, and hardcoded paths.
    Produce: one finding per issue, severity (CRITICAL/HIGH/MEDIUM/LOW),
    file path, and recommended fix pattern.
MD

  echo "QUEUED: agent-code-review ${cr_item_id} (current release: ${release_id})"
fi

echo "$qa_status_line"
echo "RELEASE_CYCLE_ACTIVE: ${team_id} current=${release_id} next=${next_release_id}"
