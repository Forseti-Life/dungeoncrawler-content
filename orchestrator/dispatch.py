"""Release dispatch orchestration: signoff reminders, gate2 approvals, close triggers.

These functions run during _health_check_step() to coordinate release workflow:
  - Signoff reminders for lagging PMs
  - Proactive awaiting-signoff alerts
  - Gate 2 QA ready alerts (detection-only)
  - Release close readiness detection (detection-only)
"""

from __future__ import annotations

import hashlib as _hashlib
import json as _json
import re
from datetime import datetime as _dt, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional

# These will be imported from run.py context
REPO_ROOT = None  # Injected by caller


def set_repo_root(root: Path) -> None:
    """Inject REPO_ROOT path (called from run.py)."""
    global REPO_ROOT
    REPO_ROOT = root


def _now_ts() -> int:
    """Get current Unix timestamp."""
    import time
    return int(time.time())


def _safe_int(s: Any, default: int = 0) -> int:
    """Safely convert to int."""
    try:
        return int(s)
    except Exception:
        return default


def _cooldown_ok(state_file: Path, seconds: int) -> bool:
    """Check if cooldown threshold has passed."""
    last = _safe_int(state_file.read_text(encoding="utf-8").strip() if state_file.exists() else "0", 0)
    return (_now_ts() - last) >= seconds


def _mark_now(state_file: Path) -> None:
    """Mark current timestamp in state file."""
    state_file.parent.mkdir(parents=True, exist_ok=True)
    state_file.write_text(str(_now_ts()), encoding="utf-8")


def _count_site_features_in_progress(site_keyword: str) -> int:
    """Count in_progress features for a site."""
    count = 0
    features_dir = REPO_ROOT / "features"
    if features_dir.exists():
        for feature_d in features_dir.iterdir():
            if not feature_d.is_dir():
                continue
            feature_file = feature_d / "feature.md"
            if not feature_file.exists():
                continue
            try:
                text = feature_file.read_text(encoding="utf-8", errors="ignore")
                if f"Site: {site_keyword}" in text and "Status: in_progress" in text:
                    count += 1
            except Exception:
                pass
    return count


def _count_site_features_for_release(site_keyword: str, release_id: str) -> int:
    """Count in_progress features for a site and release."""
    count = 0
    features_dir = REPO_ROOT / "features"
    if features_dir.exists():
        for feature_d in features_dir.iterdir():
            if not feature_d.is_dir():
                continue
            feature_file = feature_d / "feature.md"
            if not feature_file.exists():
                continue
            try:
                text = feature_file.read_text(encoding="utf-8", errors="ignore")
                if f"Site: {site_keyword}" in text and f"Release: {release_id}" in text and "Status: in_progress" in text:
                    count += 1
            except Exception:
                pass
    return count


# ── Dispatch constants ───────────────────────────────────────────────────────

_SIGNOFF_REMINDER_STATE = None  # Injected by run.py
_SIGNOFF_REMINDER_COOLDOWN = 3600  # 1 hour between reminders per release

_PROACTIVE_SIGNOFF_STATE = None  # Injected by run.py
_PROACTIVE_SIGNOFF_COOLDOWN = 900  # 15 min between awaiting-signoff pings per release

_RELEASE_CLOSE_CAP        = 10     # features per site → trigger close
_RELEASE_CLOSE_AGE_HOURS  = 24     # hours since started_at → trigger close
_RELEASE_CLOSE_COOLDOWN   = 3600   # 1h between close dispatches per release
_RELEASE_CLOSE_STATE_DIR  = None  # Injected by run.py

_TEAM_WEBSITE_PREFIX: Dict[str, str] = {
    "forseti":        "forseti-life",
    "dungeoncrawler": "dungeoncrawler",
}


