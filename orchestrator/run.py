#!/usr/bin/env python3
"""Consolidated orchestrator for copilot-sessions-hq.

Single process replacing: ceo-inbox-loop, inbox-loop, ceo-health-loop,
2-ceo-opsloop, and the old split ceo/non-ceo exec model.

Tick pipeline (in order):
  consume_replies    - pull Board replies from Drupal into agent inboxes
  dispatch_commands  - route inbox/commands/*.md to PM inboxes or CEO inbox
  release_cycle      - ensure each team has an active release cycle (interval-gated)
  pick_agents        - prioritize all agents (CEO included) that have inbox items
  exec_agents        - run agent-exec-next.sh for each picked agent
  health_check       - detect stalled agents, auto-remediate (cooldown-gated)
  kpi_monitor        - release KPI stagnation check (interval-gated)
  publish            - push telemetry to Drupal dashboard
"""

from __future__ import annotations

import argparse
import os
import re
import subprocess
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

REPO_ROOT = Path(__file__).resolve().parent.parent
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from orchestrator.runtime_graph.engine import LangGraphDeps, run_tick as _run_langgraph_tick
from orchestrator.release_prerequisites import ReleasePrerequisiteValidator
from orchestrator.failure_analyzer import ExecutorFailureAnalyzer
from orchestrator import release_cycle, health_and_audit, dispatch
from orchestrator.dispatch import _org_enabled

# Team IDs that have associated websites (used for agent->team mapping)
_TEAM_WEBSITE_PREFIX = ("forseti", "dungeoncrawler")

# ── Helpers ──────────────────────────────────────────────────────────────────

def _run(cmd: List[str], *, timeout: int = 600, env: Optional[Dict[str, str]] = None) -> Tuple[int, str]:
    try:
        proc = subprocess.run(
            cmd,
            cwd=str(REPO_ROOT),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=timeout,
            env=env,
        )
        return proc.returncode, (proc.stdout or "").strip()
    except subprocess.TimeoutExpired:
        return -1, f"TIMEOUT after {timeout}s"


def _safe_int(s: Any, default: int = 0) -> int:
    try:
        return int(s)
    except Exception:
        return default


def _now_ts() -> int:
    return int(time.time())


def _cooldown_ok(state_file: Path, seconds: int) -> bool:
    last = _safe_int(state_file.read_text(encoding="utf-8").strip() if state_file.exists() else "0", 0)
    return (_now_ts() - last) >= seconds


def _mark_now(state_file: Path) -> None:
    state_file.parent.mkdir(parents=True, exist_ok=True)
    state_file.write_text(str(_now_ts()), encoding="utf-8")


_RELEASE_CYCLE_CONTROL_DEFAULT = Path("/var/tmp/copilot-sessions-hq/release-cycle-control.json")


def _release_cycle_control_file() -> Path:
    override = os.environ.get("RELEASE_CYCLE_CONTROL_FILE", "").strip()
    if override:
        return Path(override)

    legacy = REPO_ROOT / "tmp" / "release-cycle-control.json"
    if _RELEASE_CYCLE_CONTROL_DEFAULT.exists():
        return _RELEASE_CYCLE_CONTROL_DEFAULT
    if legacy.exists():
        return legacy
    return _RELEASE_CYCLE_CONTROL_DEFAULT


def _release_cycle_control_state() -> Dict[str, Any]:
    import json as _json

    state_file = _release_cycle_control_file()
    if not state_file.exists():
        return {
            "enabled": True,
            "updated_at": None,
            "updated_by": None,
            "reason": None,
            "state_file": str(state_file),
        }

    try:
        data = _json.loads(state_file.read_text(encoding="utf-8", errors="ignore") or "{}")
    except Exception:
        data = {}

    if not isinstance(data, dict):
        data = {}

    data.setdefault("enabled", True)
    data.setdefault("updated_at", None)
    data.setdefault("updated_by", None)
    data.setdefault("reason", None)
    data["state_file"] = str(state_file)
    return data


def _is_release_cycle_enabled() -> bool:
    return bool(_release_cycle_control_state().get("enabled", True))


# ── Event signal system ───────────────────────────────────────────────────────
#
# Release lifecycle transitions emit lightweight signal files to
# tmp/events/pending/<event-name>.signal so that interval-gated steps
# (release_cycle, kpi_monitor) bypass their cooldown and react immediately
# instead of waiting up to 5 minutes.  Signals are consumed (deleted) by the
# step that reads them, ensuring each event drives exactly one bypass.
#
# Defined signals:
#   release-signoff-created  — a PM release signoff file was detected/advanced
#   release-cycle-advanced   — the active release ID was promoted to a new cycle
#   gate2-approved           — gate2 auto-approve was written to QA outbox
#   coordinated-push-done    — a cross-team coordinated push was dispatched

_EVENTS_DIR = REPO_ROOT / "tmp" / "events" / "pending"


def _emit_event(name: str) -> None:
    """Write a named event signal file. Idempotent — overwrites if already present."""
    try:
        _EVENTS_DIR.mkdir(parents=True, exist_ok=True)
        (_EVENTS_DIR / f"{name}.signal").write_text(
            str(_now_ts()), encoding="utf-8"
        )
    except Exception:
        pass  # Best-effort; never break a step over telemetry.


def _has_events(*names: str) -> bool:
    """Return True if any of the named signal files exist."""
    for name in names:
        if (_EVENTS_DIR / f"{name}.signal").exists():
            return True
    return False


def _consume_events(*names: str) -> None:
    """Delete named signal files after they have been processed."""
    for name in names:
        sig = _EVENTS_DIR / f"{name}.signal"
        try:
            sig.unlink(missing_ok=True)
        except Exception:
            pass


# ── Agent / YAML helpers ─────────────────────────────────────────────────────

def _load_agents_yaml_ids() -> List[str]:
    f = REPO_ROOT / "org-chart" / "agents" / "agents.yaml"
    if not f.exists():
        return []
    ids: List[str] = []
    for ln in f.read_text(encoding="utf-8", errors="ignore").splitlines():
        if ln.strip().startswith("- id:"):
            aid = ln.split(":", 1)[1].strip()
            if aid:
                ids.append(aid)
    return ids


def _agent_field(agent_id: str, field: str) -> str:
    """Read a single scalar field from agents.yaml for the given agent."""
    f = REPO_ROOT / "org-chart" / "agents" / "agents.yaml"
    if not f.exists():
        return ""
    in_item = False
    for ln in f.read_text(encoding="utf-8", errors="ignore").splitlines():
        m = re.match(r"^\s*-\s+id:\s*(.+)\s*$", ln)
        if m:
            in_item = m.group(1).strip() == agent_id
            continue
        if in_item:
            m2 = re.match(rf"^\s*{re.escape(field)}:\s*(.+)\s*$", ln)
            if m2:
                return m2.group(1).strip()
    return ""


def _role_for_agent(agent_id: str) -> str:
    return _agent_field(agent_id, "role")


