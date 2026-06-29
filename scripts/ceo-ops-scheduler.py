#!/usr/bin/env python3
from __future__ import annotations

import json
import os
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path


ROOT = Path(os.environ.get("HQ_ROOT_DIR", Path(__file__).resolve().parents[1]))
STATE_PATH = ROOT / "tmp" / "ceo-ops-scheduler-state.json"
LOG_DIR = ROOT / "inbox" / "responses"
LATEST_LOG = LOG_DIR / "ceo-ops-latest.log"
DAYLOG = LOG_DIR / f"ceo-ops-{datetime.now(timezone.utc).strftime('%Y%m%d')}.log"
RCA_THRESHOLD = int(os.environ.get("CEO_OPS_RCA_THRESHOLD", "2"))
RELAXED_INTERVAL_HOURS = int(os.environ.get("CEO_OPS_RELAXED_INTERVAL_HOURS", "2"))
OPS_SCRIPT = ROOT / "scripts" / "ceo-ops-once.sh"

FAIL_RE = re.compile(r"^❌ FAIL (.+)$")
WARN_RE = re.compile(r"^⚠️\s+WARN (.+)$")


def _now() -> datetime:
    return datetime.now(timezone.utc)


def _read_state() -> dict:
    if not STATE_PATH.exists():
        return {"fast_mode": False, "blockers": {}}
    try:
        return json.loads(STATE_PATH.read_text(encoding="utf-8"))
    except Exception:
        return {"fast_mode": False, "blockers": {}}


def _write_state(state: dict) -> None:
    STATE_PATH.parent.mkdir(parents=True, exist_ok=True)
    STATE_PATH.write_text(json.dumps(state, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def should_run_full_cycle(now: datetime, fast_mode: bool, relaxed_interval_hours: int = RELAXED_INTERVAL_HOURS) -> bool:
    if fast_mode:
        return True
    return now.minute == 0 and now.hour % relaxed_interval_hours == 0


def extract_blockers(output: str) -> list[str]:
    blockers: list[str] = []
    seen: set[str] = set()
    for line in output.splitlines():
        match = FAIL_RE.match(line)
        if not match:
            continue
        text = match.group(1).strip()
        if text and text not in seen:
            blockers.append(text)
            seen.add(text)
    return blockers


def update_blocker_state(previous: dict, blockers: list[str], seen_at: str) -> dict:
    prior_blockers = previous.get("blockers", {})
    current: dict[str, dict] = {}
    for blocker in blockers:
        prior = prior_blockers.get(blocker, {})
        current[blocker] = {
            "count": int(prior.get("count", 0)) + 1,
            "first_seen": prior.get("first_seen", seen_at),
            "last_seen": seen_at,
        }
    return current


def _slug(value: str, limit: int = 80) -> str:
    slug = re.sub(r"[^A-Za-z0-9._-]+", "-", value).strip("-")
    return slug[:limit] or "blocker"


def _ceo_item_exists(marker: str) -> bool:
    for bucket in ("inbox", "outbox"):
        root = ROOT / "sessions" / "ceo-copilot-2" / bucket
        if not root.exists():
            continue
        for path in root.iterdir():
            if marker in path.name:
                return True
    return False


def _queue_rca_item(blocker: str, count: int, first_seen: str, last_seen: str) -> bool:
    marker = f"rca-persistent-blocker-{_slug(blocker, 48)}"
    if _ceo_item_exists(marker):
        return False

    date_prefix = _now().strftime("%Y%m%d")
    inbox_dir = ROOT / "sessions" / "ceo-copilot-2" / "inbox" / f"{date_prefix}-{marker}"
    inbox_dir.mkdir(parents=True, exist_ok=True)
    (inbox_dir / "roi.txt").write_text("20\n", encoding="utf-8")
    readme = f"""# Persistent blocker RCA: {blocker}

- Agent: ceo-copilot-2
- Dispatched-by: ceo-ops-scheduler.py
- Blocker: {blocker}
- Consecutive CEO cycles observed: {count}
- First seen: {first_seen}
- Last seen: {last_seen}

## Issue

This blocker has failed to clear across multiple CEO monitoring cycles. CEO ownership is now required to keep release momentum moving.

## Required actions

1. Identify the current owner and latest evidence.
2. Perform a **5 Whys** root-cause analysis, or an equivalent RCA if more appropriate.
3. Decide the containment action needed now to unblock release flow.
4. Either fix directly or dispatch a stronger corrective action to the owning seat.
5. Keep this item open until the underlying blocker no longer appears in `bash scripts/ceo-ops-once.sh`.

## 5 Whys template

1. Why is this blocker happening?
2. Why did that happen?
3. Why did that happen?
4. Why did that happen?
5. Why did that happen?

## Acceptance criteria

- Root cause is documented in CEO outbox
- Containment and permanent fix are documented
- Verification evidence is recorded
- The blocker no longer appears in CEO release/system health output
"""
    (inbox_dir / "README.md").write_text(readme, encoding="utf-8")
    return True


def _run_ops() -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["bash", str(OPS_SCRIPT)],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=False,
    )


def _write_log(output: str) -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    with DAYLOG.open("a", encoding="utf-8") as fh:
        fh.write(output + ("\n" if not output.endswith("\n") else ""))
    LATEST_LOG.write_text(output + ("\n" if not output.endswith("\n") else ""), encoding="utf-8")


def _append_daylog(line: str) -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    with DAYLOG.open("a", encoding="utf-8") as fh:
        fh.write(line.rstrip("\n") + "\n")
    LATEST_LOG.write_text(line.rstrip("\n") + "\n", encoding="utf-8")


def main() -> int:
    now = _now()
    state = _read_state()
    fast_mode = bool(state.get("fast_mode", False))
    force_run = os.environ.get("CEO_OPS_FORCE_RUN", "0") == "1"

    if not force_run and not should_run_full_cycle(now, fast_mode):
        _append_daylog(
            f"[{now.isoformat()}] skip: relaxed mode active; next full CEO cycle runs on {RELAXED_INTERVAL_HOURS}h boundary"
        )
        return 0

    result = _run_ops()
    output = result.stdout + (("\n" + result.stderr) if result.stderr else "")
    _write_log(output)

    seen_at = now.isoformat()
    blockers = extract_blockers(output)
    blocker_state = update_blocker_state(state, blockers, seen_at)

    rca_created = 0
    for blocker, meta in blocker_state.items():
        count = int(meta.get("count", 0))
        if count >= RCA_THRESHOLD and _queue_rca_item(
            blocker,
            count,
            str(meta.get("first_seen", seen_at)),
            str(meta.get("last_seen", seen_at)),
        ):
            rca_created += 1

    next_state = {
        "fast_mode": result.returncode != 0,
        "last_run_at": seen_at,
        "last_rc": result.returncode,
        "blockers": blocker_state,
        "last_blockers": blockers,
        "last_rca_created": rca_created,
    }
    _write_state(next_state)
    return result.returncode


if __name__ == "__main__":
    raise SystemExit(main())