def _release_enabled_team_map() -> Dict[str, Dict[str, Any]]:
    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return {}

    team_map: Dict[str, Dict[str, Any]] = {}
    for team in teams_data.get("teams", []):
        team_id = (team.get("id") or "").strip()
        if not team_id:
            continue
        if not (team.get("active") and team.get("release_preflight_enabled")):
            continue
        deps = []
        for dep in team.get("release_dependencies") or []:
            dep_id = str(dep or "").strip()
            if dep_id and dep_id != team_id and dep_id not in deps:
                deps.append(dep_id)
        team_map[team_id] = {
            "id": team_id,
            "label": (team.get("label") or "").strip(),
            "site": (team.get("site") or "").strip(),
            "pm": (team.get("pm_agent") or "").strip(),
            "dev": (team.get("dev_agent") or "").strip(),
            "qa": (team.get("qa_agent") or "").strip(),
            "deps": deps,
        }
    return team_map


def _unrouted_code_review_findings(release_id: str) -> List[Dict[str, str]]:
    if REPO_ROOT is None or not release_id:
        return []
    import sys

    lib_dir = REPO_ROOT / "scripts" / "lib"
    if not lib_dir.exists():
        lib_dir = Path(__file__).resolve().parents[1] / "scripts" / "lib"
    if str(lib_dir) not in sys.path:
        sys.path.insert(0, str(lib_dir))
    try:
        from code_review_gate import unresolved_medium_plus_findings  # type: ignore
    except Exception:
        return []
    return unresolved_medium_plus_findings(REPO_ROOT, release_id)


def _queue_code_review_followup(team: Dict[str, Any], release_id: str, findings: List[Dict[str, str]]) -> None:
    pm_id = str(team.get("pm") or "").strip()
    team_id = str(team.get("id") or "").strip()
    team_label = str(team.get("label") or team_id).strip()
    if REPO_ROOT is None or not pm_id or not team_id or not findings:
        return
    slug = re.sub(r"[^A-Za-z0-9._-]", "-", release_id)[:80]
    item_id = f"{_dt.now(timezone.utc).strftime('%Y%m%d')}-code-review-followup-{slug}"
    item_dir = REPO_ROOT / "sessions" / pm_id / "inbox" / item_id
    if item_dir.exists():
        return
    item_dir.mkdir(parents=True, exist_ok=True)
    source_outbox = str(findings[0].get("source_outbox") or "").strip()
    findings_md = "\n".join(
        f"- `{item['id']}` ({item['severity']}) from `{item['source_outbox']}`" for item in findings
    )
    (item_dir / "command.md").write_text(
        f"- Flow id: release_shipping_flow\n"
        f"- Flow run id: {release_id}\n"
        f"- Flow node: PM Code Review Triage\n"
        f"- Flow owner seat: {pm_id}\n"
        f"- Flow previous node: Release Code Review\n"
        + (f"- Flow source outbox: {source_outbox}\n" if source_outbox else "")
        + f"- Product team id: {team_id}\n"
        f"- Product team label: {team_label}\n"
        f"- Available flow outcomes: Route fixes to Dev | Risk accepted / all findings resolved\n\n"
        f"# Flow handoff: release_shipping_flow / PM Code Review Triage\n\n"
        f"MEDIUM+ code-review findings exist for `{release_id}` but no matching dev routing or "
        f"risk-acceptance artifact was found, so Gate 1b is still open.\n\n"
        f"## Findings needing action\n{findings_md}\n\n"
        f"## Required action\n"
        f"1. Read the release code-review outbox for the findings above.\n"
        f"2. For each MEDIUM+ finding, either:\n"
        f"   - route a fix to the owning dev seat as a `cr-finding` inbox item, or\n"
        f"   - record a risk acceptance in `sessions/{pm_id}/artifacts/risk-acceptances/`.\n"
        f"3. Include one or more exact `- Flow outcome:` lines in your outbox:\n"
        f"   - `- Flow outcome: Route fixes to Dev`\n"
        f"   - `- Flow outcome: Risk accepted / all findings resolved`\n",
        encoding="utf-8",
    )
    (item_dir / "roi.txt").write_text("220\n", encoding="utf-8")