def _primary_ceo_agent() -> str:
    """Resolve the active CEO seat for command/stagnation routing."""
    preferred = os.environ.get("ORCHESTRATOR_CEO_AGENT", "").strip()
    if preferred and preferred.startswith("ceo-copilot") and not _is_agent_paused(preferred):
        return preferred

    for agent_id in _load_agents_yaml_ids():
        if agent_id.startswith("ceo-copilot") and not _is_agent_paused(agent_id):
            return agent_id

    return "ceo-copilot"


def _is_agent_paused(agent_id: str) -> bool:
    script = REPO_ROOT / "scripts" / "is-agent-paused.sh"
    if not script.exists():
        return False
    rc, out = _run(["bash", str(script), agent_id], timeout=30)
    return rc == 0 and out.strip().lower() == "true"


def _agent_level_weight(role: str) -> int:
    return {"ceo": 500, "product-manager": 400, "business-analyst": 300,
            "software-developer": 200, "tester": 150, "security-analyst": 100}.get(role, 100)


def _agent_inbox_dir(agent_id: str) -> Path:
    return REPO_ROOT / "sessions" / agent_id / "inbox"


def _is_inbox_item_done(item_dir: Path) -> bool:
    """Return True if item's command.md is marked '- Status: done' (skip from dispatch)."""
    import re as _re
    cmd = item_dir / "command.md"
    if not cmd.exists():
        return False
    try:
        text = cmd.read_text(encoding="utf-8", errors="ignore")
        return bool(_re.search(r"^-\s+[Ss]tatus:\s*done", text, _re.MULTILINE))
    except Exception:
        return False


def _agent_inbox_count(agent_id: str) -> int:
    d = _agent_inbox_dir(agent_id)
    if not d.is_dir():
        return 0
    return sum(
        1 for p in d.iterdir()
        if p.is_dir() and p.name != "_archived" and not _is_inbox_item_done(p)
    )


def _load_org_priorities() -> Dict[str, int]:
    path = REPO_ROOT / "org-chart" / "priorities.yaml"
    if not path.exists():
        return {}
    txt = path.read_text(encoding="utf-8", errors="ignore")
    try:
        import yaml  # type: ignore
        data = yaml.safe_load(txt) or {}
        if isinstance(data.get("priorities"), dict):
            return {str(k): int(v) for k, v in data["priorities"].items() if str(v).lstrip("-").isdigit()}
    except Exception:
        pass
    # Minimal fallback
    priorities: Dict[str, int] = {}
    in_pr = False
    for ln in txt.splitlines():
        s = ln.strip()
        if s == "priorities:":
            in_pr = True
        elif in_pr and ln.startswith("  ") and ":" in ln:
            k, v = s.split(":", 1)
            try:
                priorities[k.strip()] = int(v.strip())
            except Exception:
                pass
    return priorities


def _org_priority_key(item_name: str) -> str:
    lower = item_name.lower()
    for k in ("agent-management", "agent-tracker", "copilot-agent-tracker", "copilot_agent_tracker"):
        if k in lower:
            return "agent-management"
    if any(k in lower for k in ("jobhunter", "job_hunter")):
        return "jobhunter"
    if "dungeoncrawler" in lower:
        return "dungeoncrawler"
    return ""


def _item_effective_roi(item_dir: Path, item_name: str, *, priorities: Dict[str, int]) -> int:
    roi_file = item_dir / "roi.txt"
    base = 1
    if roi_file.exists():
        digits = "".join(ch for ch in roi_file.read_text(encoding="utf-8", errors="ignore").splitlines()[0] if ch.isdigit())
        base = max(1, _safe_int(digits, 1))
    divisor = _safe_int(os.environ.get("ORG_PRIORITY_DIVISOR", "100"), 100)
    score = priorities.get(_org_priority_key(item_name), 0)
    bonus = (base * score) // divisor if divisor > 0 and score > 0 else 0
    return base + bonus


@dataclass(frozen=True)
class ScheduledAgent:
    agent_id: str
    level: int
    top_roi: int
    has_release_work: bool = False
    has_next_release_work: bool = False
    team_id: str = ""


def _active_release_ids() -> List[str]:
    """Return all currently active release IDs from tmp/release-cycle-active/."""
    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return []
    ids = []
    for f in active_dir.glob("*.release_id"):
        rid = f.read_text(encoding="utf-8").strip()
        if rid:
            ids.append(rid)
    return ids


def _next_release_ids() -> List[str]:
    """Return all currently tracked next-release IDs from tmp/release-cycle-active/."""
    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    if not active_dir.exists():
        return []
    ids = []
    for f in active_dir.glob("*.next_release_id"):
        rid = f.read_text(encoding="utf-8").strip()
        if rid:
            ids.append(rid)
    return ids


def _team_id_for_agent(agent_id: str) -> str:
    for team_id in _TEAM_WEBSITE_PREFIX:
        if team_id in agent_id:
            return team_id
    return ""


def _item_mentions_release(item_dir: pathlib.Path, release_ids: List[str]) -> bool:
    if not release_ids or not item_dir.exists():
        return False
    for rid in release_ids:
        if rid and rid in item_dir.name:
            return True
    for rel_path in ("command.md", "README.md"):
        path = item_dir / rel_path
        if not path.exists():
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        for rid in release_ids:
            if rid and rid in text:
                return True
    return False


def _item_text(item_dir: pathlib.Path) -> str:
    parts: List[str] = []
    for rel_path in ("command.md", "README.md"):
        path = item_dir / rel_path
        if path.exists():
            parts.append(path.read_text(encoding="utf-8", errors="ignore"))
    return "\n".join(parts)


def _item_mentions_current_release(item_dir: pathlib.Path, release_ids: List[str]) -> bool:
    if _item_mentions_release(item_dir, release_ids):
        return True
    text = _item_text(item_dir)
    if not text:
        return False
    return bool(re.search(r"\bcurrent release\b", text, re.IGNORECASE))


def _item_mentions_next_release(item_dir: pathlib.Path, next_release_ids: List[str]) -> bool:
    if _item_mentions_release(item_dir, next_release_ids):
        return True
    text = _item_text(item_dir)
    if not text:
        return False
    return bool(re.search(r"\bnext release\b", text, re.IGNORECASE))


def _agent_has_release_work(agent_id: str, release_ids: List[str]) -> bool:
    """Return True if the agent has an inbox item explicitly tied to the current release."""
    if not release_ids:
        return False
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False

    for item in inbox.iterdir():
        if item.is_dir() and item.name != "_archived" and _item_mentions_current_release(item, release_ids):
            return True

    return False


def _agent_has_next_release_work(agent_id: str, next_release_ids: List[str]) -> bool:
    """Return True if the agent has explicit PM/BA next-release prep work queued."""
    if not next_release_ids:
        return False
    role = _role_for_agent(agent_id)
    if role not in {"product-manager", "business-analyst"} and not (
        agent_id.startswith("pm-") or agent_id.startswith("ba-")
    ):
        return False
    inbox = _agent_inbox_dir(agent_id)
    if not inbox.exists():
        return False
    for item in inbox.iterdir():
        if item.is_dir() and item.name != "_archived" and _item_mentions_next_release(item, next_release_ids):
            return True
    return False


