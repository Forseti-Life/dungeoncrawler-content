"""Health checks and inbox audit: stale locks, quarantine detection, shipping lag."""

from __future__ import annotations

import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List

import pathlib


def _now_ts() -> int:
    """Get current Unix timestamp (imported from run.py context)."""
    import time
    return int(time.time())


def _cooldown_ok(state_file: Path, seconds: int) -> bool:
    """Check if cooldown threshold has passed (imported from run.py context)."""
    def _safe_int(s: Any, default: int = 0) -> int:
        try:
            return int(s)
        except Exception:
            return default
    
    last = _safe_int(state_file.read_text(encoding="utf-8").strip() if state_file.exists() else "0", 0)
    return (_now_ts() - last) >= seconds


def _mark_now(state_file: Path) -> None:
    """Mark current timestamp in state file (imported from run.py context)."""
    state_file.parent.mkdir(parents=True, exist_ok=True)
    state_file.write_text(str(_now_ts()), encoding="utf-8")


def _run(cmd: List[str], *, timeout: int = 600) -> tuple[int, str]:
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


# ── Audit constants ───────────────────────────────────────────────────────────

_INBOX_AUDIT_COOLDOWN = 3600          # re-audit at most once per hour
_INWORK_STALE_SECS    = 7200          # .inwork lock is stale after 2h


# ── Inbox audit ───────────────────────────────────────────────────────────────

def audit_inbox_data_quality(repo_root: Path, inbox_audit_state: Path) -> Dict[str, Any]:
    """Scan every agent inbox for data-standard violations and auto-remediate.

    Checks performed every tick (subject to cooldown):
      1. Stale .inwork locks (>2h) → removed (unlock so item can be retried)
      2. Items missing README.md and command.md → inject minimal README.md
      3. READMEs missing '- Agent:' or '- Status:' fields → append the fields

    Returns a summary dict logged into the health_check audit trail.
    """
    if not _cooldown_ok(inbox_audit_state, _INBOX_AUDIT_COOLDOWN):
        return {}

    sessions_dir = repo_root / "sessions"
    if not sessions_dir.exists():
        return {}

    now = _now_ts()
    stats: Dict[str, Any] = {
        "stale_locks_cleared": [],
        "readmes_injected": [],
        "fields_patched": [],
    }

    for agent_dir in sorted(sessions_dir.iterdir()):
        if not agent_dir.is_dir():
            continue
        agent_id = agent_dir.name
        inbox = agent_dir / "inbox"
        if not inbox.exists():
            continue

        # ── 1. Clear stale .inwork locks ──────────────────────────────────────
        for lock in inbox.glob("**/*.inwork"):
            try:
                age = now - lock.stat().st_mtime
                if age > _INWORK_STALE_SECS:
                    lock.unlink()
                    stats["stale_locks_cleared"].append(f"{agent_id}/{lock.name}")
            except Exception:
                pass

        for item_dir in sorted(inbox.iterdir()):
            if item_dir.name == "_archived" or not item_dir.is_dir():
                continue

            has_readme  = (item_dir / "README.md").exists()
            has_command = (item_dir / "command.md").exists()

            # ── 2. Inject minimal README if neither exists ────────────────────
            if not has_readme and not has_command:
                roi_val = ""
                roi_f = item_dir / "roi.txt"
                if roi_f.exists():
                    roi_val = roi_f.read_text(encoding="utf-8", errors="ignore").strip()
                readme_path = item_dir / "README.md"
                readme_path.write_text(
                    f"# {item_dir.name}\n\n"
                    f"- Agent: {agent_id}\n"
                    f"- Status: pending\n"
                    + (f"- ROI: {roi_val}\n" if roi_val else ""),
                    encoding="utf-8",
                )
                stats["readmes_injected"].append(f"{agent_id}/{item_dir.name}")
                has_readme = True

            # ── 3. Patch missing Agent: / Status: fields ──────────────────────
            md_path = (item_dir / "README.md") if has_readme else (item_dir / "command.md")
            try:
                text = md_path.read_text(encoding="utf-8", errors="ignore")
                missing = []
                if not re.search(r"^-\s+Agent:", text, re.M):
                    missing.append(f"- Agent: {agent_id}")
                if not re.search(r"^-\s+Status:", text, re.M):
                    missing.append("- Status: pending")
                if missing:
                    md_path.write_text(text.rstrip() + "\n" + "\n".join(missing) + "\n",
                                       encoding="utf-8")
                    stats["fields_patched"].append(f"{agent_id}/{item_dir.name}")
            except Exception:
                pass

    total_issues = (len(stats["stale_locks_cleared"]) +
                    len(stats["readmes_injected"]) +
                    len(stats["fields_patched"]))
    if total_issues:
        print(
            f"INBOX-AUDIT: cleared {len(stats['stale_locks_cleared'])} locks, "
            f"injected {len(stats['readmes_injected'])} READMEs, "
            f"patched {len(stats['fields_patched'])} fields"
        )

    _mark_now(inbox_audit_state)
    return stats