def _has_pending_signoff_reminder(pm_id: str, release_id: str) -> bool:
    if REPO_ROOT is None:
        return False
    inbox = REPO_ROOT / "sessions" / pm_id / "inbox"
    if not inbox.exists():
        return False
    slug = re.sub(r"[^A-Za-z0-9._-]", "-", release_id)[:80]
    for item_dir in inbox.iterdir():
        if item_dir.is_dir() and "signoff-reminder" in item_dir.name and slug in item_dir.name:
            return True
    return False


def _signoff_instruction_text(
    *,
    pm_id: str,
    command: str,
    artifact_path: str,
    status_release_id: str,
    extra_context: str,
) -> str:
    return (
        "## Action required\n"
        f"{extra_context}\n\n"
        "Record the signoff in repo state by running:\n"
        f"`{command}`\n\n"
        "The signoff artifact is the source of truth; outbox prose is not a substitute.\n\n"
        "## Acceptance criteria\n"
        f"- `{artifact_path}` exists\n"
        f"- `bash scripts/release-signoff-status.sh {status_release_id}` reflects your PM signoff\n"
        "- All open blockers for your site are resolved or explicitly deferred\n"
    )


# ── Dispatch functions ───────────────────────────────────────────────────────

def _dispatch_signoff_reminders() -> None:
    """Auto-create signoff-reminder inbox items for PMs lagging on a release.

    When one or more PMs on a coordinated release have signed off but at least
    one has not, and the gap has been open for > 30 minutes, route a
    signoff-reminder directly to the unsigned PM's inbox.  Cooldown-gated per
    release ID to avoid spam.
    """
    import json as _json

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    state_dir = _SIGNOFF_REMINDER_STATE.parent
    state_dir.mkdir(parents=True, exist_ok=True)

    team_map = _release_enabled_team_map()
    if not team_map:
        return

    for rid_file in active_dir.glob("*.release_id"):
        team_id = rid_file.name.replace(".release_id", "")
        team = team_map.get(team_id)
        if not team:
            continue
        rid = rid_file.read_text(encoding="utf-8").strip()
        if not rid:
            continue
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        dep_ids = [dep for dep in team["deps"] if dep in team_map]
        if not dep_ids:
            continue

        signed = []
        unsigned = []
        primary_signoff = REPO_ROOT / "sessions" / team["pm"] / "artifacts" / "release-signoffs" / f"{slug}.md"
        if primary_signoff.exists():
            signed.append(team)
        for dep_id in dep_ids:
            dep_release_file = active_dir / f"{dep_id}.release_id"
            dep_release_id = dep_release_file.read_text(encoding="utf-8").strip() if dep_release_file.exists() else ""
            dep_team = team_map[dep_id]
            if not dep_release_id:
                unsigned.append((dep_team, dep_release_id))
                continue
            dep_slug = re.sub(r"[^A-Za-z0-9._-]", "-", dep_release_id)[:80]
            dep_signoff = REPO_ROOT / "sessions" / dep_team["pm"] / "artifacts" / "release-signoffs" / f"{dep_slug}.md"
            if dep_signoff.exists():
                signed.append(dep_team)
            else:
                unsigned.append((dep_team, dep_release_id))

        if not signed or not unsigned:
            continue  # nobody signed yet, or all signed — nothing to remind

        # Cooldown per release
        state_key = state_dir / f"signoff_reminder_{slug}"
        last = _safe_int(state_key.read_text(encoding="utf-8").strip() if state_key.exists() else "0", 0)
        if (_now_ts() - last) < _SIGNOFF_REMINDER_COOLDOWN:
            continue

        # Dispatch reminder to each unsigned PM
        for t, dep_release_id in unsigned:
            pm_id = t["pm"]
            if _has_pending_signoff_reminder(pm_id, rid):
                continue
            item_id = f"{_dt.now(timezone.utc).strftime('%Y%m%d')}-signoff-reminder-{slug}"
            item_dir = REPO_ROOT / "sessions" / pm_id / "inbox" / item_id
            if item_dir.exists():
                continue  # already dispatched this cycle
            item_dir.mkdir(parents=True, exist_ok=True)
            signed_names = ", ".join(s["pm"] for s in signed)
            dep_slug = re.sub(r"[^A-Za-z0-9._-]", "-", dep_release_id)[:80] if dep_release_id else ""
            command = f"bash scripts/release-signoff.sh {dep_id if (dep_id := t['id']) else team_id} {dep_release_id}".strip()
            artifact_path = (
                f"sessions/{pm_id}/artifacts/release-signoffs/{dep_slug}.md"
                if dep_slug else
                f"sessions/{pm_id}/artifacts/release-signoffs/<your-active-release-id>.md"
            )
            (item_dir / "README.md").write_text(
                f"# Dependency signoff reminder: {rid}\n\n"
                f"- Agent: {pm_id}\n"
                f"- Blocking release: {rid}\n"
                f"- Required signoff release: {dep_release_id or 'unknown'}\n"
                f"- Status: pending\n"
                f"- Created: {_dt.now(timezone.utc).isoformat()}\n\n"
                + _signoff_instruction_text(
                    pm_id=pm_id,
                    command=command,
                    artifact_path=artifact_path,
                    status_release_id=rid,
                    extra_context=(
                        f"The following dependency PMs have already signed off for `{rid}`: {signed_names}.\n"
                        f"Your dependency release signoff `{dep_release_id or '<your-active-release-id>'}` is still required before `{team_id}` can ship."
                    ),
                ),
                encoding="utf-8",
            )
            (item_dir / "roi.txt").write_text("500", encoding="utf-8")
            print(f"SIGNOFF-REMINDER: dispatched to {pm_id} for dependency cohort {rid}")

        state_key.write_text(str(_now_ts()), encoding="utf-8")