def _prioritized_agents() -> List[ScheduledAgent]:
    """All agents (CEO included) with inbox items, sorted by release-aware priority.

    Scheduling intent:
      1. Current-release work always gets first priority.
      2. One current-release seat per team is surfaced before any duplicate
         same-team current-release seats.
      3. Spare capacity then spills into next-release prep work instead of
         waiting for the current release to close out completely.
      4. Remaining current-release seats, remaining next-release seats, then
         all other work follow in priority order.
    """
    priorities = _load_org_priorities()
    release_ids = _active_release_ids()
    next_release_ids = _next_release_ids()
    agents = []
    for agent_id in _load_agents_yaml_ids():
        if _is_agent_paused(agent_id):
            continue
        if _agent_inbox_count(agent_id) <= 0:
            continue
        inbox = _agent_inbox_dir(agent_id)
        top = max(
            (_item_effective_roi(p, p.name, priorities=priorities) for p in inbox.iterdir() if p.is_dir() and p.name != "_archived" and not _is_inbox_item_done(p)),
            default=1,
        )
        has_release = _agent_has_release_work(agent_id, release_ids)
        has_next_release = (not has_release) and _agent_has_next_release_work(agent_id, next_release_ids)
        agents.append(ScheduledAgent(
            agent_id=agent_id,
            level=_agent_level_weight(_role_for_agent(agent_id)),
            top_roi=top,
            has_release_work=has_release,
            has_next_release_work=has_next_release,
            team_id=_team_id_for_agent(agent_id),
        ))

    def _sort_bucket(items: List[ScheduledAgent]) -> List[ScheduledAgent]:
        return sorted(items, key=lambda a: (-a.level, -a.top_roi, a.agent_id))

    def _split_first_per_team(items: List[ScheduledAgent]) -> tuple[List[ScheduledAgent], List[ScheduledAgent]]:
        first_pass: List[ScheduledAgent] = []
        remainder: List[ScheduledAgent] = []
        seen_teams: set[str] = set()
        seen_agents_without_team = 0
        for agent in items:
            team_key = agent.team_id or f"__no_team__:{seen_agents_without_team}"
            if not agent.team_id:
                seen_agents_without_team += 1
            if team_key not in seen_teams:
                seen_teams.add(team_key)
                first_pass.append(agent)
            else:
                remainder.append(agent)
        return first_pass, remainder

    current_first, current_remainder = _split_first_per_team(
        _sort_bucket([a for a in agents if a.has_release_work])
    )
    next_first, next_remainder = _split_first_per_team(
        _sort_bucket([a for a in agents if not a.has_release_work and a.has_next_release_work])
    )
    other_agents = _sort_bucket([a for a in agents if not a.has_release_work and not a.has_next_release_work])

    return current_first + next_first + next_remainder + current_remainder + other_agents


# ── Command dispatch ──────────────────────────────────────────────────────────

def _parse_md_field(text: str, field: str) -> str:
    m = re.search(rf"^-\s+{re.escape(field)}:\s*(.+)$", text, re.MULTILINE)
    return m.group(1).strip() if m else ""


def _route_to_ceo_inbox(content: str, topic: str, work_item: str) -> str:
    """Create a properly-named CEO inbox item so agent-exec-next.sh picks it up."""
    ceo_agent = _primary_ceo_agent()
    slug = re.sub(r"[^a-z0-9-]+", "-", topic.lower()).strip("-")[:50]
    item_id = f"{datetime.now(timezone.utc).strftime('%Y%m%d')}-needs-{ceo_agent}-{slug}"
    item_dir = REPO_ROOT / "sessions" / ceo_agent / "inbox" / item_id
    if item_dir.exists():
        return f"duplicate:{item_id}"
    item_dir.mkdir(parents=True, exist_ok=True)
    (item_dir / "README.md").write_text(
        f"# Command: {topic}\n\n"
        f"- Agent: {ceo_agent}\n"
        f"- Item: {item_id}\n"
        f"- Work item: {work_item}\n"
        f"- Status: pending\n"
        f"- Supervisor: board\n"
        f"- Created: {datetime.now(timezone.utc).isoformat()}\n\n"
        f"## Decision needed\n- Review and action or escalate this command.\n\n"
        f"## Recommendation\n- See command text below.\n\n"
        f"## Command text\n{content}\n",
        encoding="utf-8",
    )
    return f"ceo-inbox:{item_id}"


def _dispatch_commands_step(log: List[Any]) -> None:
    """Route inbox/commands/*.md to PM inboxes or CEO inbox.

    Routing priority:
            0. Has '- target:' for a non-HQ target (for example dev-laptop) → leave queued
      1. Has '- pm:' field → dispatch to that PM via dispatch-pm-request.sh
      2. Has '- work_item:' with matching features/<wi>/feature.md → look up PM owner
      3. Anything else → CEO inbox (CEO GenAI call will triage/action/escalate)
    """
    commands_dir = REPO_ROOT / "inbox" / "commands"
    processed_dir = REPO_ROOT / "inbox" / "processed"
    commands_dir.mkdir(parents=True, exist_ok=True)
    processed_dir.mkdir(parents=True, exist_ok=True)

    dispatched: List[str] = []
    for f in sorted(commands_dir.glob("*.md")):
        content = f.read_text(encoding="utf-8", errors="ignore")
        target = (_parse_md_field(content, "target") or "").strip().lower()
        pm = _parse_md_field(content, "pm")
        work_item = _parse_md_field(content, "work_item")
        topic = _parse_md_field(content, "topic") or f.stem

        dest = processed_dir / f.name

        if target and target not in {"hq", "orchestrator", "ceo"}:
            dispatched.append(f"deferred:{target} topic:{topic}")
            continue

        if pm:
            _run(["bash", "scripts/dispatch-pm-request.sh", pm, work_item or "", topic], timeout=60)
            # Copy original command into the dispatched PM inbox so agent-exec-next can find command.md.
            # (Legacy dispatch scripts did this; orchestrator must preserve the contract.)
            inbox_dir = REPO_ROOT / "sessions" / pm / "inbox"
            if inbox_dir.exists():
                # Find the item that was just created (should contain topic in its name)
                try:
                    latest_item = max(
                        (p for p in inbox_dir.iterdir() if p.is_dir() and topic in p.name),
                        key=lambda p: p.stat().st_mtime,
                        default=None
                    )
                    if latest_item:
                        command_path = latest_item / "command.md"
                        if not command_path.exists():
                            command_path.write_text(content, encoding="utf-8")
                except (OSError, ValueError):
                    pass
            f.rename(dest)
            dispatched.append(f"pm:{pm} topic:{topic}")
            continue

        if work_item:
            feature = REPO_ROOT / "features" / work_item / "feature.md"
            if feature.exists():
                pm_owner = _parse_md_field(feature.read_text(encoding="utf-8", errors="ignore"), "PM owner")
                if pm_owner:
                    _run(["bash", "scripts/dispatch-pm-request.sh", pm_owner, work_item, topic], timeout=60)
                    # Copy original command into the dispatched PM inbox (legacy dispatch contract).
                    inbox_dir = REPO_ROOT / "sessions" / pm_owner / "inbox"
                    if inbox_dir.exists():
                        try:
                            latest_item = max(
                                (p for p in inbox_dir.iterdir() if p.is_dir() and topic in p.name),
                                key=lambda p: p.stat().st_mtime,
                                default=None
                            )
                            if latest_item:
                                command_path = latest_item / "command.md"
                                if not command_path.exists():
                                    command_path.write_text(content, encoding="utf-8")
                        except (OSError, ValueError):
                            pass
                    f.rename(dest)
                    dispatched.append(f"pm:{pm_owner} via-feature:{work_item}")
                    continue

        # No PM found — route to CEO inbox for GenAI triage
        result = _route_to_ceo_inbox(content, topic, work_item or "")
        f.rename(dest)
        dispatched.append(result)

    log.append({"step": "dispatch_commands", "dispatched": dispatched})


