#!/usr/bin/env python3
"""Detect and reap stale detached Copilot CLI process groups.

The HQ executor intentionally reuses per-seat Copilot session IDs, but the
Copilot CLI can leave helper processes alive after the foreground call
completes. In practice these show up as no-TTY `node /usr/bin/copilot`
launcher groups that survive for days and accumulate swap.

This script targets only detached launcher groups that are old enough to be
considered stale and whose ancestry does not indicate an active HQ executor
run. Default mode is dry-run; pass --apply to terminate selected groups.
"""

from __future__ import annotations

import argparse
import json
import os
import signal
import subprocess
import time
from dataclasses import dataclass
from typing import Dict, List


_ACTIVE_ANCESTRY_MARKERS = (
    "scripts/agent-exec-next.sh",
    "orchestrator/run.py",
    "scripts/hq-automation.sh",
    "scripts/orchestrator-loop.sh",
)


@dataclass(frozen=True)
class ProcInfo:
    pid: int
    ppid: int
    pgid: int
    sid: int
    tty: str
    etimes: int
    args: str


def _load_processes() -> Dict[int, ProcInfo]:
    proc = subprocess.run(
        ["ps", "-eo", "pid=,ppid=,pgid=,sid=,tty=,etimes=,args="],
        capture_output=True,
        text=True,
        check=True,
    )
    out: Dict[int, ProcInfo] = {}
    for raw in proc.stdout.splitlines():
        line = raw.strip()
        if not line:
            continue
        parts = line.split(None, 6)
        if len(parts) < 7:
            continue
        try:
            pid, ppid, pgid, sid, tty, etimes = (
                int(parts[0]),
                int(parts[1]),
                int(parts[2]),
                int(parts[3]),
                parts[4],
                int(parts[5]),
            )
        except ValueError:
            continue
        out[pid] = ProcInfo(
            pid=pid,
            ppid=ppid,
            pgid=pgid,
            sid=sid,
            tty=tty,
            etimes=etimes,
            args=parts[6],
        )
    return out


def _ancestor_chain(proc: ProcInfo, table: Dict[int, ProcInfo], *, max_depth: int = 6) -> List[ProcInfo]:
    chain: List[ProcInfo] = []
    current = proc
    depth = 0
    while depth < max_depth:
        parent = table.get(current.ppid)
        if parent is None:
            break
        chain.append(parent)
        current = parent
        depth += 1
    return chain


def _is_copilot_launcher(proc: ProcInfo) -> bool:
    return proc.args == "node /usr/bin/copilot"


def _has_active_ancestry(proc: ProcInfo, table: Dict[int, ProcInfo]) -> bool:
    for ancestor in _ancestor_chain(proc, table):
        if any(marker in ancestor.args for marker in _ACTIVE_ANCESTRY_MARKERS):
            return True
    return False


def select_stale_launchers(
    table: Dict[int, ProcInfo],
    *,
    min_age_seconds: int,
) -> List[ProcInfo]:
    selected: List[ProcInfo] = []
    for proc in table.values():
        if not _is_copilot_launcher(proc):
            continue
        if proc.tty != "?":
            continue
        if proc.etimes < min_age_seconds:
            continue
        if _has_active_ancestry(proc, table):
            continue
        selected.append(proc)
    return sorted(selected, key=lambda p: (-p.etimes, p.pid))


def _group_pids(table: Dict[int, ProcInfo], pgid: int) -> List[int]:
    return sorted(proc.pid for proc in table.values() if proc.pgid == pgid)


def _wait_for_exit(pid: int, timeout_seconds: float) -> bool:
    deadline = time.time() + timeout_seconds
    while time.time() < deadline:
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            return True
        time.sleep(0.1)
    return False


def reap_launchers(
    launchers: List[ProcInfo],
    table: Dict[int, ProcInfo],
    *,
    grace_seconds: float,
) -> List[Dict[str, object]]:
    actions: List[Dict[str, object]] = []
    seen_pgids = set()
    for launcher in launchers:
        if launcher.pgid in seen_pgids:
            continue
        seen_pgids.add(launcher.pgid)
        pids = _group_pids(table, launcher.pgid)
        action: Dict[str, object] = {
            "launcher_pid": launcher.pid,
            "pgid": launcher.pgid,
            "age_seconds": launcher.etimes,
            "pids": pids,
            "signal": "SIGTERM",
            "killed": False,
            "escalated": False,
        }
        try:
            os.killpg(launcher.pgid, signal.SIGTERM)
        except ProcessLookupError:
            action["killed"] = True
            actions.append(action)
            continue
        except PermissionError as exc:
            action["error"] = str(exc)
            actions.append(action)
            continue

        if all(_wait_for_exit(pid, grace_seconds) for pid in pids):
            action["killed"] = True
            actions.append(action)
            continue

        action["escalated"] = True
        action["signal"] = "SIGKILL"
        try:
            os.killpg(launcher.pgid, signal.SIGKILL)
        except ProcessLookupError:
            action["killed"] = True
            actions.append(action)
            continue
        except PermissionError as exc:
            action["error"] = str(exc)
            actions.append(action)
            continue

        action["killed"] = all(_wait_for_exit(pid, grace_seconds) for pid in pids)
        actions.append(action)
    return actions


def _summarize(launchers: List[ProcInfo], table: Dict[int, ProcInfo], actions: List[Dict[str, object]]) -> Dict[str, object]:
    return {
        "candidates": [
            {
                "pid": proc.pid,
                "pgid": proc.pgid,
                "ppid": proc.ppid,
                "age_seconds": proc.etimes,
                "tty": proc.tty,
                "pids": _group_pids(table, proc.pgid),
            }
            for proc in launchers
        ],
        "candidate_count": len(launchers),
        "actions": actions,
        "killed_count": sum(1 for action in actions if action.get("killed")),
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true", help="Terminate matched stale Copilot launcher groups")
    parser.add_argument("--min-age-seconds", type=int, default=7200, help="Minimum launcher age before reap consideration")
    parser.add_argument("--grace-seconds", type=float, default=2.0, help="Seconds to wait after SIGTERM before SIGKILL")
    parser.add_argument("--json", action="store_true", help="Emit JSON summary")
    args = parser.parse_args()

    table = _load_processes()
    launchers = select_stale_launchers(table, min_age_seconds=max(1, args.min_age_seconds))
    actions: List[Dict[str, object]] = []
    if args.apply and launchers:
        actions = reap_launchers(launchers, table, grace_seconds=max(0.1, args.grace_seconds))
    summary = _summarize(launchers, table, actions)

    if args.json:
        print(json.dumps(summary, sort_keys=True))
    else:
        mode = "apply" if args.apply else "dry-run"
        print(f"mode={mode} candidates={summary['candidate_count']} killed={summary['killed_count']}")
        for candidate in summary["candidates"]:
            print(
                "candidate"
                f" pid={candidate['pid']}"
                f" pgid={candidate['pgid']}"
                f" age={candidate['age_seconds']}"
                f" pids={','.join(str(pid) for pid in candidate['pids'])}"
            )
        for action in summary["actions"]:
            print(
                "action"
                f" pgid={action['pgid']}"
                f" signal={action['signal']}"
                f" killed={str(action['killed']).lower()}"
                f" escalated={str(action['escalated']).lower()}"
            )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