def _dispatch_proactive_awaiting_signoff() -> None:
    """Auto-create awaiting-signoff inbox items when a release is ready but unsigned.

    When a release has NO open blockers and NO signoffs yet, route an informational
    item to the PM's inbox to prompt them to review and sign off. This is proactive
    (vs reactive reminder when others have signed).
    
    Called from release_cycle_step only when cycle is active (no signoff yet).
    """
    import json as _json

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    team_map = _release_enabled_team_map()
    if not team_map:
        return

    for rid_file in active_dir.glob("*.release_id"):
        team_id = rid_file.name.replace(".release_id", "")
        team = team_map.get(team_id)
        if not team:
            continue
        rid = rid_file.read_text(encoding="utf-8").strip()
        if not rid:
            continue
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        pm_id = team["pm"]
        if not pm_id:
            continue
        signoff_path = REPO_ROOT / "sessions" / pm_id / "artifacts" / "release-signoffs" / f"{slug}.md"
        if signoff_path.exists():
            continue

        unresolved = _unrouted_code_review_findings(rid)
        if unresolved:
            _queue_code_review_followup(team, rid, unresolved)
            continue

        item_id = f"{_dt.now(timezone.utc).strftime('%Y%m%d')}-awaiting-signoff-{slug}"
        item_dir = REPO_ROOT / "sessions" / pm_id / "inbox" / item_id
        if item_dir.exists():
            continue
        item_dir.mkdir(parents=True, exist_ok=True)
        command = f"bash scripts/release-signoff.sh {team_id} {rid}"
        artifact_path = f"sessions/{pm_id}/artifacts/release-signoffs/{slug}.md"
        (item_dir / "README.md").write_text(
            f"# Release ready for signoff: {rid}\n\n"
            f"- Agent: {pm_id}\n"
            f"- Release: {rid}\n"
            f"- Status: pending\n"
            f"- Created: {_dt.now(timezone.utc).isoformat()}\n\n"
            + _signoff_instruction_text(
                pm_id=pm_id,
                command=command,
                artifact_path=artifact_path,
                status_release_id=rid,
                extra_context="Your release cycle is ready for signoff.",
            ),
            encoding="utf-8",
        )
        (item_dir / "roi.txt").write_text("60", encoding="utf-8")
        print(f"AWAITING-SIGNOFF: dispatched to {pm_id} for release {rid}")