# ── Health check ──────────────────────────────────────────────────────────────

_HEALTH_AUTOEXEC_STATE = REPO_ROOT / "tmp" / "orchestrator-health-last-autoexec"
_HEALTH_AUTOEXEC_COOLDOWN = 120  # seconds

# Health and audit state files (used by health_and_audit module)
_INBOX_AUDIT_STATE = REPO_ROOT / "tmp" / "orchestrator-stagnation" / "inbox_audit_last"
_QUARANTINE_ESCALATE_STATE = REPO_ROOT / "tmp" / "orchestrator-quarantine-escalate-last"
_SHIPPING_LAG_ESCALATE_STATE = REPO_ROOT / "tmp" / "orchestrator-shipping-lag-escalate-last"

_STAGNATION_STATE_DIR = REPO_ROOT / "tmp" / "orchestrator-stagnation"

# Stagnation signal thresholds
_STAG_NO_DONE_OUTBOX_SECONDS   = 900   # 15 min: no agent wrote Status:done
_STAG_INBOX_AGING_SECONDS      = 1800  # 30 min: inbox item sitting unresolved
_STAG_CEO_INBOX_DEPTH          = 3     # CEO has N+ pending items it hasn't cleared
_STAG_BLOCKED_TICKS            = 5     # N consecutive ticks with blocked agents + no new done outboxes
_STAG_NO_RELEASE_SECONDS       = 7200  # 2 hours: in-flight release with no signoff progress
_STAG_DISPATCH_COOLDOWN        = 1800  # 30 min between CEO dispatches

# hq-status.sh result cache — avoids re-running the full status scan every tick.
# Status is written to a JSON sidecar; reads return the cached value if fresh.
_HQ_STATUS_CACHE_FILE = REPO_ROOT / "tmp" / "hq-status-cache.json"
_HQ_STATUS_CACHE_TTL = 120  # seconds; re-run at most every 2 minutes


def _run_hq_status_cached() -> Tuple[int, str]:
    """Run hq-status.sh, caching results for _HQ_STATUS_CACHE_TTL seconds."""
    import json as _json_mod
    now = _now_ts()
    if _HQ_STATUS_CACHE_FILE.exists():
        try:
            data = _json_mod.loads(_HQ_STATUS_CACHE_FILE.read_text(encoding="utf-8"))
            if now - data.get("ts", 0) < _HQ_STATUS_CACHE_TTL:
                return int(data.get("rc", 0)), str(data.get("out", ""))
        except Exception:  # noqa: BLE001
            pass
    rc, out = _run(["bash", "scripts/hq-status.sh"], timeout=180)
    if rc != -1:  # only cache successful (non-timeout) runs
        try:
            _HQ_STATUS_CACHE_FILE.parent.mkdir(parents=True, exist_ok=True)
            _HQ_STATUS_CACHE_FILE.write_text(
                _json_mod.dumps({"ts": now, "rc": rc, "out": out}), encoding="utf-8"
            )
        except Exception:  # noqa: BLE001
            pass
    return rc, out


def _seconds_since_last_done_outbox() -> int:
    """Return seconds since any agent last wrote a Status:done outbox file."""
    import re as _re
    now = _now_ts()
    latest_done_mtime = 0
    for p in (REPO_ROOT / "sessions").glob("*/outbox/*.md"):
        if not p.is_file():
            continue
        try:
            text = p.read_text(encoding="utf-8", errors="ignore")
            if _re.search(r"^-\s+[Ss]tatus:\s*done", text, _re.MULTILINE):
                mtime = int(p.stat().st_mtime)
                if mtime > latest_done_mtime:
                    latest_done_mtime = mtime
        except Exception:
            continue
    return max(0, now - latest_done_mtime) if latest_done_mtime else 99999


def _oldest_unresolved_inbox_seconds() -> int:
    """Return age in seconds of the oldest inbox item that has no corresponding done outbox."""
    import re as _re
    now = _now_ts()
    oldest = 0
    sessions_dir = REPO_ROOT / "sessions"
    for agent_dir in sessions_dir.iterdir():
        if not agent_dir.is_dir():
            continue
        inbox_dir = agent_dir / "inbox"
        outbox_dir = agent_dir / "outbox"
        if not inbox_dir.exists():
            continue
        for item_dir in inbox_dir.iterdir():
            if not item_dir.is_dir() or item_dir.name == "_archived":
                continue
            if _is_inbox_item_done(item_dir):
                continue
            item_id = item_dir.name
            # Check if a done outbox exists for this item
            done = False
            if outbox_dir.exists():
                for candidate in outbox_dir.glob(f"{item_id}*.md"):
                    try:
                        text = candidate.read_text(encoding="utf-8", errors="ignore")
                        if _re.search(r"^-\s+[Ss]tatus:\s*done", text, _re.MULTILINE):
                            done = True
                            break
                    except Exception:
                        continue
            if not done:
                try:
                    age = int(now - item_dir.stat().st_mtime)
                    if age > oldest:
                        oldest = age
                except Exception:
                    continue
    return oldest


def _ceo_inbox_depth() -> int:
    """Return count of pending CEO inbox items."""
    ceo_inbox = REPO_ROOT / "sessions" / _primary_ceo_agent() / "inbox"
    if not ceo_inbox.exists():
        return 0
    return sum(1 for p in ceo_inbox.iterdir() if p.is_dir())


_STAG_ITEM_STALE_SECONDS = 14400  # 4h: re-dispatch if CEO stagnation item sits unresolved this long


def _ceo_has_pending_stagnation_item() -> bool:
    """Return True if there is already an unresolved stagnation item in CEO inbox.

    If the item has been sitting unresolved for > _STAG_ITEM_STALE_SECONDS (4h),
    treat it as stale and return False so a fresh dispatch can occur.  This
    prevents a deadlock where a CEO item stuck at in_progress/blocked blocks all
    future stagnation monitoring.
    """
    ceo_inbox = REPO_ROOT / "sessions" / _primary_ceo_agent() / "inbox"
    if not ceo_inbox.exists():
        return False
    now = _now_ts()
    for item_dir in ceo_inbox.iterdir():
        if not item_dir.is_dir() or "stagnation" not in item_dir.name:
            continue
        # If item is too old (stale), let a new dispatch happen
        age = now - int(item_dir.stat().st_mtime)
        if age > _STAG_ITEM_STALE_SECONDS:
            print(f"STAGNATION-STALE: item {item_dir.name} age={age}s > {_STAG_ITEM_STALE_SECONDS}s — allowing re-dispatch")
            continue
        # Check if it's been resolved (has a Status: done in any item file)
        for md in item_dir.glob("*.md"):
            text = md.read_text(encoding="utf-8", errors="ignore")
            if re.search(r"^-\s+Status:\s*done", text, re.MULTILINE | re.IGNORECASE):
                break  # this stagnation item is resolved
        else:
            return True  # unresolved, within age window — block re-dispatch
    return False


