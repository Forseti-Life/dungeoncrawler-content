#!/usr/bin/env python3
from __future__ import annotations

import argparse
import collections
import json
import subprocess
import time
import uuid
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
PRODUCT_TEAMS = ROOT / "org-chart/products/product-teams.json"
STATE_DIR = ROOT / "tmp/release-kpi-monitor"


@dataclass
class Team:
    team_id: str
    site: str
    dev_agent: str
    pm_agent: str
    qa_agent: str
    qa_artifacts_dir: Path


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Monitor release KPI stagnation and trigger investigation.")
    p.add_argument("--stagnation-seconds", type=int, default=1800)
    p.add_argument("--cooldown-seconds", type=int, default=900)
    p.add_argument("--followup-seconds", type=int, default=600)
    p.add_argument("--execution-stall-seconds", type=int, default=600)
    p.add_argument("--auto-remediate", action="store_true", help="Attempt bounded direct execution for execution-stalled dev agents.")
    p.add_argument("--auto-remediate-cooldown-seconds", type=int, default=300, help="Minimum seconds between auto-remediation attempts per team.")
    p.add_argument("--no-progress-seconds", type=int, default=600, help="If dev outbox has no updates for this long with open issues, analyze session transcript health.")
    p.add_argument("--conversation-window-seconds", type=int, default=900, help="How far back to inspect Copilot session events for transcript-level failures.")
    p.add_argument("--max-session-reset-failures", type=int, default=3, help="After this many failed session reset attempts, stop resetting and escalate.")
    p.add_argument("--handoff-gap-seconds", type=int, default=600, help="Detect dev-complete/open-issues idle handoff gap after this many seconds.")
    p.add_argument("--handoff-investigation-cooldown-seconds", type=int, default=900, help="Minimum seconds between dedicated handoff-gap full investigations.")
    p.add_argument("--json", action="store_true")
    return p.parse_args()


def load_teams() -> list[Team]:
    if not PRODUCT_TEAMS.exists():
        return []
    data = json.loads(PRODUCT_TEAMS.read_text(encoding="utf-8", errors="ignore"))
    teams = []
    for t in data.get("teams") or []:
        if not t.get("active", False):
            continue
        site_audit = t.get("site_audit") or {}
        if not site_audit.get("enabled", False):
            continue
        team_id = str(t.get("id") or "").strip()
        site = str(t.get("site") or "").strip()
        dev_agent = str(t.get("dev_agent") or "").strip()
        qa_agent = str(t.get("qa_agent") or "").strip()
        pm_agent = str(t.get("pm_agent") or "").strip()
        qa_dir = Path(str(site_audit.get("qa_artifacts_dir") or "").strip())
        if not qa_dir.is_absolute():
            qa_dir = ROOT / qa_dir
        if not (team_id and site and dev_agent and qa_agent):
            continue
        teams.append(Team(team_id=team_id, site=site, dev_agent=dev_agent, pm_agent=pm_agent, qa_agent=qa_agent, qa_artifacts_dir=qa_dir))
    return teams


def parse_findings_total(findings_path: Path) -> int:
    obj = json.loads(findings_path.read_text(encoding="utf-8", errors="ignore"))
    c = obj.get("counts") or {}
    return int(c.get("missing_assets_404s") or 0) + int(c.get("permission_violations") or 0) + int(c.get("failures") or 0)


def latest_run_info(team: Team) -> tuple[str, int] | None:
    latest = team.qa_artifacts_dir / "latest"
    if not latest.exists():
        return None
    try:
        real = latest.resolve(strict=True)
    except Exception:
        return None
    findings = real / "findings-summary.json"
    if not findings.exists():
        return None
    try:
        total = parse_findings_total(findings)
    except Exception:
        return None
    return real.name, total


def read_status_from_outbox(path: Path) -> str:
    try:
        text = path.read_text(encoding="utf-8", errors="ignore")
    except Exception:
        return ""
    for ln in text.splitlines()[:30]:
        if ln.lower().startswith("- status:"):
            return ln.split(":", 1)[1].strip().lower()
    return ""


def lock_has_live_agent_exec(lock_dir: Path, dev_agent: str) -> bool:
    pid_path = lock_dir / "pid"
    if not pid_path.exists():
        return False
    try:
        pid = int(pid_path.read_text(encoding="utf-8", errors="ignore").strip())
    except Exception:
        return False
    if pid <= 0:
        return False
    try:
        proc = subprocess.run(
            ["ps", "-p", str(pid), "-o", "args="],
            check=False,
            capture_output=True,
            text=True,
        )
    except Exception:
        return False
    args = (proc.stdout or "").strip()
    if not args:
        return False
    return "agent-exec-next.sh" in args and dev_agent in args


def dev_diag(team: Team) -> dict[str, Any]:
    inbox = ROOT / "sessions" / team.dev_agent / "inbox"
    outbox = ROOT / "sessions" / team.dev_agent / "outbox"

    inbox_count = 0
    qa_findings_count = 0
    active_exec_locks = 0
    if inbox.exists():
        for d in inbox.iterdir():
            if not d.is_dir():
                continue
            inbox_count += 1
            if "-qa-findings-" in d.name:
                qa_findings_count += 1
                if lock_has_live_agent_exec(d / ".exec-lock", team.dev_agent):
                    active_exec_locks += 1

    latest_status = ""
    latest_outbox = ""
    latest_mtime = -1.0
    if outbox.exists():
        for f in outbox.glob("*.md"):
            try:
                m = f.stat().st_mtime
            except Exception:
                continue
            if m > latest_mtime:
                latest_mtime = m
                latest_outbox = f.name
                latest_status = read_status_from_outbox(f)

    release_marker = ROOT / "tmp" / "release-cycle-active" / f"{team.team_id}.release_id"
    release_id = ""
    if release_marker.exists():
        release_id = release_marker.read_text(encoding="utf-8", errors="ignore").strip()

    return {
        "dev_inbox_count": inbox_count,
        "dev_findings_items": qa_findings_count,
        "dev_active_exec_locks": active_exec_locks,
        "dev_active_processing": active_exec_locks > 0,
        "dev_latest_outbox": latest_outbox,
        "dev_latest_status": latest_status,
        "dev_latest_outbox_mtime": int(latest_mtime) if latest_mtime > 0 else 0,
        "release_id": release_id,
    }