def _org_enabled() -> bool:
    ctrl = REPO_ROOT / "tmp" / "org-control.json"
    if not ctrl.exists():
        return True
    try:
        import json as _json
        return bool(_json.loads(ctrl.read_text(encoding="utf-8")).get("enabled", True))
    except Exception:
        return True


# Release close detection constants (no paths here - injected via setup())
_RELEASE_CLOSE_CAP        = 10     # features per site → trigger close
_RELEASE_CLOSE_AGE_HOURS  = 24     # hours since started_at → trigger close
_RELEASE_CLOSE_COOLDOWN   = 3600   # 1h between close dispatches per release

_SCOPE_ACTIVATE_GRACE_MINS = 15    # minutes before nudging PM to scope a new release
_SCOPE_ACTIVATE_COOLDOWN   = 3600  # 1h between nudges per release


def _count_site_features_in_progress(site_keyword: str) -> int:
    """Count features whose feature.md has Status: in_progress and Website: <site_keyword>."""
    count = 0
    for fm in (REPO_ROOT / "features").glob("*/feature.md"):
        try:
            text = fm.read_text(encoding="utf-8", errors="ignore")
            has_status = bool(re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE))
            has_site   = bool(re.search(rf"^-\s+Website:.*{re.escape(site_keyword)}", text, re.MULTILINE | re.IGNORECASE))
            if has_status and has_site:
                count += 1
        except Exception:
            pass
    return count


def _count_site_features_for_release(site_keyword: str, release_id: str) -> int:
    """Count features scoped to a specific release_id with Status: in_progress|done and Website: <site_keyword>.

    Only counts features that explicitly declare the matching Release: field.
    Features without a Release: field are excluded (they belong to an untracked release).
    """
    count = 0
    for fm in (REPO_ROOT / "features").glob("*/feature.md"):
        try:
            text = fm.read_text(encoding="utf-8", errors="ignore")
            has_status  = bool(re.search(r"^-\s+Status:\s*(in_progress|done)\s*$", text, re.MULTILINE | re.IGNORECASE))
            has_site    = bool(re.search(rf"^-\s+Website:.*{re.escape(site_keyword)}", text, re.MULTILINE | re.IGNORECASE))
            has_release = bool(re.search(
                rf"^-\s+Release:\s*(?:\n\s*)*{re.escape(release_id)}\s*$",
                text, re.MULTILINE | re.IGNORECASE,
            ))
            if has_status and has_site and has_release:
                count += 1
        except Exception:
            pass
    return count


_FEATURE_GAP_COOLDOWN    = 4 * 3600   # 4h between gap dispatches per feature

# Agent role prefixes that own implementation vs verification work
_IMPL_AGENT_PREFIX  = "dev-"
_QA_AGENT_PREFIX    = "qa-"


def _agent_inbox_has_feature(agent_id: str, feat_name: str) -> bool:
    """Return True if agent's inbox has any item whose name contains feat_name."""
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False
    return any(
        d.is_dir() and d.name != "_archived" and feat_name in d.name
        for d in inbox.iterdir()
    )


def _agent_outbox_has_feature(agent_id: str, feat_name: str) -> bool:
    """Return True if agent's outbox has any artifact whose stem contains feat_name."""
    outbox = REPO_ROOT / "sessions" / agent_id / "outbox"
    if not outbox.exists():
        return False
    return any(feat_name in f.stem for f in outbox.iterdir() if f.is_file())


def _agent_outbox_has_done_feature(agent_id: str, feat_name: str) -> bool:
    """Return True if agent's outbox has a COMPLETED (Status: done/approved) artifact
    whose stem contains feat_name.  Used by GAP-A guard to prevent re-dispatching
    work that was already finished (ISSUE-009 fix)."""
    outbox = REPO_ROOT / "sessions" / agent_id / "outbox"
    if not outbox.exists():
        return False
    done_statuses = {"done", "approved", "approve", "completed"}
    for f in outbox.iterdir():
        if not f.is_file() or feat_name not in f.stem:
            continue
        try:
            content = f.read_text(encoding="utf-8", errors="ignore")
            m = re.search(r"^-\s*Status:\s*(\S+)", content, re.MULTILINE | re.IGNORECASE)
            if m and m.group(1).lower() in done_statuses:
                return True
        except OSError:
            pass
    return False