def _seconds_since_last_release_signoff() -> int:
    """Return seconds since any PM wrote a release signoff artifact."""
    now = _now_ts()
    latest = 0
    for p in (REPO_ROOT / "sessions").glob("*/artifacts/release-signoffs/*.md"):
        try:
            mtime = int(p.stat().st_mtime)
            if mtime > latest:
                latest = mtime
        except Exception:
            continue
    return max(0, now - latest) if latest else 99999


def _release_gate_brief() -> str:
    """Return a concise, actionable snapshot of current release gate status.

    Includes: active release IDs, which PMs have/haven't signed, QA preflight
    items pending, and the oldest unresolved inbox item per agent (top 5).
    This context is injected into the CEO stagnation brief so the CEO can act
    immediately without running manual diagnostics.
    """
    import json as _json

    lines: List[str] = []

    # 1. Release signoff status for each active release
    active_dir = REPO_ROOT / "tmp" / "release-cycle-active"
    active_releases: List[str] = []
    if active_dir.exists():
        for f in active_dir.glob("*.release_id"):
            rid = f.read_text(encoding="utf-8").strip()
            if rid:
                active_releases.append(rid)

    if active_releases:
        lines.append("### Active release gate status")
        teams_path = REPO_ROOT / "org-chart" / "products" / "product-teams.json"
        coordinated_teams: List[Dict[str, str]] = []
        try:
            teams_data = _json.loads(teams_path.read_text(encoding="utf-8"))
            for t in teams_data.get("teams", []):
                if t.get("active") and t.get("coordinated_release_default"):
                    tid = (t.get("id") or "").strip()
                    pm = (t.get("pm_agent") or "").strip()
                    if tid and pm:
                        coordinated_teams.append({"id": tid, "pm": pm})
        except Exception:
            pass

        for rid in active_releases:
            slug = re.sub(r"[^A-Za-z0-9._-]", "-", rid)[:80]
            signed: List[str] = []
            unsigned: List[str] = []
            for t in coordinated_teams:
                sf = REPO_ROOT / "sessions" / t["pm"] / "artifacts" / "release-signoffs" / f"{slug}.md"
                if sf.exists():
                    signed.append(t["pm"])
                else:
                    unsigned.append(t["pm"])
            lines.append(f"- `{rid}`:")
            lines.append(f"  - Signed: {', '.join(signed) if signed else 'none'}")
            if not unsigned:
                lines.append(f"  - **All signed — ready to push!**")
            elif signed:
                lines.append(f"  - **Push triggered (decoupled). Waiting on: {', '.join(unsigned)}**")
            else:
                lines.append(f"  - **Missing signoff: {', '.join(unsigned)}**")
    else:
        lines.append("### Active releases: none")

    # 2. QA preflight items still in inbox
    qa_agents = [a for a in _load_agents_yaml_ids() if a.startswith("qa-")]
    preflight_pending: List[str] = []
    for qa in qa_agents:
        inbox = _agent_inbox_dir(qa)
        if not inbox.exists():
            continue
        for item in inbox.iterdir():
            if item.is_dir() and item.name != "_archived" and "preflight" in item.name:
                preflight_pending.append(f"{qa}: {item.name}")
    if preflight_pending:
        lines.append("\n### QA preflight items still pending")
        for p in preflight_pending[:10]:
            lines.append(f"- {p}")

    # 3. Top 5 oldest unresolved inbox items across all agents
    lines.append("\n### Oldest unresolved inbox items (top 5)")
    oldest: List[Dict[str, Any]] = []
    now = _now_ts()
    for agent_id in _load_agents_yaml_ids():
        inbox = _agent_inbox_dir(agent_id)
        if not inbox.exists():
            continue
        for item in inbox.iterdir():
            if not item.is_dir() or item.name == "_archived":
                continue
            try:
                age_m = (now - int(item.stat().st_mtime)) // 60
                oldest.append({"agent": agent_id, "item": item.name, "age_m": age_m})
            except Exception:
                pass
    oldest.sort(key=lambda x: x["age_m"], reverse=True)
    for o in oldest[:5]:
        lines.append(f"- {o['agent']}: `{o['item']}` ({o['age_m']}m old)")

    # 4. Feature pipeline gap analysis — in_progress features with missing dev/QA coverage
    active_rids: set = set()
    if active_dir.exists():
        for f in active_dir.glob("*.release_id"):
            rid = f.read_text(encoding="utf-8").strip()
            if rid:
                active_rids.add(rid)
    gaps_found: List[str] = []
    if active_rids:
        for fm in sorted((REPO_ROOT / "features").glob("*/feature.md")):
            try:
                ftext = fm.read_text(encoding="utf-8", errors="ignore")
                if not re.search(r"^-\s+Status:\s*in_progress", ftext, re.MULTILINE | re.IGNORECASE):
                    continue
                rel_m2 = re.search(r"^-\s+Release:\s*(.+)", ftext, re.MULTILINE | re.IGNORECASE)
                if not rel_m2 or rel_m2.group(1).strip() not in active_rids:
                    continue
                feat_name = fm.parent.name
                site_m2 = re.search(r"^-\s+Website:\s*(.+)", ftext, re.MULTILINE | re.IGNORECASE)
                site_raw = site_m2.group(1).strip().lower() if site_m2 else ""
                site_id = next((k for k in _TEAM_WEBSITE_PREFIX if k in site_raw), None)
                if not site_id:
                    continue
                dev_agent = f"dev-{site_id}"
                qa_agent  = f"qa-{site_id}"
                dev_inbox  = _agent_inbox_has_feature(dev_agent, feat_name)
                dev_out    = _agent_outbox_has_feature(dev_agent, feat_name)
                qa_inbox   = _agent_inbox_has_feature(qa_agent,  feat_name)
                qa_out     = _agent_outbox_has_feature(qa_agent,  feat_name)
                if not dev_inbox and not dev_out and not qa_inbox and not qa_out:
                    gaps_found.append(f"GAP-A {feat_name}: no dev or QA coverage (impl not started)")
                elif dev_out and not qa_inbox and not qa_out:
                    gaps_found.append(f"GAP-B {feat_name}: dev done but no QA testgen/verification")
            except Exception:
                pass
    if gaps_found:
        lines.append("\n### ⚠️ Feature pipeline gaps (auto-remediation dispatched)")
        for g in gaps_found:
            lines.append(f"- {g}")
    else:
        lines.append("\n### Feature pipeline: no gaps detected")

    # 5. Quick inbox data quality snapshot (stale locks + missing READMEs/fields)
    sessions_dir = REPO_ROOT / "sessions"
    import re as _re2
    stale_locks = 0
    missing_readmes = 0
    missing_fields = 0
    now_ts = _now_ts()
    if sessions_dir.exists():
        for agent_d in sessions_dir.iterdir():
            ib = agent_d / "inbox"
            if not ib.exists():
                continue
            for lock in ib.glob("**/*.inwork"):
                try:
                    if now_ts - lock.stat().st_mtime > _INWORK_STALE_SECS:
                        stale_locks += 1
                except Exception:
                    pass
            for item_d in ib.iterdir():
                if item_d.name == "_archived" or not item_d.is_dir():
                    continue
                has_r = (item_d / "README.md").exists()
                has_c = (item_d / "command.md").exists()
                if not has_r and not has_c:
                    missing_readmes += 1
                else:
                    md_f = (item_d / "README.md") if has_r else (item_d / "command.md")
                    try:
                        txt = md_f.read_text(encoding="utf-8", errors="ignore")
                        if not _re2.search(r"^-\s+Agent:", txt, _re2.M) or \
                           not _re2.search(r"^-\s+Status:", txt, _re2.M):
                            missing_fields += 1
                    except Exception:
                        pass
    dq_issues = stale_locks + missing_readmes + missing_fields
    if dq_issues:
        lines.append(f"\n### ⚠️ Inbox data quality issues (will auto-remediate next tick)")
        if stale_locks:
            lines.append(f"- {stale_locks} stale .inwork lock(s)")
        if missing_readmes:
            lines.append(f"- {missing_readmes} item(s) missing README/command.md")
        if missing_fields:
            lines.append(f"- {missing_fields} item(s) missing Agent:/Status: fields")
    else:
        lines.append("\n### Inbox data quality: ✅ all items conformant")

    return "\n".join(lines)


