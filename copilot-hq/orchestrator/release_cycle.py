"""Release cycle orchestration: state machine, signoffs, deployment."""

from __future__ import annotations

import json as _json
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

from orchestrator.release_prerequisites import ReleasePrerequisiteValidator


def _run(cmd: List[str], *, timeout: int = 600) -> Tuple[int, str]:
    """Execute a shell command (imported from run.py context)."""
    import subprocess
    try:
        proc = subprocess.run(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=timeout,
        )
        return proc.returncode, (proc.stdout or "").strip()
    except subprocess.TimeoutExpired:
        return -1, f"TIMEOUT after {timeout}s"


def _safe_int(s: Any, default: int = 0) -> int:
    """Safely convert to int with default fallback."""
    try:
        return int(s)
    except Exception:
        return default


def _emit_event(event_type: str) -> None:
    """Emit an orchestrator event (stub for now)."""
    pass


def _release_cycle_control_state() -> Dict[str, Any]:
    """Read release cycle control state (imported from run.py context)."""
    # This will be passed in or imported from run.py
    return {"enabled": True}


def _dispatch_signoff_reminders() -> None:
    """Dispatch reminders to unsigned PMs (imported from run.py context)."""
    pass


def _dispatch_gate2_auto_approve() -> None:
    """Auto-approve Gate 2 if conditions are met (imported from run.py context)."""
    pass


def _dispatch_proactive_awaiting_signoff() -> None:
    """Proactively dispatch awaiting-signoff items (imported from run.py context)."""
    pass


# ── ROI management ────────────────────────────────────────────────────────────

def set_min_inbox_roi(item_dir: Path, min_roi: int) -> None:
    """Ensure inbox item has minimum ROI, bumping if needed."""
    if min_roi <= 0:
        return
    roi_file = item_dir / "roi.txt"
    current = 1
    if roi_file.exists():
        lines = roi_file.read_text(encoding="utf-8", errors="ignore").splitlines()
        digits = "".join(ch for ch in (lines[0] if lines else "") if ch.isdigit())
        current = max(1, _safe_int(digits, 1))
    if current >= min_roi:
        return
    roi_file.write_text(f"{min_roi}\n", encoding="utf-8")


# ── PM grooming dispatch ──────────────────────────────────────────────────────

def find_pm_grooming_item(pm_agent: str, next_release_id: str, repo_root: Path) -> Tuple[Path | None, bool]:
    """Find existing PM grooming item (inbox or outbox) for a release."""
    slug = re.sub(r"[^A-Za-z0-9._-]", "-", next_release_id).strip("-")[:60]
    inbox = repo_root / "sessions" / pm_agent / "inbox"
    outbox = repo_root / "sessions" / pm_agent / "outbox"
    if inbox.exists():
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and item.name.endswith(f"-groom-{slug}"):
                return item, False
    if outbox.exists():
        for item in outbox.glob(f"*-groom-{slug}.md"):
            if item.is_file():
                return item, True
    return None, False


def site_has_incomplete_grooming_backlog(site: str, current_release: str, repo_root: Path) -> bool:
    """Check if a site has ungroomed features in the backlog."""
    features_dir = repo_root / "features"
    if not features_dir.exists():
        return False
    for feature_md in features_dir.glob("*/feature.md"):
        if not feature_md.is_file():
            continue
        text = feature_md.read_text(encoding="utf-8", errors="ignore")
        if f"- Website: {site}" not in text:
            continue
        m = re.search(r"^- Status:\s*(.+)$", text, re.MULTILINE)
        if not m:
            continue
        status = m.group(1).strip()
        if status not in {"planned", "ready", "in_progress"}:
            continue
        release_match = re.search(r"^- Release:\s*(.*)$", text, re.MULTILINE)
        release_value = release_match.group(1).strip() if release_match else ""
        if status == "in_progress" and release_value == current_release:
            continue
        feature_dir = feature_md.parent
        if not (feature_dir / "01-acceptance-criteria.md").exists():
            return True
        if not (feature_dir / "03-test-plan.md").exists():
            return True
    return False