def load_state(path: Path, now: int) -> dict[str, Any]:
    if not path.exists():
        return {
            "last_run": "",
            "last_total": -1,
            "last_changed_epoch": now,
            "last_total_changed_epoch": now,
            "last_investigated_epoch": 0,
            "followup_due_epoch": 0,
            "last_followup_epoch": 0,
            "last_auto_remediate_epoch": 0,
            "last_session_analysis_epoch": 0,
            "session_reset_failures": 0,
            "last_session_reset_epoch": 0,
            "last_session_escalated_epoch": 0,
            "last_auto_requeue_epoch": 0,
            "last_handoff_recovery_epoch": 0,
            "last_handoff_escalated_epoch": 0,
            "last_handoff_investigated_epoch": 0,
        }
    try:
        obj = json.loads(path.read_text(encoding="utf-8", errors="ignore"))
        if not isinstance(obj, dict):
            raise ValueError("invalid")
        obj.setdefault("last_run", "")
        obj.setdefault("last_total", -1)
        obj.setdefault("last_changed_epoch", now)
        obj.setdefault("last_total_changed_epoch", int(obj.get("last_changed_epoch", now)))
        obj.setdefault("last_investigated_epoch", 0)
        obj.setdefault("followup_due_epoch", 0)
        obj.setdefault("last_followup_epoch", 0)
        obj.setdefault("last_auto_remediate_epoch", 0)
        obj.setdefault("last_session_analysis_epoch", 0)
        obj.setdefault("session_reset_failures", 0)
        obj.setdefault("last_session_reset_epoch", 0)
        obj.setdefault("last_session_escalated_epoch", 0)
        obj.setdefault("last_auto_requeue_epoch", 0)
        obj.setdefault("last_handoff_recovery_epoch", 0)
        obj.setdefault("last_handoff_escalated_epoch", 0)
        obj.setdefault("last_handoff_investigated_epoch", 0)
        return obj
    except Exception:
        return {
            "last_run": "",
            "last_total": -1,
            "last_changed_epoch": now,
            "last_total_changed_epoch": now,
            "last_investigated_epoch": 0,
            "followup_due_epoch": 0,
            "last_followup_epoch": 0,
            "last_auto_remediate_epoch": 0,
            "last_session_analysis_epoch": 0,
            "session_reset_failures": 0,
            "last_session_reset_epoch": 0,
            "last_session_escalated_epoch": 0,
            "last_auto_requeue_epoch": 0,
            "last_handoff_recovery_epoch": 0,
            "last_handoff_escalated_epoch": 0,
            "last_handoff_investigated_epoch": 0,
        }


def _slug(text: str) -> str:
    s = "".join(ch if ch.isalnum() or ch in "._-" else "-" for ch in (text or "").strip())
    s = s.strip("-")
    return (s or "site")[:40]


def _has_pending_findings_item(dev_agent: str, label_slug: str) -> bool:
    inbox_root = ROOT / "sessions" / dev_agent / "inbox"
    if not inbox_root.exists() or not inbox_root.is_dir():
        return False
    needle = f"-qa-findings-{label_slug}-"
    for p in inbox_root.iterdir():
        if p.is_dir() and needle in p.name:
            return True
    return False


def _outbox_has_blocked_for_run(dev_agent: str, run_id: str) -> bool:
    """Return True if any outbox file for this run_id contains 'Status: blocked'.

    When the dev agent has repeatedly written blocked outbox entries for the
    same QA run, re-queuing more dev inbox items is counterproductive. The
    correct action is to trigger a QA re-run, not another dev retry.
    """
    outbox_root = ROOT / "sessions" / dev_agent / "outbox"
    if not outbox_root.exists():
        return False
    for f in outbox_root.glob("*.md"):
        if run_id not in f.stem:
            continue
        try:
            content = f.read_text(encoding="utf-8", errors="ignore")
            if "status: blocked" in content.lower():
                return True
        except Exception:
            pass
    return False