def _stagnation_check(blocked_count: int, blocked_out: str) -> None:
    """Dispatch CEO agent for full analysis when any stagnation signal fires."""
    import hashlib as _hashlib
    _STAGNATION_STATE_DIR.mkdir(parents=True, exist_ok=True)
    ticks_file     = _STAGNATION_STATE_DIR / "blocked_ticks"
    dispatched_file = _STAGNATION_STATE_DIR / "last_dispatch"

    # --- Evaluate all signals ---
    signals: List[str] = []

    # 1. Org disabled with blocked agents
    if not _org_enabled() and blocked_count > 0:
        signals.append(f"ORG_DISABLED: org-control.json disabled with {blocked_count} blocked agent(s)")

    # 2. No Status:done outbox written recently (while work exists)
    if blocked_count > 0:
        done_age = _seconds_since_last_done_outbox()
        if done_age >= _STAG_NO_DONE_OUTBOX_SECONDS:
            signals.append(f"NO_DONE_OUTBOX: no agent wrote Status:done in {done_age // 60}m (threshold {_STAG_NO_DONE_OUTBOX_SECONDS // 60}m)")

    # 3. Inbox items aging without resolution
    oldest_inbox = _oldest_unresolved_inbox_seconds()
    if oldest_inbox >= _STAG_INBOX_AGING_SECONDS:
        signals.append(f"INBOX_AGING: oldest unresolved inbox item is {oldest_inbox // 60}m old (threshold {_STAG_INBOX_AGING_SECONDS // 60}m)")

    # 4. CEO inbox depth — system can't clear its own escalations
    ceo_depth = _ceo_inbox_depth()
    if ceo_depth >= _STAG_CEO_INBOX_DEPTH:
        signals.append(f"CEO_INBOX_DEPTH: {ceo_depth} pending CEO inbox items (threshold {_STAG_CEO_INBOX_DEPTH})")

    # 5. Consecutive blocked ticks with no new done outboxes
    if blocked_count > 0:
        ticks = _safe_int(ticks_file.read_text(encoding="utf-8").strip() if ticks_file.exists() else "0", 0) + 1
        ticks_file.write_text(str(ticks), encoding="utf-8")
        if ticks >= _STAG_BLOCKED_TICKS:
            signals.append(f"BLOCKED_TICKS: {ticks} consecutive ticks with {blocked_count} blocked agent(s) and no resolution (threshold {_STAG_BLOCKED_TICKS})")
    else:
        ticks_file.write_text("0", encoding="utf-8")

    # 6. No release shipped in threshold window (only relevant if release is active)
    active_release_files = list((REPO_ROOT / "tmp" / "release-cycle-active").glob("*.release_id")) if (REPO_ROOT / "tmp" / "release-cycle-active").exists() else []
    if active_release_files:
        signoff_age = _seconds_since_last_release_signoff()
        if signoff_age >= _STAG_NO_RELEASE_SECONDS:
            signals.append(f"NO_RELEASE_PROGRESS: no release signoff in {signoff_age // 3600}h {(signoff_age % 3600) // 60}m (threshold {_STAG_NO_RELEASE_SECONDS // 3600}h)")

    if not signals:
        return

    # Dedup: skip if a stagnation item is already pending in CEO inbox (not yet resolved)
    if _ceo_has_pending_stagnation_item():
        return

    # Cooldown: don't re-dispatch more than once per 30 minutes
    last_dispatch = _safe_int(dispatched_file.read_text(encoding="utf-8").strip() if dispatched_file.exists() else "0", 0)
    if (_now_ts() - last_dispatch) < _STAG_DISPATCH_COOLDOWN:
        return

    signals_text = "\n".join(f"  - {s}" for s in signals)
    gate_brief = _release_gate_brief()
    brief = (
        f"[STAGNATION ALERT] The orchestrator has detected that the org is stuck.\n\n"
        f"## Signals fired ({len(signals)}):\n{signals_text}\n\n"
        f"## What to do\n"
        f"Perform a full system analysis. Review all blocked agents, identify the root cause, "
        f"and take **direct action** to unblock — run drush commands, trigger audits, clear stale "
        f"locks, fix permissions, re-enable org. Do not just escalate; act.\n\n"
        f"For release blockers: check which PMs are missing signoffs and dispatch signoff-reminder "
        f"inbox items immediately (see cross-site signoff reminder pattern in your seat instructions).\n\n"
        f"## Release gate snapshot\n{gate_brief}\n\n"
        f"## Blocked agent summary\n{blocked_out or '(none currently blocked)'}\n"
    )
    result = _route_to_ceo_inbox(brief, "stagnation-full-analysis", f"stagnation-{len(signals)}-signals")
    dispatched_file.write_text(str(_now_ts()), encoding="utf-8")
    ticks_file.write_text("0", encoding="utf-8")
    print(f"STAGNATION-DISPATCH ({len(signals)} signals): {signals_text} → CEO: {result}")