def _dispatch_feature_gap_remediation() -> None:
    """DEPRECATED: Feature gaps should be prevented by enforcing Gate 1a.
    
    Gate 1a (Feature Scope) must create dev/QA inbox items as part of scoping ceremony.
    This function is no longer called and will be removed when Gate 1a enforcement is complete.
    """
    pass

def _dispatch_scope_activate_nudge() -> None:
    """DEPRECATED: Gate 1a enforcement removes the need for auto-nudge.
    
    Gate 1a is now mandatory — feature scoping must create work assignments immediately.
    This function is no longer called and will be removed when Gate 1a enforcement is complete.
    """
    pass

def _dispatch_gate2_auto_approve() -> None:
    """MODIFIED: Detect when Gate 2 is ready for QA approval (detection-only, no auto-approve).

    Previously auto-generated Gate 2 APPROVE files. Now only detects and alerts.
    
    Fires per team when ALL of the following are true:
      1. The team has an active release with >= 1 in-progress features.
      2. Every in-progress feature for that release has a corresponding suite-activate
         outbox file in sessions/<qa_agent>/outbox/ (name contains the feature id).
      3. No suite-activate inbox items remain (outside _archived/) in sessions/<qa_agent>/inbox/.
      4. No gate2-approve outbox file already exists referencing this release_id.

    CHANGE: Instead of auto-generating Gate 2 APPROVE, this now alerts QA that the release
    is ready for explicit approval/rejection decision. QA must explicitly file the approval.
    """
    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    for team in teams_data.get("teams", []):
        if not team.get("active"):
            continue

        team_id   = (team.get("id") or "").strip()
        qa_agent  = (team.get("qa_agent") or f"qa-{team_id}").strip()
        site_kw   = _TEAM_WEBSITE_PREFIX.get(team_id, team_id)

        release_id_file = active_dir / f"{team_id}.release_id"
        if not release_id_file.exists():
            continue
        release_id = release_id_file.read_text(encoding="utf-8").strip()
        if not release_id:
            continue

        qa_outbox = REPO_ROOT / "sessions" / qa_agent / "outbox"
        qa_inbox  = REPO_ROOT / "sessions" / qa_agent / "inbox"

        # Condition 4: skip if gate2-approve outbox already exists for this release
        if qa_outbox.exists():
            already_approved = any(
                "gate2-approve" in f.name and release_id in f.read_text(encoding="utf-8", errors="ignore")
                for f in qa_outbox.iterdir()
                if f.is_file() and "gate2-approve" in f.name
            )
            if already_approved:
                continue

        # Collect in-progress features for this release
        features: List[str] = []
        for fm in (REPO_ROOT / "features").glob("*/feature.md"):
            try:
                text = fm.read_text(encoding="utf-8", errors="ignore")
                if (re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE)
                        and re.search(rf"^-\s+Website:.*{re.escape(site_kw)}", text, re.MULTILINE | re.IGNORECASE)
                        and re.search(rf"^-\s+Release:\s*{re.escape(release_id)}\s*$", text, re.MULTILINE | re.IGNORECASE)):
                    features.append(fm.parent.name)
            except Exception:
                pass

        # Condition 1: must have at least 1 feature
        if not features:
            continue

        # Condition 2: every in-progress feature must have a suite-activate outbox entry
        if not qa_outbox.exists():
            continue
        outbox_files = [f.name for f in qa_outbox.iterdir() if f.is_file()]
        all_activated = all(
            any(feat_id in ob_name and "suite-activate" in ob_name for ob_name in outbox_files)
            for feat_id in features
        )
        if not all_activated:
            continue

        # Condition 3: no suite-activate inbox items remaining outside _archived
        if qa_inbox.exists():
            pending_activates = [
                d for d in qa_inbox.iterdir()
                if d.is_dir() and d.name != "_archived" and "suite-activate" in d.name
            ]
            if pending_activates:
                continue

        # All conditions met — ALERT that Gate 2 is ready for QA approval (detection-only)
        # QA must make the approval/rejection decision explicitly
        print(f"[gate2-ready-for-qa-decision] {release_id} — {len(features)} feature(s) ready for QA approval → {qa_agent}")
        # Emit event so release_cycle can track but does NOT auto-approve
        _emit_event("gate2-ready-for-qa-decision")