def ensure_pm_next_release_grooming(
    *,
    site: str,
    team_id: str,
    pm_agent: str,
    current_release: str,
    next_release_id: str,
    today: str,
    repo_root: Path,
    min_roi: int = 25,
) -> str:
    """Ensure PM has a grooming task for the next release."""
    existing, is_outbox = find_pm_grooming_item(pm_agent, next_release_id, repo_root)
    if existing is not None:
        if not is_outbox:
            set_min_inbox_roi(existing, min_roi)
            return f"pm-groom-prioritized:{existing.name}"
        if not site_has_incomplete_grooming_backlog(site, current_release, repo_root):
            return f"pm-groom-covered:{existing.name}"

    slug = re.sub(r"[^A-Za-z0-9._-]", "-", next_release_id).strip("-")[:60]
    item_id = f"{today}-groom-{slug}"
    item_dir = repo_root / "sessions" / pm_agent / "inbox" / item_id
    item_dir.mkdir(parents=True, exist_ok=True)
    set_min_inbox_roi(item_dir, min_roi)
    (item_dir / "command.md").write_text(
        f"# Groom Next Release: {next_release_id}\n\n"
        f"- Site: {site}\n"
        f"- Current release (Dev executing): {current_release}\n"
        f"- Next release (your target): {next_release_id}\n\n"
        "The org always has two releases defined simultaneously:\n"
        "- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.\n"
        "- **Next release** — You groom the backlog so Stage 0 of the next release is instant scope selection.\n\n"
        f"This task does NOT touch the current release. All work here is for {next_release_id} only.\n\n"
        "## Steps\n\n"
        "### 1. Audit the existing next-release backlog first\n"
        "If there are already next-release features in `planned`, `ready`, or `in_progress` without both grooming artifacts, finish those before treating suggestion intake as done.\n"
        "```bash\n"
        "python3 - <<'PY'\n"
        "import pathlib, re\n"
        f"site = {site!r}\n"
        "for fm in sorted(pathlib.Path('features').glob('*/feature.md')):\n"
        "    text = fm.read_text(encoding='utf-8')\n"
        "    if f'- Website: {site}' not in text:\n"
        "        continue\n"
        "    m = re.search(r'^- Status:\\s*(.+)$', text, re.MULTILINE)\n"
        "    if not m:\n"
        "        continue\n"
        "    status = m.group(1).strip()\n"
        "    if status not in {'planned', 'ready', 'in_progress'}:\n"
        "        continue\n"
        "    ac = fm.with_name('01-acceptance-criteria.md').exists()\n"
        "    tp = fm.with_name('03-test-plan.md').exists()\n"
        "    if not (ac and tp):\n"
        "        print(f'{fm.parent.name}: status={status} ac={ac} testplan={tp}')\n"
        "PY\n"
        "```\n\n"
        "### 2. Pull community suggestions\n"
        "Run `./scripts/suggestion-intake.sh` once for the site.\n\n"
        "### 3. Triage valid suggestions\n"
        "Create or curate feature briefs for the next release only.\n\n"
        "### 4. Write or complete acceptance criteria\n"
        "Any accepted or already-tracked next-release feature missing `01-acceptance-criteria.md` must get a complete AC contract before QA handoff.\n\n"
        "### 5. Hand features to QA for test-plan design\n"
        "Any next-release feature that has AC but is missing `03-test-plan.md` must be handed to QA via `./scripts/pm-qa-handoff.sh`.\n\n"
        "### 6. Leave current-release scope unchanged\n"
        "Activation happens only when the next release becomes current.\n\n"
        "## Done when\n"
        f"- The next release `{next_release_id}` has an actively groomed ready backlog.\n"
        "- Every existing next-release feature already in `planned`, `ready`, or `in_progress` either has both grooming artifacts (`01-acceptance-criteria.md` + `03-test-plan.md`) or is explicitly deferred/blocked.\n"
        "- Any newly accepted feature has acceptance criteria and a QA handoff queued.\n",
        encoding="utf-8",
    )
    return f"pm-groom-queued:{item_id}"


# ── BA reference scanning ─────────────────────────────────────────────────────