_INWORK_STALE_SECS    = 7200          # .inwork lock is stale after 2h
def _health_check_step(provider: "RuntimeProvider", log: List[Any]) -> None:
    """Detect stalled agents (inbox items, no active exec) and auto-remediate."""
    rc, status_out = _run_hq_status_cached()
    if rc == -1:  # timeout — skip health check this tick, log and continue
        log.append({"step": "health_check", "skipped": "hq-status.sh timed out (>180s)"})
        print(f"HEALTH-CHECK-SKIP: hq-status.sh exceeded 180s timeout")
        return
    rc2, blocked_out = _run(["bash", "scripts/hq-blockers.sh"], timeout=60)
    rc3, blocked_count_str = _run(["bash", "scripts/hq-blockers.sh", "count"], timeout=60)
    blocked_count = _safe_int(blocked_count_str)

    idle_agents: List[str] = []
    for line in status_out.splitlines():
        parts = line.split()
        if len(parts) < 3:
            continue
        agent, inbox_s, exec_s = parts[0], parts[1], parts[2]
        if exec_s not in ("yes", "no"):
            continue
        if _safe_int(inbox_s) > 0 and exec_s == "no":
            idle_agents.append(agent)

    alert: Dict[str, Any] = {
        "step": "health_check",
        "idle_with_inbox": len(idle_agents),
        "blocked_count": blocked_count,
        "remediated": [],
    }

    # TEMP: Disable provider.run_one() - hangs indefinitely on real agent execution
    # This is identical to the hang in exec_agents; provider is likely blocking on subprocess
    # Skip auto-remediation for now to keep orchestration loop alive
    if idle_agents and _cooldown_ok(_HEALTH_AUTOEXEC_STATE, _HEALTH_AUTOEXEC_COOLDOWN):
        for agent in idle_agents[:5]:
            rc_exec, _ = provider.run_one(agent)
            alert["remediated"].append({"agent": agent, "rc": rc_exec})
        _mark_now(_HEALTH_AUTOEXEC_STATE)
        print(f"AUTO-REMEDIATE: stalled={len(idle_agents)} remediated={len(alert['remediated'])}")

    if blocked_count > 0:
        print(f"BLOCKED: {blocked_count} agent(s) blocked\n{blocked_out}")
        _stagnation_check(blocked_count, blocked_out)
    else:
        _stagnation_check(0, "")

    # ── Release-support dispatchers ──────────────────────────────────────────
    # These run every health-check tick (every health_check_interval seconds).
    # _dispatch_gate2_auto_approve is also called from _release_cycle_step as
    # a fallback for when this step returns early (hq-status.sh timeout).
    release_control = _release_cycle_control_state()
    if not bool(release_control.get("enabled", True)):
        alert["release_automation"] = {
            "status": "paused",
            "state_file": release_control.get("state_file"),
            "reason": release_control.get("reason"),
        }
    else:
        # Check auto-close triggers: ≥10 features or ≥24h since release started
        try:
            dispatch._dispatch_release_close_triggers()
        except Exception as e:
            print(f"RELEASE-CLOSE-TRIGGER-ERR: {e}")

        # Auto-generate Gate 2 APPROVE when all suite-activates for a release are done
        try:
            dispatch._dispatch_gate2_auto_approve()
        except Exception as e:
            print(f"GATE2-AUTO-APPROVE-ERR: {e}")

        # Nudge PM to scope features when a new release has been empty for 15+ min
        try:
            dispatch._dispatch_scope_activate_nudge()
        except Exception as e:
            print(f"SCOPE-ACTIVATE-NUDGE-ERR: {e}")

        # Scan for release feature gaps: in_progress features with no dev/QA inbox coverage
        try:
            dispatch._dispatch_feature_gap_remediation()
        except Exception as e:
            print(f"FEATURE-GAP-ERR: {e}")

        # Escalate when gating agents (PM, code-review) are majority-quarantined (ISSUE-012)
        try:
            health_and_audit.escalate_quarantined_gating_agents(REPO_ROOT, _QUARANTINE_ESCALATE_STATE)
        except Exception as e:
            print(f"QUARANTINE-ESCALATE-ERR: {e}")

        # Escalate when release has been dev-complete but unshipped for >72h (ISSUE-010)
        try:
            health_and_audit.escalate_shipping_lag(REPO_ROOT, _SHIPPING_LAG_ESCALATE_STATE)
        except Exception as e:
            print(f"SHIPPING-LAG-ERR: {e}")

    # Audit inbox data quality: clear stale locks, inject missing READMEs, patch fields
    try:
        audit_stats = health_and_audit.audit_inbox_data_quality(REPO_ROOT, _INBOX_AUDIT_STATE)
        if audit_stats:
            alert["inbox_audit"] = audit_stats
    except Exception as e:
        print(f"INBOX-AUDIT-ERR: {e}")

    # Reap stale detached Copilot process groups before they accumulate swap.
    try:
        reaper_stats = health_and_audit.reap_stale_copilot_processes()
        if reaper_stats.get("candidate_count") or reaper_stats.get("killed_count"):
            alert["copilot_reaper"] = reaper_stats
            if reaper_stats.get("killed_count"):
                print(
                    "COPILOT-REAPER:"
                    f" candidates={reaper_stats.get('candidate_count', 0)}"
                    f" killed={reaper_stats.get('killed_count', 0)}"
                )
    except Exception as e:
        print(f"COPILOT-REAPER-ERR: {e}")

    # ════════════════════════════════════════════════════════════════════════════
    # ROOT CAUSE FIX #2: Executor failure diagnosis and pattern detection
    # Only analyze if failures exist; don't over-analyze on every tick
    # ════════════════════════════════════════════════════════════════════════════
    try:
        analyzer = ExecutorFailureAnalyzer(REPO_ROOT)
        
        # Quick count first - only do detailed analysis if there are actual failures
        recent_failures = analyzer.load_recent_failures(hours=24)
        if recent_failures:
            failure_analysis = analyzer.analyze_patterns(hours=24)
            
            # Only include in alert if there are issues to report
            if failure_analysis["total_failures"] > 0:
                alert["failure_analysis"] = {
                    "total_failures_24h": failure_analysis["total_failures"],
                    "high_rate_agents": failure_analysis["high_rate_agents"],
                }
                
                # Only log recommendations for actual problems
                recommendations = analyzer.recommend_action(failure_analysis)
                if failure_analysis.get("potential_issues"):
                    alert["failure_recommendations"] = recommendations
                    print(f"EXECUTOR-HEALTH: {failure_analysis['total_failures']} failures in 24h")
                    for issue in failure_analysis.get("potential_issues", []):
                        print(f"  - {issue}")
    except Exception as e:
        print(f"FAILURE-ANALYSIS-ERR: {e}")

    log.append(alert)



# ── Runtime provider ──────────────────────────────────────────────────────────

class RuntimeProvider:
    def run_one(self, agent_id: str) -> Tuple[int, str]:
        raise NotImplementedError


class ShellProvider(RuntimeProvider):
    def run_one(self, agent_id: str) -> Tuple[int, str]:
        return _run(["bash", "scripts/agent-exec-next.sh", agent_id], timeout=3600)


class ClineProvider(RuntimeProvider):
    def run_one(self, agent_id: str) -> Tuple[int, str]:
        import shutil
        exe = shutil.which("cline")
        if not exe:
            return 2, "cline not in PATH; use --provider shell"
        return _run([exe, "run", "--agent", agent_id], timeout=3600)


# ── Release cycle wrappers (delegates to release_cycle module) ────────────────

def _release_cycle_step(log: List[Any]) -> None:
    """Delegate to release_cycle module."""
    _setup_runtime_modules()
    release_cycle.run_release_cycle_step(log, REPO_ROOT)


def _coordinated_push_step(log: List[Any]) -> None:
    """Delegate to release_cycle module."""
    _setup_runtime_modules()
    release_cycle.run_coordinated_push_step(log, REPO_ROOT)