def _dispatch_release_close_triggers() -> None:
    """MODIFIED: Detect release-close conditions without auto-dispatching (detection-only).

    Previously dispatched release-close-now inbox items to PMs based on auto-triggers:
      - ≥ 10 features in_progress
      - ≥ 24h elapsed since release started

    NOW: Only detects conditions and alerts. PM must make explicit close decision.
    
    Rationale: Release close is PM's decision, not system-triggered. Time/count gates
    are heuristics, not rules. Restore PM authority by requiring explicit close request.
    """
    import json as _json

    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return

    teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    pm_map: Dict[str, str] = {
        (t.get("id") or "").strip(): (t.get("pm_agent") or "").strip()
        for t in teams_data.get("teams", [])
        if t.get("active") and (t.get("id") or "").strip() and (t.get("pm_agent") or "").strip()
    }

    now = _now_ts()

    for rid_file in active_dir.glob("*.release_id"):
        team_id = rid_file.stem
        rid = rid_file.read_text(encoding="utf-8").strip()
        if not rid:
            continue

        pm_id = pm_map.get(team_id, "")
        if not pm_id:
            continue

        # Check if already signed off (no need to dispatch)
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
        signoff_file = REPO_ROOT / "sessions" / pm_id / "artifacts" / "release-signoffs" / f"{slug}.md"
        if signoff_file.exists():
            continue  # already signed, nothing to do

        # Detect (but don't auto-trigger)
        site_kw = _TEAM_WEBSITE_PREFIX.get(team_id, team_id)
        feature_count = _count_site_features_for_release(site_kw, rid)

        # Check age
        started_file = active_dir / f"{team_id}.started_at"
        age_hours = 0
        if started_file.exists():
            try:
                from datetime import datetime as _dt
                started_str = started_file.read_text(encoding="utf-8").strip()
                started_str_clean = started_str.replace("+00:00", "").rstrip("Z")
                started_ts = int(_dt.fromisoformat(started_str_clean).replace(
                    tzinfo=timezone.utc).timestamp())
                age_hours = (now - started_ts) / 3600
            except Exception:
                pass

        # Alert (but don't dispatch auto-trigger)
        if feature_count >= _RELEASE_CLOSE_CAP or age_hours >= _RELEASE_CLOSE_AGE_HOURS:
            conditions = []
            if feature_count >= _RELEASE_CLOSE_CAP:
                conditions.append(f"{feature_count} features (cap: {_RELEASE_CLOSE_CAP})")
            if age_hours >= _RELEASE_CLOSE_AGE_HOURS:
                conditions.append(f"{age_hours:.1f}h old (threshold: {_RELEASE_CLOSE_AGE_HOURS}h)")
            print(f"[release-ready-to-close] {rid} — {', '.join(conditions)} → {pm_id} must decide")




def setup(repo_root: Path, signoff_reminder_state: Path, proactive_signoff_state: Path, release_close_state_dir: Path) -> None:
    """Initialize dispatch module with paths from run.py."""
    global REPO_ROOT, _SIGNOFF_REMINDER_STATE, _PROACTIVE_SIGNOFF_STATE, _RELEASE_CLOSE_STATE_DIR
    REPO_ROOT = repo_root
    _SIGNOFF_REMINDER_STATE = signoff_reminder_state
    _PROACTIVE_SIGNOFF_STATE = proactive_signoff_state
    _RELEASE_CLOSE_STATE_DIR = release_close_state_dir