# ── Process reaping ───────────────────────────────────────────────────────────

def reap_stale_copilot_processes() -> Dict[str, Any]:
    """Execute stale copilot process reaper and return stats."""
    rc, out = _run(
        [
            "python3",
            "scripts/reap_stale_copilot.py",
            "--apply",
            "--json",
            "--min-age-seconds",
            "7200",
        ],
        timeout=60,
    )
    if rc != 0:
        raise RuntimeError(f"reap_stale_copilot.py failed rc={rc}: {out}")
    import json as _json

    data = _json.loads(out or "{}")
    if not isinstance(data, dict):
        return {}
    return data


# ── Gating agent quarantine detection ──────────────────────────────────────────

_QUARANTINE_ESCALATE_COOLDOWN = 3600  # seconds between gating-agent quarantine escalations


def escalate_quarantined_gating_agents(repo_root: Path, quarantine_state: Path) -> None:
    """Detect when gating agents (PM, agent-code-review) are majority-quarantined
    for an active release and escalate to CEO inbox. (ISSUE-012 fix)

    Only fires once per cooldown window to avoid flooding the CEO inbox.
    """
    if not _cooldown_ok(quarantine_state, _QUARANTINE_ESCALATE_COOLDOWN):
        return

    teams_path = repo_root / "org-chart" / "products" / "product-teams.json"
    try:
        import json as _json2
        teams_data = _json2.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    active_dir = repo_root / "tmp" / "release-cycle-active"
    gating_failures: List[str] = []

    # Build list of gating agents for active releases
    gating_agents: List[tuple[str, str]] = []   # (agent_id, release_id)
    for team in teams_data.get("teams", []):
        if not team.get("active"):
            continue
        team_id = team.get("id", "")
        pm_agent = team.get("pm_agent", f"pm-{team_id}")
        rid_file = active_dir / f"{team_id}.release_id"
        if not rid_file.exists():
            continue
        release_id = rid_file.read_text(encoding="utf-8", errors="ignore").strip()
        if release_id:
            gating_agents.append((pm_agent, release_id))

    gating_agents.append(("agent-code-review", ""))  # always check code review

    # Collect feature IDs across all active releases for outbox matching
    all_release_ids = {rid for _, rid in gating_agents if rid}
    feature_ids: List[str] = []
    if all_release_ids:
        for fmd in (repo_root / "features").glob("*/feature.md"):
            try:
                txt = fmd.read_text(encoding="utf-8", errors="ignore")
                m = re.search(r"[-\s]*[Rr]elease:\s*\n?(20[0-9]+-[^\s\n]+)", txt)
                if m and m.group(1).strip() in all_release_ids:
                    feature_ids.append(fmd.parent.name)
            except OSError:
                pass

    quarantine_statuses = {"needs-info", "blocked"}

    for agent_id, release_id in gating_agents:
        outbox = repo_root / "sessions" / agent_id / "outbox"
        if not outbox.exists():
            continue
        # Find outbox files relevant to any active release
        relevant: List[pathlib.Path] = []
        for f in outbox.glob("*.md"):
            if (release_id and release_id in f.name) or any(fid in f.name for fid in feature_ids):
                relevant.append(f)
        if not relevant:
            continue
        quarantined = 0
        for f in relevant:
            try:
                content = f.read_text(encoding="utf-8", errors="ignore")
                m = re.search(r"^-\s*Status:\s*(\S+)", content, re.MULTILINE | re.IGNORECASE)
                if m and m.group(1).lower() in quarantine_statuses:
                    quarantined += 1
            except OSError:
                pass
        rate = quarantined / len(relevant)
        if rate >= 0.5 and quarantined > 0:
            gating_failures.append(
                f"{agent_id} ({quarantined}/{len(relevant)} = {rate:.0%} quarantined"
                + (f", release={release_id}" if release_id else "") + ")"
            )

    if not gating_failures:
        return

    _mark_now(quarantine_state)
    print(f"QUARANTINE-ESCALATE: gating agent failures detected: {', '.join(gating_failures)}")

    today = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    ceo_inbox = repo_root / "sessions" / "ceo-copilot-2" / "inbox"
    item_dir = ceo_inbox / f"{today}-gating-agent-quarantine-escalation"
    if item_dir.exists():
        return
    item_dir.mkdir(parents=True, exist_ok=True)
    (item_dir / "roi.txt").write_text("95\n")
    failures_md = "\n".join(f"- {f}" for f in gating_failures)
    (item_dir / "command.md").write_text(
        f"# Gating Agent Quarantine Escalation\n\n"
        f"**Detected:** {datetime.now(timezone.utc).isoformat()}\n"
        f"**Priority:** CRITICAL — release gates are bypassed when gating agents are quarantined\n\n"
        f"## Quarantined Gating Agents\n{failures_md}\n\n"
        f"## Impact\n"
        f"- PM quarantine: release signoff gate cannot fire automatically\n"
        f"- agent-code-review quarantine: code ships without automated review\n"
        f"- CEO must manually proxy all gating work (adds ~4-5h CEO load)\n\n"
        f"## Immediate Actions\n"
        f"1. Investigate quarantine root cause per agent (backend failure vs. bad inbox item)\n"
        f"2. Reset quarantine: update outbox Status from `needs-info` → `done` if work was already completed\n"
        f"3. Re-dispatch with tighter scope if item was genuinely incomplete\n"
        f"4. Check executor health: `bash scripts/hq-status.sh`\n\n"
        f"## Recovery command\n"
        f"```bash\nbash scripts/hq-blockers.sh\n```\n"
    )