def auto_requeue_findings_item(team: Team, run_id: str, total: int) -> tuple[bool, str]:
    try:
        latest = (team.qa_artifacts_dir / "latest").resolve(strict=True)
    except Exception:
        return False, "missing-latest-qa-artifacts"

    findings_md = latest / "findings-summary.md"
    findings_json = latest / "findings-summary.json"
    if not findings_json.exists():
        return False, "missing-findings-summary-json"

    label_slug = _slug(team.site)
    if _has_pending_findings_item(team.dev_agent, label_slug):
        return False, "pending-findings-item-exists"

    item_id = f"{run_id}-qa-findings-{label_slug}-{total}"
    inbox_dir = ROOT / "sessions" / team.dev_agent / "inbox" / item_id
    outbox_file = ROOT / "sessions" / team.dev_agent / "outbox" / f"{item_id}.md"
    if inbox_dir.exists():
        return False, "item-already-exists"
    if outbox_file.exists():
        # Dev already handled the original item. Before retrying, check if dev
        # has written a blocked outbox for this run — if so, more dev retries
        # will not help; a QA re-run is needed instead.
        if _outbox_has_blocked_for_run(team.dev_agent, run_id):
            return False, "dev-blocked-on-run-qa-rerun-needed"
        retry_item_id = f"{item_id}-retry-{int(time.time())}"
        retry_inbox_dir = ROOT / "sessions" / team.dev_agent / "inbox" / retry_item_id
        if retry_inbox_dir.exists():
            return False, "item-already-exists"
        item_id = retry_item_id
        inbox_dir = retry_inbox_dir

    try:
        inbox_dir.mkdir(parents=True, exist_ok=True)
        roi = max(4, min(9, total // 5 if total >= 5 else total))
        (inbox_dir / "roi.txt").write_text(f"{roi}\n", encoding="utf-8")
        cmd = (
            "- command: |\n"
            "    Dev review QA results and fix failed tests.\n\n"
            f"    - Product team: {team.team_id}\n"
            f"    - Site label: {team.site}\n"
            f"    - QA run: {run_id}\n"
            f"    - Findings summary: {findings_md.as_posix()}\n"
            f"    - Findings JSON: {findings_json.as_posix()}\n\n"
            "    Required actions:\n"
            "    1) Fix highest-impact failures first.\n"
            "    2) For each fix, notify QA immediately with explicit handoff marker.\n"
            "    3) Keep notes concise in outbox and include touched files/routes.\n"
        )
        (inbox_dir / "command.md").write_text(cmd, encoding="utf-8")
        return True, item_id
    except Exception:
        return False, "write-failed"


def _has_pending_or_done_item(agent_id: str, marker: str) -> bool:
    inbox_root = ROOT / "sessions" / agent_id / "inbox"
    outbox_root = ROOT / "sessions" / agent_id / "outbox"

    if inbox_root.exists() and inbox_root.is_dir():
        for p in inbox_root.iterdir():
            if p.is_dir() and marker in p.name:
                return True

    if outbox_root.exists() and outbox_root.is_dir():
        for p in outbox_root.glob("*.md"):
            if marker in p.stem:
                return True

    return False


def auto_queue_qa_rerun_item(team: Team, run_id: str, total: int) -> tuple[bool, str]:
    today = datetime.now().strftime("%Y%m%d")
    site_slug = _slug(team.site)
    marker = f"rerun-full-audit-{site_slug}-{run_id}"
    if _has_pending_or_done_item(team.qa_agent, marker):
        return False, "qa-rerun-item-exists"

    item_id = f"{today}-{marker}"
    inbox_dir = ROOT / "sessions" / team.qa_agent / "inbox" / item_id

    try:
        latest = (team.qa_artifacts_dir / "latest").resolve(strict=True)
    except Exception:
        return False, "missing-latest-qa-artifacts"

    findings_md = latest / "findings-summary.md"
    findings_json = latest / "findings-summary.json"

    try:
        inbox_dir.mkdir(parents=True, exist_ok=False)
        (inbox_dir / "roi.txt").write_text("8\n", encoding="utf-8")
        cmd = (
            "- command: |\n"
            "    Run full QA audit rerun for release handoff validation.\n\n"
            f"    - Product team: {team.team_id}\n"
            f"    - Site: {team.site}\n"
            f"    - Last run id: {run_id}\n"
            f"    - Open issues reported: {total}\n"
            f"    - Prior findings summary: {findings_md.as_posix()}\n"
            f"    - Prior findings JSON: {findings_json.as_posix()}\n\n"
            "    Required actions:\n"
            "    1) Re-run the full site audit for this team/site.\n"
            "    2) Publish updated findings summary JSON/MD artifacts.\n"
            "    3) If open issues remain, hand off concrete failing items to Dev/PM in outbox.\n"
            "    4) If open issues drop to zero, mark APPROVE with evidence.\n"
        )
        (inbox_dir / "command.md").write_text(cmd, encoding="utf-8")
        return True, item_id
    except Exception:
        return False, "qa-rerun-write-failed"


def queue_handoff_escalation(team: Team, run_id: str, total: int, diag: dict[str, Any], reason: str) -> bool:
    cmd_text = (
        f"Release handoff gap for {team.team_id} ({team.site}): open issues remain after Dev marked complete. "
        f"run={run_id}, open_issues={total}, dev_status={diag.get('dev_latest_status','')}, "
        f"dev_outbox={diag.get('dev_latest_outbox','')}, reason={reason}. "
        f"Action: PM must triage remaining failures and ensure QA rerun is queued/executed."
    )
    try:
        subprocess.run(
            [
                str(ROOT / "scripts" / "ceo-queue.sh"),
                team.team_id,
                "release-handoff-gap",
                cmd_text,
                team.pm_agent,
            ],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return True
    except Exception:
        return False


def queue_handoff_full_investigation(team: Team, run_id: str, total: int, stagnant_seconds: int, diag: dict[str, Any]) -> bool:
    cmd_text = (
        f"FULL INVESTIGATION REQUIRED: release handoff gap for {team.team_id} ({team.site}). "
        f"Dev reports complete but open issues remain and lane is idle. "
        f"run={run_id}, open_issues={total}, no_progress_min={stagnant_seconds // 60}, "
        f"dev_status={diag.get('dev_latest_status','')}, dev_outbox={diag.get('dev_latest_outbox','')}, "
        f"dev_inbox={diag.get('dev_inbox_count',0)}, findings_items={diag.get('dev_findings_items',0)}. "
        f"Action: launch PM+QA full triage now and restore active execution flow."
    )
    try:
        subprocess.run(
            [
                str(ROOT / "scripts" / "ceo-queue.sh"),
                team.team_id,
                "release-handoff-full-investigation",
                cmd_text,
                team.pm_agent,
            ],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return True
    except Exception:
        return False


def _session_file_for_agent(dev_agent: str) -> Path:
    return Path.home() / ".copilot" / "wrappers" / f"hq-{dev_agent}.session"


def _session_events_file(session_id: str) -> Path:
    return Path.home() / ".copilot" / "session-state" / session_id / "events.jsonl"


def _tail_lines(path: Path, max_lines: int = 2000) -> list[str]:
    try:
        with path.open("r", encoding="utf-8", errors="ignore") as f:
            return list(collections.deque(f, maxlen=max_lines))
    except Exception:
        return []


def analyze_conversation_health(dev_agent: str, now: int, window_seconds: int) -> dict[str, Any]:
    session_file = _session_file_for_agent(dev_agent)
    if not session_file.exists():
        return {
            "conversation_analysis_available": False,
            "conversation_unhealthy": False,
            "conversation_reason": "missing-session-file",
            "conversation_session_id": "",
            "conversation_recent_errors": 0,
            "conversation_recent_compaction_413": 0,
            "conversation_recent_tooluse_mismatch": 0,
        }

    session_id = session_file.read_text(encoding="utf-8", errors="ignore").strip()
    if not session_id:
        return {
            "conversation_analysis_available": False,
            "conversation_unhealthy": False,
            "conversation_reason": "empty-session-id",
            "conversation_session_id": "",
            "conversation_recent_errors": 0,
            "conversation_recent_compaction_413": 0,
            "conversation_recent_tooluse_mismatch": 0,
        }

    events_file = _session_events_file(session_id)
    if not events_file.exists():
        return {
            "conversation_analysis_available": False,
            "conversation_unhealthy": False,
            "conversation_reason": "missing-events",
            "conversation_session_id": session_id,
            "conversation_recent_errors": 0,
            "conversation_recent_compaction_413": 0,
            "conversation_recent_tooluse_mismatch": 0,
        }

    recent_errors = 0
    recent_compaction_413 = 0
    recent_tooluse_mismatch = 0
    cutoff = now - max(60, window_seconds)

    for line in _tail_lines(events_file, max_lines=2500):
        try:
            obj = json.loads(line)
        except Exception:
            continue

        ts_raw = str(obj.get("timestamp") or "")
        if not ts_raw:
            continue
        try:
            ts_epoch = int(datetime.fromisoformat(ts_raw.replace("Z", "+00:00")).timestamp())
        except Exception:
            continue
        if ts_epoch < cutoff:
            continue

        typ = str(obj.get("type") or "")
        data = obj.get("data") if isinstance(obj.get("data"), dict) else {}
        message = str(data.get("message") or data.get("error") or "")

        if typ == "session.error":
            recent_errors += 1
            lower = message.lower()
            if "unexpected `tool_use_id`" in lower or "tool_result" in lower:
                recent_tooluse_mismatch += 1
        elif typ == "session.compaction_complete":
            if not bool(data.get("success", True)) and "413" in message:
                recent_compaction_413 += 1

    unhealthy = recent_errors >= 3 or recent_compaction_413 >= 1 or recent_tooluse_mismatch >= 1
    reason = "ok"
    if unhealthy:
        if recent_tooluse_mismatch > 0:
            reason = "tool-use-id-mismatch"
        elif recent_compaction_413 > 0:
            reason = "compaction-413"
        else:
            reason = "session-errors"

    return {
        "conversation_analysis_available": True,
        "conversation_unhealthy": unhealthy,
        "conversation_reason": reason,
        "conversation_session_id": session_id,
        "conversation_recent_errors": recent_errors,
        "conversation_recent_compaction_413": recent_compaction_413,
        "conversation_recent_tooluse_mismatch": recent_tooluse_mismatch,
    }


def rotate_agent_session(dev_agent: str) -> bool:
    session_file = _session_file_for_agent(dev_agent)
    try:
        session_file.parent.mkdir(parents=True, exist_ok=True)
        old = ""
        if session_file.exists():
            old = session_file.read_text(encoding="utf-8", errors="ignore").strip()
        new_id = str(uuid.uuid4())
        session_file.write_text(new_id + "\n", encoding="utf-8")
        if old and old != new_id:
            old_dir = _session_events_file(old).parent
            if old_dir.exists():
                marker = old_dir / ".release-monitor-reset"
                marker.write_text(datetime.now().isoformat() + "\n", encoding="utf-8")
        return True
    except Exception:
        return False


def try_session_reset_and_reexec(team: Team) -> bool:
    if not rotate_agent_session(team.dev_agent):
        return False
    try:
        proc = subprocess.run(
            [str(ROOT / "scripts" / "agent-exec-next.sh"), team.dev_agent],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return proc.returncode == 0
    except Exception:
        return False


def queue_session_escalation(team: Team, run_id: str, total: int, diag: dict[str, Any], conversation: dict[str, Any], failures: int) -> bool:
    cmd_text = (
        f"Release monitor session reset failed {failures} times for {team.team_id} ({team.site}). "
        f"Auto-resets paused pending manual intervention. "
        f"run={run_id}, open_issues={total}, dev_status={diag.get('dev_latest_status','')}, "
        f"dev_outbox={diag.get('dev_latest_outbox','')}, conversation_reason={conversation.get('conversation_reason','')}, "
        f"recent_errors={conversation.get('conversation_recent_errors',0)}, "
        f"tooluse_mismatch={conversation.get('conversation_recent_tooluse_mismatch',0)}, "
        f"compaction_413={conversation.get('conversation_recent_compaction_413',0)}"
    )
    try:
        subprocess.run(
            [
                str(ROOT / "scripts" / "ceo-queue.sh"),
                team.team_id,
                "release-session-reset-escalation",
                cmd_text,
                team.pm_agent,
            ],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return True
    except Exception:
        return False


def try_auto_remediate(team: Team) -> bool:
    try:
        proc = subprocess.run(
            [str(ROOT / "scripts" / "agent-exec-next.sh"), team.dev_agent],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return proc.returncode == 0
    except Exception:
        return False


def try_agent_exec(agent_id: str) -> bool:
    try:
        proc = subprocess.run(
            [str(ROOT / "scripts" / "agent-exec-next.sh"), agent_id],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return proc.returncode == 0
    except Exception:
        return False


def read_latest_outbox_content(team: Team, diag: dict[str, Any], max_chars: int = 1500) -> str:
    """Read the latest dev outbox file content for escalation messages."""
    latest_outbox = str(diag.get("dev_latest_outbox", "")).strip()
    if not latest_outbox:
        return ""
    outbox_file = ROOT / "sessions" / team.dev_agent / "outbox" / latest_outbox
    try:
        content = outbox_file.read_text(encoding="utf-8", errors="ignore").strip()
        if len(content) > max_chars:
            content = content[:max_chars] + "\n...[truncated]"
        return content
    except Exception:
        return ""


def check_stale_inbox_items(sessions_root: Path, threshold_roi: int = 10, age_seconds: int = 86400) -> list[dict[str, Any]]:
    """Scan sessions/<agent>/inbox/ for high-ROI items with no outbox counterpart
    that are older than age_seconds.  Returns a list of dicts with keys:
      agent, item, roi, age_seconds, age_hours (rounded to 1 decimal).
    Only items whose roi.txt value >= threshold_roi are considered.
    An outbox counterpart is any file in sessions/<agent>/outbox/ whose stem
    starts with the inbox item folder name.
    """
    stale: list[dict[str, Any]] = []
    if not sessions_root.exists():
        return stale
    now = int(time.time())
    for agent_dir in sorted(sessions_root.iterdir()):
        inbox_dir = agent_dir / "inbox"
        outbox_dir = agent_dir / "outbox"
        if not inbox_dir.is_dir():
            continue
        outbox_stems: set[str] = set()
        if outbox_dir.is_dir():
            for f in outbox_dir.iterdir():
                if f.is_file():
                    outbox_stems.add(f.stem)
        for item_dir in sorted(inbox_dir.iterdir()):
            if not item_dir.is_dir():
                continue
            # Check for outbox counterpart (stem starts with item name)
            has_outbox = any(s.startswith(item_dir.name) for s in outbox_stems)
            if has_outbox:
                continue
            # Read roi.txt
            roi_file = item_dir / "roi.txt"
            if not roi_file.exists():
                continue
            try:
                roi = int(roi_file.read_text(encoding="utf-8").strip())
            except Exception:
                continue
            if roi < threshold_roi:
                continue
            # Check age using folder mtime
            try:
                mtime = int(item_dir.stat().st_mtime)
            except Exception:
                continue
            age = now - mtime
            if age < age_seconds:
                continue
            stale.append({
                "agent": agent_dir.name,
                "item": item_dir.name,
                "roi": roi,
                "age_seconds": age,
                "age_hours": round(age / 3600, 1),
            })
    return stale


def count_recent_executor_failures(window_seconds: int = 3600) -> tuple[int, list[str]]:
    """Count executor failure records written to tmp/executor-failures/ within the
    given window.  Returns (count, [agent_ids]).  A high count indicates a systemic
    executor problem (e.g. repeated validation failures across multiple agents) that
    the stagnation detector should surface as an operator alert, distinct from
    individual agent blocked/needs-info states.
    """
    failures_dir = ROOT / "tmp" / "executor-failures"
    if not failures_dir.exists():
        return 0, []
    now = int(time.time())
    cutoff = now - window_seconds
    count = 0
    agents: list[str] = []
    for f in failures_dir.glob("*.md"):
        try:
            if int(f.stat().st_mtime) >= cutoff:
                count += 1
                # File name format: <timestamp>-<agent-id>.md
                agent = "-".join(f.stem.split("-")[1:]) if "-" in f.stem else f.stem
                agents.append(agent)
        except Exception:
            pass
    return count, agents


def count_unanswered_ceo_alerts(run_id: str) -> int:
    """Count stagnation/investigation alerts for this run_id that were queued
    but have not yet been actioned (i.e. still sit in inbox/commands OR were
    processed but KPI hasn't moved since).

    We treat any alert file (commands/ or processed/) whose name contains
    'stagnation' or 'investigation' and whose content references run_id as a
    queued-but-unactioned signal when they outnumber what we expect.
    """
    count = 0
    for subdir in ("commands", "processed"):
        d = ROOT / "inbox" / subdir
        if not d.exists():
            continue
        for f in d.glob("*.md"):
            if "stagnation" not in f.name and "investigation" not in f.name:
                continue
            try:
                if run_id in f.read_text(encoding="utf-8", errors="ignore"):
                    count += 1
            except Exception:
                pass
    return count


def count_needs_escalated_items(run_id: str) -> int:
    """Count 'needs-escalated' inbox items anywhere in sessions/ that reference
    this run_id.  When this number grows, it means the escalation chain is
    looping without resolution.
    """
    count = 0
    sessions_root = ROOT / "sessions"
    if not sessions_root.exists():
        return 0
    # Only scan session inbox directories — avoid huge outbox scans.
    for agent_inbox in sessions_root.glob("*/inbox"):
        for item_dir in agent_inbox.iterdir():
            if "needs-escalated" not in item_dir.name and "needs" not in item_dir.name:
                continue
            if run_id not in item_dir.name:
                continue
            count += 1
    return count


def auto_investigate_and_fix(team: Team, run_id: str, total: int, diag: dict[str, Any]) -> str:
    """Autonomous investigation triggered when CEO alerts have piled up unanswered.

    Reads the dev outbox, counts escalation depth, and composes a targeted
    resolution prompt sent to ceo-queue.sh with specific action directives —
    not just a stagnation notice.  Returns a short status string.
    """
    outbox_excerpt = read_latest_outbox_content(team, diag, max_chars=2000)
    dev_status = diag.get("dev_latest_status", "unknown")
    unanswered = count_unanswered_ceo_alerts(run_id)
    escalation_depth = count_needs_escalated_items(run_id)

    directives = []
    if _outbox_has_blocked_for_run(team.dev_agent, run_id):
        directives.append(
            "Dev agent is blocked on this run. Do NOT re-queue dev. "
            "Trigger a QA re-run: bash scripts/site-audit-run.sh "
            + team.site
        )
    if escalation_depth >= 3:
        directives.append(
            f"Escalation chain depth={escalation_depth}. Clear stale needs-escalated "
            "inbox items for this run before re-queuing."
        )
    if not directives:
        directives.append(
            "Investigate why KPI is stagnant. Check dev outbox, run QA audit, "
            "apply any committed fixes."
        )

    cmd_text = (
        f"[AUTO-INVESTIGATION] Release KPI stagnation for {team.team_id} ({team.site}).\n"
        f"run_id={run_id}, open_issues={total}, dev_status={dev_status}, "
        f"unanswered_alerts={unanswered}, escalation_depth={escalation_depth}.\n\n"
        "Autonomous directives (execute in order):\n"
        + "\n".join(f"  {i+1}. {d}" for i, d in enumerate(directives))
    )
    if outbox_excerpt:
        cmd_text += f"\n\nDev outbox excerpt:\n{outbox_excerpt}"

    try:
        subprocess.run(
            [
                str(ROOT / "scripts" / "ceo-queue.sh"),
                f"{team.team_id}-auto-investigation",
                "auto-investigate-fix",
                cmd_text,
            ],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return f"auto-investigation-queued(unanswered={unanswered},depth={escalation_depth})"
    except Exception:
        return "auto-investigation-failed"


def queue_investigation(team: Team, run_id: str, total: int, stagnant_seconds: int, diag: dict[str, Any], outbox_excerpt: str = "") -> bool:
    cmd_text = (
        f"Release KPI stagnation investigation for {team.team_id} ({team.site}). "
        f"No KPI movement for {stagnant_seconds//60}m. "
        f"latest_run={run_id}, open_issues={total}, release_id={diag.get('release_id','')}, "
        f"dev_inbox={diag.get('dev_inbox_count',0)}, findings_items={diag.get('dev_findings_items',0)}, "
        f"dev_latest_status={diag.get('dev_latest_status','')}"
    )
    if outbox_excerpt:
        cmd_text += f"\n\nDev agent outbox ({diag.get('dev_latest_outbox','')}):\n{outbox_excerpt}"
    try:
        subprocess.run(
            [
                str(ROOT / "scripts" / "ceo-queue.sh"),
                team.team_id,
                "release-kpi-stagnation",
                cmd_text,
                team.pm_agent,
            ],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return True
    except Exception:
        return False


def queue_followup(team: Team, run_id: str, total: int, stagnant_seconds: int, diag: dict[str, Any]) -> bool:
    cmd_text = (
        f"Release KPI stagnation FOLLOW-UP for {team.team_id} ({team.site}). "
        f"Still no movement after {stagnant_seconds//60}m. "
        f"run={run_id}, open_issues={total}, release_id={diag.get('release_id','')}, "
        f"dev_inbox={diag.get('dev_inbox_count',0)}, findings_items={diag.get('dev_findings_items',0)}, "
        f"dev_latest_status={diag.get('dev_latest_status','')}"
    )
    try:
        subprocess.run(
            [
                str(ROOT / "scripts" / "ceo-queue.sh"),
                team.team_id,
                "release-kpi-stagnation-followup",
                cmd_text,
                team.pm_agent,
            ],
            cwd=str(ROOT),
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return True
    except Exception:
        return False


def main() -> int:
    args = parse_args()
    now = int(time.time())
    STATE_DIR.mkdir(parents=True, exist_ok=True)

    out_rows: list[dict[str, Any]] = []

    for team in load_teams():
        info = latest_run_info(team)
        if not info:
            out_rows.append({
                "team_id": team.team_id,
                "status": "no-qa-data",
            })
            continue

        run_id, total = info
        state_path = STATE_DIR / f"{team.team_id}.json"
        state = load_state(state_path, now)

        changed = run_id != str(state.get("last_run", "")) or total != int(state.get("last_total", -1))
        total_changed = total != int(state.get("last_total", -1))
        if changed:
            state["last_changed_epoch"] = now
            state["last_run"] = run_id
            state["last_total"] = total
        if total_changed:
            state["last_total_changed_epoch"] = now

        stagnant_seconds = max(0, now - int(state.get("last_total_changed_epoch", now)))
        stagnant = total > 0 and stagnant_seconds >= args.stagnation_seconds

        diag = dev_diag(team)
        dev_status = str(diag.get("dev_latest_status", "")).strip().lower()
        dev_outbox_age_seconds = 0
        outbox_mtime = int(diag.get("dev_latest_outbox_mtime", 0) or 0)
        if outbox_mtime > 0:
            dev_outbox_age_seconds = max(0, now - outbox_mtime)

        execution_stalled = (
            total > 0
            and int(diag.get("dev_findings_items", 0) or 0) > 0
            and not bool(diag.get("dev_active_processing", False))
            and dev_status in {"in_progress", "blocked", "needs-info", "idle"}
            and dev_outbox_age_seconds >= args.execution_stall_seconds
        )

        no_progress_15m = total > 0 and dev_outbox_age_seconds >= max(60, args.no_progress_seconds)
        handoff_gap = (
            total > 0
            and dev_outbox_age_seconds >= max(60, args.handoff_gap_seconds)
            and int(diag.get("dev_findings_items", 0) or 0) == 0
            and not bool(diag.get("dev_active_processing", False))
            and dev_status in {"complete", "done"}
        )
        starved_lane = (
            total > 0
            and no_progress_15m
            and int(diag.get("dev_findings_items", 0) or 0) == 0
            and not bool(diag.get("dev_active_processing", False))
            and dev_status in {"in_progress", "blocked", "needs-info", "idle", ""}
        )

        should_investigate = stagnant or execution_stalled or starved_lane or handoff_gap

        # Executor failure signal: count recent tmp/executor-failures/ entries.
        # Systemic executor failures (multiple agents in same window) indicate an
        # infrastructure problem distinct from individual agent blocked states.
        exec_fail_count, exec_fail_agents = count_recent_executor_failures(window_seconds=3600)
        systemic_executor_failure = exec_fail_count >= 3
        auto_remediate_attempted = False
        auto_remediate_succeeded = False
        auto_requeue_attempted = False
        auto_requeue_succeeded = False
        auto_requeue_item_id = ""
        handoff_recovery_attempted = False
        handoff_recovery_succeeded = False
        handoff_recovery_item_id = ""
        handoff_escalation_queued = False
        handoff_investigation_queued = False
        conversation_analyzed = False
        conversation_analysis: dict[str, Any] = {
            "conversation_analysis_available": False,
            "conversation_unhealthy": False,
            "conversation_reason": "not-run",
            "conversation_session_id": "",
            "conversation_recent_errors": 0,
            "conversation_recent_compaction_413": 0,
            "conversation_recent_tooluse_mismatch": 0,
        }
        session_reset_attempted = False
        session_reset_succeeded = False
        session_escalation_queued = False

        if args.auto_remediate and execution_stalled:
            last_auto = int(state.get("last_auto_remediate_epoch", 0) or 0)
            if now - last_auto >= max(60, args.auto_remediate_cooldown_seconds):
                auto_remediate_attempted = True
                auto_remediate_succeeded = try_auto_remediate(team)
                state["last_auto_remediate_epoch"] = now

        if args.auto_remediate and starved_lane:
            last_requeue = int(state.get("last_auto_requeue_epoch", 0) or 0)
            if now - last_requeue >= max(60, args.auto_remediate_cooldown_seconds):
                auto_requeue_attempted = True
                auto_requeue_succeeded, auto_requeue_item_id = auto_requeue_findings_item(team, run_id, total)
                state["last_auto_requeue_epoch"] = now
                if auto_requeue_succeeded:
                    auto_remediate_attempted = True
                    auto_remediate_succeeded = try_auto_remediate(team)
                    state["last_auto_remediate_epoch"] = now
                elif auto_requeue_item_id == "dev-blocked-on-run-qa-rerun-needed":
                    # Dev is blocked on this exact run — more dev retries won't help.
                    # Trigger a QA re-run to update `latest` with post-fix evidence.
                    auto_requeue_succeeded, auto_requeue_item_id = auto_queue_qa_rerun_item(team, run_id, total)
                    state["last_auto_requeue_epoch"] = now
                    if auto_requeue_succeeded:
                        auto_remediate_attempted = True
                        auto_remediate_succeeded = try_agent_exec(team.qa_agent)
                        state["last_auto_remediate_epoch"] = now

        if args.auto_remediate and handoff_gap:
            last_handoff_inv = int(state.get("last_handoff_investigated_epoch", 0) or 0)
            if now - last_handoff_inv >= max(60, args.handoff_investigation_cooldown_seconds):
                handoff_investigation_queued = queue_handoff_full_investigation(
                    team,
                    run_id,
                    total,
                    stagnant_seconds,
                    diag,
                )
                if handoff_investigation_queued:
                    state["last_handoff_investigated_epoch"] = now

            last_handoff = int(state.get("last_handoff_recovery_epoch", 0) or 0)
            if now - last_handoff >= max(60, args.auto_remediate_cooldown_seconds):
                handoff_recovery_attempted = True
                handoff_recovery_succeeded, handoff_recovery_item_id = auto_queue_qa_rerun_item(team, run_id, total)
                state["last_handoff_recovery_epoch"] = now
                if handoff_recovery_succeeded:
                    auto_remediate_attempted = True
                    auto_remediate_succeeded = try_agent_exec(team.qa_agent)
                    state["last_auto_remediate_epoch"] = now
                else:
                    last_handoff_escalated = int(state.get("last_handoff_escalated_epoch", 0) or 0)
                    if now - last_handoff_escalated >= args.cooldown_seconds:
                        handoff_escalation_queued = queue_handoff_escalation(
                            team,
                            run_id,
                            total,
                            diag,
                            handoff_recovery_item_id,
                        )
                        if handoff_escalation_queued:
                            state["last_handoff_escalated_epoch"] = now

        if args.auto_remediate and no_progress_15m:
            conversation_analysis = analyze_conversation_health(team.dev_agent, now, args.conversation_window_seconds)
            conversation_analyzed = True
            state["last_session_analysis_epoch"] = now
            reset_failures = int(state.get("session_reset_failures", 0) or 0)
            if bool(conversation_analysis.get("conversation_unhealthy", False)):
                if reset_failures < max(1, args.max_session_reset_failures):
                    last_reset = int(state.get("last_session_reset_epoch", 0) or 0)
                    if now - last_reset >= max(60, args.auto_remediate_cooldown_seconds):
                        session_reset_attempted = True
                        session_reset_succeeded = try_session_reset_and_reexec(team)
                        state["last_session_reset_epoch"] = now
                        if session_reset_succeeded:
                            state["session_reset_failures"] = 0
                        else:
                            state["session_reset_failures"] = reset_failures + 1
                if int(state.get("session_reset_failures", 0) or 0) >= max(1, args.max_session_reset_failures):
                    last_escalated = int(state.get("last_session_escalated_epoch", 0) or 0)
                    if now - last_escalated >= args.cooldown_seconds:
                        session_escalation_queued = queue_session_escalation(
                            team,
                            run_id,
                            total,
                            diag,
                            conversation_analysis,
                            int(state.get("session_reset_failures", 0) or 0),
                        )
                        if session_escalation_queued:
                            state["last_session_escalated_epoch"] = now

        queued = False
        followup_queued = False
        if should_investigate:
            last_inv = int(state.get("last_investigated_epoch", 0))
            if now - last_inv >= args.cooldown_seconds:
                # Include dev outbox content when agent is blocked — surfaces fix recommendations to CEO.
                outbox_excerpt = ""
                if execution_stalled and dev_status == "blocked":
                    outbox_excerpt = read_latest_outbox_content(team, diag)
                queued = queue_investigation(team, run_id, total, stagnant_seconds, diag, outbox_excerpt=outbox_excerpt)
                state["last_investigated_epoch"] = now
                state["followup_due_epoch"] = now + max(60, args.followup_seconds)

            # Auto-investigate: if previous alerts went unanswered (same run_id,
            # KPI still stuck), compose and queue a directive-style resolution
            # prompt rather than another passive stagnation notice.
            unanswered = count_unanswered_ceo_alerts(run_id)
            escalation_depth = count_needs_escalated_items(run_id)
            auto_inv_threshold = 2  # fire after 2 unanswered cycles
            last_auto_inv = int(state.get("last_auto_investigate_epoch", 0) or 0)
            auto_inv_cooldown = max(args.cooldown_seconds * 2, 600)
            if (unanswered >= auto_inv_threshold or escalation_depth >= 3) and (
                now - last_auto_inv >= auto_inv_cooldown
            ):
                auto_investigate_and_fix(team, run_id, total, diag)
                state["last_auto_investigate_epoch"] = now

            followup_due = int(state.get("followup_due_epoch", 0))
            if followup_due > 0 and now >= followup_due:
                followup_queued = queue_followup(team, run_id, total, stagnant_seconds, diag)
                state["last_followup_epoch"] = now
                state["followup_due_epoch"] = now + max(60, args.followup_seconds)
        else:
            # Movement resumed; clear any pending follow-up timer.
            state["followup_due_epoch"] = 0

        state_path.write_text(json.dumps(state, indent=2, sort_keys=True) + "\n", encoding="utf-8")

        row = {
            "team_id": team.team_id,
            "site": team.site,
            "run_id": run_id,
            "open_issues": total,
            "stagnant": stagnant,
            "execution_stalled": execution_stalled,
            "no_progress_15m": no_progress_15m,
            "handoff_gap": handoff_gap,
            "starved_lane": starved_lane,
            "stagnant_minutes": round(stagnant_seconds / 60.0, 1),
            "dev_outbox_age_minutes": round(dev_outbox_age_seconds / 60.0, 1),
            "investigation_queued": queued,
            "followup_queued": followup_queued,
            "auto_remediate_enabled": bool(args.auto_remediate),
            "auto_remediate_attempted": auto_remediate_attempted,
            "auto_remediate_succeeded": auto_remediate_succeeded,
            "auto_requeue_attempted": auto_requeue_attempted,
            "auto_requeue_succeeded": auto_requeue_succeeded,
            "auto_requeue_item_id": auto_requeue_item_id,
            "handoff_recovery_attempted": handoff_recovery_attempted,
            "handoff_recovery_succeeded": handoff_recovery_succeeded,
            "handoff_recovery_item_id": handoff_recovery_item_id,
            "handoff_escalation_queued": handoff_escalation_queued,
            "handoff_investigation_queued": handoff_investigation_queued,
            "conversation_analyzed": conversation_analyzed,
            "session_reset_attempted": session_reset_attempted,
            "session_reset_succeeded": session_reset_succeeded,
            "session_reset_failures": int(state.get("session_reset_failures", 0) or 0),
            "session_escalation_queued": session_escalation_queued,
            "followup_due_seconds": max(0, int(state.get("followup_due_epoch", 0)) - now),
            "exec_fail_count_1h": exec_fail_count,
            "exec_fail_agents": exec_fail_agents,
            "systemic_executor_failure": systemic_executor_failure,
            **diag,
            **conversation_analysis,
        }
        out_rows.append(row)

    if args.json:
        _stale = check_stale_inbox_items(ROOT / "sessions")
        print(json.dumps({
            "generated_at": datetime.now().isoformat(),
            "rows": out_rows,
            "stale_inbox_items": _stale,
            "stagnation_detected": bool(_stale) or any(
                r.get("stagnant") or r.get("systemic_executor_failure") or
                r.get("execution_stalled") or r.get("starved_lane") or r.get("handoff_gap")
                for r in out_rows if r.get("status") != "no-qa-data"
            ),
        }, indent=2))
    else:
        print("Release KPI monitor")
        stale_items = check_stale_inbox_items(ROOT / "sessions")
        if stale_items:
            for s in stale_items:
                print(f"STALE-INBOX: {s['agent']}/{s['item']} (roi={s['roi']}, age={s['age_hours']}h)")
        for r in out_rows:
            if r.get("status") == "no-qa-data":
                print(f"- {r['team_id']}: no-qa-data")
                continue
            if r.get("handoff_gap"):
                flag = "HANDOFF-GAP"
            elif r.get("systemic_executor_failure"):
                flag = "EXECUTOR-FAIL"
            elif r.get("starved_lane"):
                flag = "STARVED"
            elif r.get("stagnant"):
                flag = "STAGNANT"
            elif r.get("execution_stalled"):
                flag = "EXEC-STALLED"
            else:
                flag = "ok"
            queued = " queued-investigation" if r.get("investigation_queued") else ""
            if r.get("handoff_investigation_queued"):
                queued += " queued-handoff-investigation"
            fup = " queued-followup" if r.get("followup_queued") else ""
            remed = ""
            if r.get("auto_remediate_attempted"):
                remed = " auto-remediate=ok" if r.get("auto_remediate_succeeded") else " auto-remediate=failed"
            print(
                f"- {r['team_id']} ({r['site']}): {flag}{queued}{fup}{remed}; "
                f"run={r['run_id']}; open={r['open_issues']}; "
                f"stagnant_min={r['stagnant_minutes']}; "
                f"dev_outbox_age_min={r.get('dev_outbox_age_minutes',0)}; "
                f"followup_due_s={r.get('followup_due_seconds',0)}; "
                f"dev_inbox={r['dev_inbox_count']}; findings_items={r['dev_findings_items']}; "
                f"dev_status={r.get('dev_latest_status','')}"
            )
            if r.get("systemic_executor_failure"):
                print(
                    f"  EXECUTOR-FAIL: {r['exec_fail_count_1h']} failure(s) in last 1h "
                    f"for agents: {', '.join(r['exec_fail_agents'][:10])}"
                )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