def _item_mentions_release(item_dir: Path, release_ids: List[str]) -> bool:
    """Check if an item mentions any of the given release IDs."""
    readme = item_dir / "README.md"
    if readme.exists():
        content = readme.read_text(encoding="utf-8", errors="ignore")
        for rid in release_ids:
            if rid in content:
                return True
    return False


def find_ba_next_release_item(ba_agent: str, next_release_id: str, repo_root: Path) -> Tuple[Path | None, bool]:
    """Find existing BA scan item for a release."""
    inbox = repo_root / "sessions" / ba_agent / "inbox"
    outbox = repo_root / "sessions" / ba_agent / "outbox"
    if inbox.exists():
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and _item_mentions_release(item, [next_release_id]):
                return item, False
    if outbox.exists():
        for item in outbox.glob("*.md"):
            if item.is_file():
                text = item.read_text(encoding="utf-8", errors="ignore")
                if next_release_id in text:
                    return item, True
    return None, False


def ensure_ba_next_release_scan(
    team_id: str,
    ba_agent: str,
    next_release_id: str,
    repo_root: Path,
    min_roi: int = 18,
) -> str:
    """Ensure BA has a reference scan task for the next release."""
    existing, is_outbox = find_ba_next_release_item(ba_agent, next_release_id, repo_root)
    if existing is not None:
        if not is_outbox:
            set_min_inbox_roi(existing, min_roi)
            return f"ba-scan-prioritized:{existing.name}"
        return f"ba-scan-covered:{existing.name}"

    rc, _ = _run(["bash", "scripts/ba-reference-scan.sh", team_id, next_release_id], timeout=120)
    created, is_outbox = find_ba_next_release_item(ba_agent, next_release_id, repo_root)
    if created is not None and not is_outbox:
        set_min_inbox_roi(created, min_roi)
        return f"ba-scan-queued:{created.name}"
    return f"ba-scan-rc:{rc}"


def ensure_parallel_release_coverage(
    *,
    team_id: str,
    site: str,
    pm_agent: str,
    ba_agent: str,
    current_release: str,
    next_release_id: str,
    today: str,
    repo_root: Path,
) -> List[str]:
    """Ensure both PM grooming and BA scanning are queued for the next release."""
    actions: List[str] = []
    if pm_agent and current_release and next_release_id:
        actions.append(
            ensure_pm_next_release_grooming(
                site=site,
                team_id=team_id,
                pm_agent=pm_agent,
                current_release=current_release,
                next_release_id=next_release_id,
                today=today,
                repo_root=repo_root,
            )
        )
    if ba_agent and next_release_id:
        actions.append(ensure_ba_next_release_scan(team_id, ba_agent, next_release_id, repo_root))
    return actions


# ── Release cycle state machine ───────────────────────────────────────────────

def _next_release_id_after(release_id: str, team_id: str, current_day: str) -> str:
    """Generate the next monotonic release ID in the sequence."""
    suffixes = ["release", "release-next"] + [f"release-{chr(c)}" for c in range(ord("b"), ord("z") + 1)]
    date_part = current_day
    suffix = "release"

    match = re.match(rf"^(\d{{8}})-{re.escape(team_id)}-(.+)$", release_id or "")
    if match:
        date_part = match.group(1)
        suffix = match.group(2)

    try:
        idx = suffixes.index(suffix)
    except ValueError:
        idx = 0

    next_idx = min(idx + 1, len(suffixes) - 1)
    return f"{date_part}-{team_id}-{suffixes[next_idx]}"