# ── Shipping lag detection ─────────────────────────────────────────────────────

_SHIPPING_LAG_WARN_H   = 72    # hours: emit escalation if release dev-done but unshipped
_SHIPPING_LAG_ESCALATE_COOLDOWN = 14400  # 4h between repeated escalations


def escalate_shipping_lag(repo_root: Path, shipping_lag_state: Path) -> None:
    """Detect when an active release has been dev-complete (all features have done dev
    outbox entries) but has not been pushed to production for >72h. (ISSUE-010 fix)

    Dispatches a CEO escalation so the delay doesn't silently compound.
    Only fires once per cooldown window.
    """
    if not _cooldown_ok(shipping_lag_state, _SHIPPING_LAG_ESCALATE_COOLDOWN):
        return

    import json as _json3
    teams_path = repo_root / "org-chart" / "products" / "product-teams.json"
    try:
        teams_data = _json3.loads(teams_path.read_text(encoding="utf-8"))
    except Exception:
        return

    active_dir = repo_root / "tmp" / "release-cycle-active"
    now_ts = _now_ts()
    lag_releases: List[str] = []

    for team in teams_data.get("teams", []):
        if not team.get("active"):
            continue
        team_id  = team.get("id", "")
        dev_agent = team.get("dev_agent", f"dev-{team_id}")
        rid_file  = active_dir / f"{team_id}.release_id"
        if not rid_file.exists():
            continue
        release_id = rid_file.read_text(encoding="utf-8", errors="ignore").strip()
        if not release_id:
            continue

        # Collect features for this release
        feature_ids: List[str] = []
        for fmd in (repo_root / "features").glob("*/feature.md"):
            try:
                txt = fmd.read_text(encoding="utf-8", errors="ignore")
                m = re.search(r"[-\s]*[Rr]elease:\s*\n?(20[0-9]+-[^\s\n]+)", txt)
                if m and m.group(1).strip() == release_id:
                    feature_ids.append(fmd.parent.name)
            except OSError:
                pass

        if not feature_ids:
            continue

        # Check all features have done dev outbox
        dev_done_times: List[float] = []
        all_done = True
        for feat_id in feature_ids:
            outbox = repo_root / "sessions" / dev_agent / "outbox"
            done_times = []
            if outbox.exists():
                done_statuses = {"done", "approved", "approve", "completed"}
                for f in outbox.glob(f"*{feat_id}*.md"):
                    try:
                        content = f.read_text(encoding="utf-8", errors="ignore")
                        m_s = re.search(r"^-\s*Status:\s*(\S+)", content, re.MULTILINE | re.IGNORECASE)
                        if m_s and m_s.group(1).lower() in done_statuses:
                            done_times.append(f.stat().st_mtime)
                    except OSError:
                        pass
            if not done_times:
                all_done = False
                break
            dev_done_times.append(max(done_times))

        if not all_done or not dev_done_times:
            continue

        last_dev_done = max(dev_done_times)
        age_h = (now_ts - last_dev_done) / 3600.0
        if age_h >= _SHIPPING_LAG_WARN_H:
            lag_releases.append(f"{release_id} (dev-done {age_h:.1f}h ago, threshold {_SHIPPING_LAG_WARN_H}h)")

    if not lag_releases:
        return

    _mark_now(shipping_lag_state)
    print(f"SHIPPING-LAG: releases stuck post-dev-done: {', '.join(lag_releases)}")

    today = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    ceo_inbox = repo_root / "sessions" / "ceo-copilot-2" / "inbox"
    item_dir = ceo_inbox / f"{today}-shipping-lag-escalation"
    if item_dir.exists():
        return
    item_dir.mkdir(parents=True, exist_ok=True)
    (item_dir / "roi.txt").write_text("80\n")
    releases_md = "\n".join(f"- {r}" for r in lag_releases)
    (item_dir / "command.md").write_text(
        f"# Shipping Lag Escalation — Release(s) Dev-Complete but Unshipped\n\n"
        f"**Detected:** {datetime.now(timezone.utc).isoformat()}\n"
        f"**Threshold:** {_SHIPPING_LAG_WARN_H}h from dev-done to push\n\n"
        f"## Stalled Releases\n{releases_md}\n\n"
        f"## Impact\n"
        f"- Completed features are sitting unreleased — user value delayed\n"
        f"- Each additional day increases risk of merge conflicts and drift\n\n"
        f"## Possible Causes\n"
        f"1. PM signoff gate not advancing (check pm-<site> quarantine status)\n"
        f"2. QA Gate 2 not completed or blocked\n"
        f"3. Code review gate not cleared\n"
        f"4. Release cycle paused (`tmp/release-cycle-control/paused`)\n\n"
        f"## Action required\n"
        f"```bash\nbash scripts/hq-status.sh\nbash scripts/hq-blockers.sh\n```\n"
        f"Identify the blocking gate and clear it or authorize a bypass.\n"
    )