def _setup_runtime_modules() -> None:
    """Wire shared orchestrator modules to the live runtime helpers/state."""
    dispatch.setup(
        repo_root=REPO_ROOT,
        signoff_reminder_state=REPO_ROOT / "tmp" / "orchestrator-stagnation" / "signoff_reminder_dispatch",
        proactive_signoff_state=REPO_ROOT / "tmp" / "orchestrator-stagnation" / "proactive_signoff_dispatch",
        release_close_state_dir=REPO_ROOT / "tmp" / "orchestrator-stagnation",
    )
    release_cycle._run = _run
    release_cycle._emit_event = _emit_event
    release_cycle._release_cycle_control_state = _release_cycle_control_state
    release_cycle._dispatch_signoff_reminders = dispatch._dispatch_signoff_reminders
    release_cycle._dispatch_gate2_auto_approve = dispatch._dispatch_gate2_auto_approve
    release_cycle._dispatch_proactive_awaiting_signoff = dispatch._dispatch_proactive_awaiting_signoff


def _count_site_features_for_release(site: str, rid: str) -> int:
    """Compatibility wrapper preserving release guard semantics in run.py."""
    return dispatch._count_site_features_for_release(site, rid)


def _scope_activate_nudge_feature_count(site: str, rid: str) -> int:
    """Keep the activated-scope counter explicit in this runtime module."""
    feature_count = _count_site_features_for_release(site, rid)
    return feature_count


def _feature_gap_needs_dev_dispatch(dev_has_inbox: bool, dev_has_outbox: bool) -> bool:
    """Feature GAP-A dispatch depends only on missing Dev coverage."""
    if not dev_has_inbox and not dev_has_outbox:
        return True
    return False


def _feature_level_dev_owner(feature_text: str, fallback_dev_agent: str) -> str:
    """Parse the feature-level Dev owner: field while preserving fallback behavior."""
    dev_agent = fallback_dev_agent
    m = re.search(r"^-\s+Dev owner:\s*(.+)$", feature_text, re.MULTILINE)
    if m:
        parsed = m.group(1).strip()
        if parsed:
            dev_agent = parsed
    return dev_agent


def _make_provider(name: str) -> RuntimeProvider:
    return {"shell": ShellProvider, "cline": ClineProvider}.get(name, ShellProvider)()


# ── Tick ─────────────────────────────────────────────────────────────────────

def _run_tick(
    provider: RuntimeProvider,
    *,
    agent_cap: int,
    publish_enabled: bool,
    kpi_interval: int,
    kpi_last_run: int,
    release_cycle_interval: int,
    release_cycle_last_run: int,
) -> Tuple[Dict[str, Any], int, int]:
    """Run one full orchestration tick through the LangGraph execution graph."""
    deps = LangGraphDeps(
        run_cmd=_run,
        dispatch_commands_step=_dispatch_commands_step,
        release_cycle_step=_release_cycle_step,
        coordinated_push_step=_coordinated_push_step,
        prioritized_agents=_prioritized_agents,
        health_check_step=_health_check_step,
        now_ts=_now_ts,
        kpi_monitor_cmd=[sys.executable, "scripts/release-kpi-monitor.py", "--auto-remediate"],
        has_events=_has_events,
        consume_events=_consume_events,
    )
    return _run_langgraph_tick(
        provider,
        agent_cap=agent_cap,
        publish_enabled=publish_enabled,
        kpi_interval=kpi_interval,
        kpi_last_run=kpi_last_run,
        release_cycle_interval=release_cycle_interval,
        release_cycle_last_run=release_cycle_last_run,
        deps=deps,
    )


# ── Entry point ───────────────────────────────────────────────────────────────

def main() -> None:
    parser = argparse.ArgumentParser(description="Consolidated HQ orchestrator")
    parser.add_argument("--once", action="store_true", help="Run one tick and exit")
    parser.add_argument("--interval", type=int, default=60, help="Seconds between ticks")
    parser.add_argument("--provider", choices=["shell", "cline"], default="shell")
    parser.add_argument("--agent-cap", type=int, default=6,
                        help="Max agents to execute per tick (CEO counts toward cap)")
    parser.add_argument("--non-ceo-cap", type=int, default=None,
                        help="Deprecated alias; mapped to total cap as non_ceo+1")
    parser.add_argument("--no-publish", action="store_true")
    parser.add_argument("--kpi-interval", type=int, default=300,
                        help="Seconds between KPI monitor runs (default 5 min)")
    parser.add_argument("--release-cycle-interval", type=int, default=300,
                        help="Seconds between release cycle checks (default 5 min)")
    parser.add_argument("--log-file", default="inbox/responses/orchestrator-latest.log")
    args = parser.parse_args()

    provider = _make_provider(args.provider)
    
    _setup_runtime_modules()
    
    effective_agent_cap = max(0, int(args.agent_cap))
    if args.non_ceo_cap is not None:
        effective_agent_cap = max(effective_agent_cap, max(0, int(args.non_ceo_cap)) + 1)
    log_path = (REPO_ROOT / args.log_file).resolve()
    log_path.parent.mkdir(parents=True, exist_ok=True)

    def _write_log(result: Dict[str, Any]) -> None:
        selected = result.get("selected_agents") or []
        line = f"[{result.get('ts', time.strftime('%Y-%m-%dT%H:%M:%S%z'))}] agents={','.join(selected) if selected else '-'}\n"
        with log_path.open("a", encoding="utf-8") as f:
            f.write(line)

    if args.once:
        if (REPO_ROOT / "scripts" / "is-org-enabled.sh").exists():
            rc, enabled = _run(["bash", "scripts/is-org-enabled.sh"], timeout=10)
            if enabled.strip().lower() != "true":
                print("org disabled; skipping tick")
                return
        result, _, _ = _run_tick(
            provider,
            agent_cap=effective_agent_cap,
            publish_enabled=not args.no_publish,
            kpi_interval=args.kpi_interval,
            kpi_last_run=0,
            release_cycle_interval=args.release_cycle_interval,
            release_cycle_last_run=0,
        )
        _write_log(result)
        return

    kpi_last_run = 0
    release_cycle_last_run = 0
    while True:
        tick_start = time.time()
        try:
            rc, enabled = _run(["bash", "scripts/is-org-enabled.sh"], timeout=10)
            if enabled.strip().lower() != "true":
                time.sleep(max(1, args.interval))
                continue
            result, kpi_last_run, release_cycle_last_run = _run_tick(
                provider,
                agent_cap=effective_agent_cap,
                publish_enabled=not args.no_publish,
                kpi_interval=args.kpi_interval,
                kpi_last_run=kpi_last_run,
                release_cycle_interval=args.release_cycle_interval,
                release_cycle_last_run=release_cycle_last_run,
            )
            _write_log(result)
        except Exception as e:
            print(f"[WARN] tick failed: {e}", file=sys.stderr)
        # Sleep only the remaining portion of the interval so that
        # interval is the period between tick *starts*, not tick end + sleep.
        elapsed = time.time() - tick_start
        remaining = max(1, args.interval - elapsed)
        time.sleep(remaining)


if __name__ == "__main__":
    main()