def run_release_cycle_step(log: List[Any], repo_root: Path) -> None:
    """Ensure each coordinated-release team has an active release cycle.

    For each eligible team (active + release_preflight_enabled + coordinated_release_default):
      - If no active release → start a new one (current + next IDs)
      - If active but no next_release_id tracked → write it so the cycle can advance
      - If current release is signed off → advance: next becomes current, generate new next
    """
    release_control = _release_cycle_control_state()
    if not bool(release_control.get("enabled", True)):
        log.append({
            "step": "release_cycle",
            "status": "paused",
            "state_file": release_control.get("state_file"),
            "reason": release_control.get("reason"),
        })
        return

    active_dir = repo_root / "tmp" / "release-cycle-active"
    active_dir.mkdir(parents=True, exist_ok=True)

    teams_path = repo_root / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        log.append({"step": "release_cycle", "error": "could not read product-teams.json"})
        return

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    results: List[Dict[str, Any]] = []

    for team in teams_data.get("teams", []):
        if not (team.get("active") and team.get("release_preflight_enabled") and team.get("coordinated_release_default")):
            continue
        team_id = (team.get("id") or "").strip()
        site = (team.get("site") or team_id).strip() or team_id
        pm_agent = (team.get("pm_agent") or "").strip()
        ba_agent = (team.get("ba_agent") or "").strip()
        if not team_id:
            continue

        release_id_file = active_dir / f"{team_id}.release_id"
        next_release_id_file = active_dir / f"{team_id}.next_release_id"

        current_release = release_id_file.read_text().strip() if release_id_file.exists() else ""
        next_release = next_release_id_file.read_text().strip() if next_release_id_file.exists() else ""

        # Detect signoff: pm-<team>/artifacts/release-signoffs/<release_id>.md
        cycle_signed_off = False
        if current_release and pm_agent:
            slug = re.sub(r"[^A-Za-z0-9._-]", "-", current_release)[:80]
            signoff_file = repo_root / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
            cycle_signed_off = signoff_file.exists()

        if not current_release:
            new_current = f"{today}-{team_id}-release"
            new_next = f"{today}-{team_id}-release-next"
            action = "start"

            rc, out = _run(
                ["bash", "scripts/release-cycle-start.sh", team_id, new_current, new_next],
                timeout=120,
            )
            results.append({"team": team_id, "action": action, "current": new_current, "next": new_next, "rc": rc})
            if rc == 0:
                print(f"RELEASE-CYCLE: {action} {team_id} current={new_current} next={new_next}")
                _emit_event("release-cycle-advanced")
        elif cycle_signed_off:
            validator = ReleasePrerequisiteValidator(repo_root)
            can_advance, prereq_issues = validator.validate_release_can_advance_to_push(
                team_id, current_release
            )

            if not can_advance:
                diag = validator.diagnose_stalled_release(team_id, current_release)
                print(f"RELEASE-CYCLE: {team_id} {current_release} → BLOCKED waiting for coordinated teams")
                print(f"  Issues: {prereq_issues}")
                print(f"  Diagnostics: {diag}")
                print(f"  ACTION: Investigate why coordinated teams haven't signed off (root cause analysis needed)")
                action = "blocked_waiting_cross_team_signoffs"
                results.append({
                    "team": team_id,
                    "action": action,
                    "current": current_release,
                    "next": next_release,
                    "issues": prereq_issues,
                    "diagnostics": diag,
                })
            else:
                expected_next = _next_release_id_after(current_release, team_id, today)
                if next_release != expected_next:
                    next_release_id_file.write_text(expected_next + "\n")
                    next_release = expected_next
                    action = "signed_off_next_fixed"
                else:
                    action = "signed_off_waiting_push"
                _emit_event("release-signoff-created")
                try:
                    _dispatch_signoff_reminders()
                except Exception as e:
                    print(f"SIGNOFF-REMINDER-ERR: {e}")
                coverage = ensure_parallel_release_coverage(
                    team_id=team_id,
                    site=site,
                    pm_agent=pm_agent,
                    ba_agent=ba_agent,
                    current_release=current_release,
                    next_release_id=next_release,
                    today=today,
                    repo_root=repo_root,
                )
                if prereq_issues:
                    results.append(
                        {"team": team_id, "action": action, "current": current_release, "next": next_release, "parallel": coverage, "remediation": prereq_issues}
                    )
                else:
                    results.append(
                        {"team": team_id, "action": action, "current": current_release, "next": next_release, "parallel": coverage}
                    )

        else:
            expected_next = _next_release_id_after(current_release, team_id, today)
            if next_release != expected_next:
                had_next = bool(next_release)
                next_release_id_file.write_text(expected_next + "\n")
                next_release = expected_next
                action = "next_fixed" if had_next else "next_set"
            else:
                action = "active"
            coverage = ensure_parallel_release_coverage(
                team_id=team_id,
                site=site,
                pm_agent=pm_agent,
                ba_agent=ba_agent,
                current_release=current_release,
                next_release_id=next_release,
                today=today,
                repo_root=repo_root,
            )
            results.append(
                {"team": team_id, "action": action, "current": current_release, "next": next_release, "parallel": coverage}
            )

    log.append({"step": "release_cycle", "teams": results})

    try:
        _dispatch_gate2_auto_approve()
    except Exception as _e:
        log.append({"step": "release_cycle", "gate2_auto_approve_error": str(_e)})

    try:
        _dispatch_proactive_awaiting_signoff()
    except Exception as _e:
        log.append({"step": "release_cycle", "proactive_signoff_error": str(_e)})


# ── Coordinated push step ─────────────────────────────────────────────────────

def write_release_notes(release_id: str, slug: str, required: List[Dict[str, Any]], repo_root: Path) -> None:
    """Auto-generate 05-release-notes.md in pm-forseti's release-candidates dir."""
    rc_dir = repo_root / "sessions" / "pm-forseti" / "artifacts" / "release-candidates" / slug
    notes_file = rc_dir / "05-release-notes.md"
    if notes_file.exists():
        return

    rc_dir.mkdir(parents=True, exist_ok=True)
    # Release notes generation logic would go here (simplified for now)
    notes_file.write_text(f"# Release Notes: {release_id}\n\nGenerated by orchestrator.\n")


def check_code_review_gate(team_release_ids: Dict[str, str], log: List[Any], repo_root: Path) -> None:
    """Verify code review gate for each release being pushed."""
    cr_outbox = repo_root / "sessions" / "agent-code-review" / "outbox"
    if not cr_outbox.exists():
        return

    for team_id, release_id in team_release_ids.items():
        feature_ids: List[str] = []
        for fmd in (repo_root / "features").glob("*/feature.md"):
            try:
                txt = fmd.read_text(encoding="utf-8", errors="ignore")
                m = re.search(r"[-\s]*[Rr]elease:\s*\n?(20[0-9]+-[^\s\n]+)", txt)
                if m and m.group(1).strip() == release_id:
                    feature_ids.append(fmd.parent.name)
            except OSError:
                pass

        done_statuses = {"done", "approved", "approve"}
        review_done = False
        for f in cr_outbox.glob("*.md"):
            if release_id not in f.name and not any(fid in f.name for fid in feature_ids):
                continue
            try:
                content = f.read_text(encoding="utf-8", errors="ignore")
                m = re.search(r"^-\s*Status:\s*(\S+)", content, re.MULTILINE | re.IGNORECASE)
                if m and m.group(1).lower() in done_statuses:
                    review_done = True
                    break
                if "verdict: approve" in content.lower() or "verdict: approved" in content.lower():
                    review_done = True
                    break
            except OSError:
                pass

        if review_done:
            log.append({"step": "code_review_gate", "release": release_id, "status": "verified"})
            continue

        log.append({
            "step": "code_review_gate",
            "release": release_id,
            "status": "missing",
            "action": "ceo_escalation_dispatched",
        })
        print(f"CODE-REVIEW-GATE: {release_id} — no completed review found, dispatching CEO escalation")

        today = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
        ceo_inbox = repo_root / "sessions" / "ceo-copilot-2" / "inbox"
        item_dir = ceo_inbox / f"{today}-code-review-gate-{re.sub(r'[^A-Za-z0-9-]', '-', release_id)[:60]}"
        if not item_dir.exists():
            item_dir.mkdir(parents=True, exist_ok=True)
            (item_dir / "roi.txt").write_text("85\n")
            feat_list = "\n".join(f"- `{fid}`" for fid in sorted(feature_ids)) or "- (none found)"
            (item_dir / "command.md").write_text(
                f"# Code Review Gate — Manual Verification Required\n\n"
                f"**Release:** `{release_id}`\n"
                f"**Triggered:** Coordinated push fired without a verified `agent-code-review` "
                f"completion for this release.\n\n"
                f"## Features shipping without automated code review:\n{feat_list}\n\n"
                f"## Action required\n"
                f"1. Review the diff for the features above: `git log --oneline --name-only -20`\n"
                f"2. Verify no regressions, security issues, or unreviewed logic changes.\n"
                f"3. Write verdict to `sessions/agent-code-review/outbox/{today}-manual-cr-{release_id[:40]}.md`:\n"
                f"   ```\n   - Status: done\n   - Verdict: APPROVE / REJECT\n   ```\n"
                f"4. Archive this inbox item.\n"
            )


def run_coordinated_push_step(log: List[Any], repo_root: Path) -> None:
    """Auto-deploy when one or more coordinated team PMs have signed off their release."""
    release_control = _release_cycle_control_state()
    if not bool(release_control.get("enabled", True)):
        log.append({
            "step": "coordinated_push",
            "status": "paused",
            "state_file": release_control.get("state_file"),
            "reason": release_control.get("reason"),
        })
        return

    teams_path = repo_root / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        log.append({"step": "coordinated_push", "error": "could not read product-teams.json"})
        return

    required: List[Dict[str, Any]] = []
    for team in teams_data.get("teams", []):
        if not (team.get("active") and team.get("coordinated_release_default")):
            continue
        pm_agent = (team.get("pm_agent") or "").strip()
        if pm_agent:
            required.append({
                "team_id": team.get("id", ""),
                "pm_agent": pm_agent,
                "qa_agent": team.get("qa_agent", ""),
            })

    if not required:
        return

    active_dir = repo_root / "tmp" / "release-cycle-active"

    team_release_ids: Dict[str, str] = {}
    team_ready: Dict[str, bool] = {}

    for entry in required:
        team_id = entry["team_id"]
        pm_agent = entry["pm_agent"]
        release_id_file = active_dir / f"{team_id}.release_id"
        if not release_id_file.exists():
            team_ready[team_id] = False
            continue
        rid = release_id_file.read_text(encoding="utf-8").strip()
        if not rid:
            team_ready[team_id] = False
            continue
        team_release_ids[team_id] = rid
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        signoff_file = repo_root / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
        team_ready[team_id] = signoff_file.exists()

    any_ready = any(team_ready.get(e["team_id"], False) for e in required)
    if not any_ready:
        not_ready = [e["team_id"] for e in required if not team_ready.get(e["team_id"], False)]
        log.append({"step": "coordinated_push", "status": "waiting", "not_ready": not_ready,
                    "team_releases": team_release_ids})
        return

    signed_teams = [e["team_id"] for e in required if team_ready.get(e["team_id"], False)]
    waiting_teams = [e["team_id"] for e in required if not team_ready.get(e["team_id"], False)]

    combined_key = "__".join(
        re.sub(r"[^A-Za-z0-9._-]", "-", team_release_ids[e["team_id"]])
        for e in sorted(required, key=lambda x: x["team_id"])
    )[:120]

    pushed_dir = repo_root / "tmp" / "auto-push-dispatched"
    pushed_dir.mkdir(parents=True, exist_ok=True)
    marker = pushed_dir / f"{combined_key}.pushed"

    if marker.exists():
        log.append({"step": "coordinated_push", "status": "already_pushed", "marker": combined_key})
        return

    check_code_review_gate(team_release_ids, log, repo_root)

    marker.write_text(datetime.now(timezone.utc).isoformat() + "\n")

    canonical_release_id = sorted(team_release_ids.values())[0]
    canonical_slug = re.sub(r"[^A-Za-z0-9._-]", "-", canonical_release_id)[:80]

    write_release_notes(canonical_release_id, canonical_slug, required, repo_root)

    rc, out = _run(
        ["gh", "workflow", "run", "deploy.yml",
         "--repo", "keithaumiller/forseti.life", "--ref", "main"],
        timeout=60,
    )
    print(f"COORDINATED-PUSH: {combined_key} deploy rc={rc} (signed={signed_teams} waiting={waiting_teams})")

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    item_id = f"{today}-post-push-{canonical_slug}"
    inbox_dir = repo_root / "sessions" / "pm-forseti" / "inbox" / item_id
    outbox_file = repo_root / "sessions" / "pm-forseti" / "outbox" / f"{item_id}.md"
    if not inbox_dir.exists() and not outbox_file.exists():
        inbox_dir.mkdir(parents=True, exist_ok=True)
        (inbox_dir / "roi.txt").write_text("85\n")
        all_ids_text = "\n".join(f"  - {tid}: `{rid}`" for tid, rid in sorted(team_release_ids.items()))
        (inbox_dir / "command.md").write_text(
            f"# Post-push steps: coordinated release\n\n"
            f"The coordinated release deploy was triggered automatically.\n\n"
            f"## Releases shipped\n{all_ids_text}\n\n"
            "## 1. Wait for deploy workflow to finish\n"
            "```bash\ngh run list --repo keithaumiller/forseti.life --workflow deploy.yml --limit 3\n```\n\n"
            "## 2. Import config on production\n"
            "```bash\ncd /var/www/html/forseti && vendor/bin/drush config:import -y && vendor/bin/drush cr\n```\n\n"
            "## 3. Gate R5 — post-release production QA\n"
            "Trigger a production audit for each product (requires ALLOW_PROD_QA=1):\n"
            "```bash\nALLOW_PROD_QA=1 bash scripts/site-full-audit.py forseti\n```\n"
            "Record clean/unclean signal in your outbox.\n\n"
            f"Canonical release id: `{canonical_release_id}`\n"
        )

    for entry in required:
        team_id = entry["team_id"]
        pm_agent = entry["pm_agent"]
        rid = team_release_ids.get(team_id, "")
        if not rid:
            continue
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        signoff_path = repo_root / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
        if not signoff_path.exists():
            signoff_path.parent.mkdir(parents=True, exist_ok=True)
            signoff_path.write_text(
                f"# Release Signoff: {rid}\n\n"
                f"- Status: approved\n"
                f"- Signed by: orchestrator (coordinated push {combined_key})\n"
                f"- Timestamp: {datetime.now(timezone.utc).isoformat()}\n\n"
                f"This release was shipped as part of a coordinated push.\n"
            )
            print(f"COORDINATED-PUSH: wrote signoff {rid} for {team_id}/{pm_agent}")

    today_notif = datetime.now(timezone.utc).strftime("%Y%m%d")
    push_notif_id = f"{today_notif}-push-triggered-{canonical_slug}"
    push_notif_inbox = repo_root / "sessions" / "pm-forseti" / "inbox" / push_notif_id
    push_notif_outbox = repo_root / "sessions" / "pm-forseti" / "outbox" / f"{push_notif_id}.md"
    if not push_notif_inbox.exists() and not push_notif_outbox.exists():
        push_notif_inbox.mkdir(parents=True, exist_ok=True)
        (push_notif_inbox / "roi.txt").write_text("50\n")
        releases_shipped = "\n".join(f"  - {tid}: `{rid}`" for tid, rid in sorted(team_release_ids.items()))
        (push_notif_inbox / "README.md").write_text(
            f"# Push Triggered: Coordinated Release\n\n"
            f"- Timestamp: {datetime.now(timezone.utc).isoformat()}\n"
            f"- Status: push triggered\n\n"
            f"## Releases Shipped\n{releases_shipped}\n\n"
            f"## Status\n"
            f"- Signed teams: {', '.join(sorted(signed_teams))}\n"
            f"- Waiting teams: {', '.join(sorted(waiting_teams))}\n"
            f"- GitHub deploy workflow was triggered (rc={rc})\n\n"
            f"See inbox item `{today_notif}-post-push-{canonical_slug}` for post-release steps.\n"
        )
        print(f"COORDINATED-PUSH: notified pm-forseti that push was triggered for {canonical_slug}")

    log.append({
        "step": "coordinated_push",
        "status": "pushed",
        "marker": combined_key,
        "team_releases": team_release_ids,
        "signed_teams": signed_teams,
        "waiting_teams": waiting_teams,
        "deploy_rc": rc,
    })
    _emit_event("coordinated-push-done")
