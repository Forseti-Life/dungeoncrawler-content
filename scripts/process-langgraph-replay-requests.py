#!/usr/bin/env python3
from __future__ import annotations

import argparse
import fcntl
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

REPO_ROOT = Path(__file__).resolve().parent.parent
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from orchestrator.langgraph_request_paths import checkpoint_replays_root

RUN_LOG_DIR = REPO_ROOT / "tmp" / "langgraph-replay-request-runs"
LOCK_PATH = REPO_ROOT / "tmp" / ".langgraph-replay-requests.lock"


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def discover_request_root() -> Path:
    override = os.environ.get("DRUPAL_LANGGRAPH_REPLAY_REQUEST_DIR", "").strip()
    if override:
        return Path(override)

    return checkpoint_replays_root()


def python_bin() -> str:
    venv_python = REPO_ROOT / "orchestrator" / ".venv" / "bin" / "python"
    return str(venv_python) if venv_python.exists() else sys.executable


def read_json(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        data = {}
    return data if isinstance(data, dict) else {}


def write_json(path: Path, payload: dict[str, Any]) -> None:
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    os.replace(tmp, path)


def request_paths(root: Path, flow_id: str | None) -> list[Path]:
    pattern = "*.json" if not flow_id else f"{flow_id}/*.json"
    paths = list(root.glob(pattern)) if flow_id else list(root.glob("*/*.json"))
    paths.sort(key=lambda path: path.stat().st_mtime, reverse=True)
    return paths


def pending_replay_requests(root: Path, flow_id: str | None) -> list[tuple[Path, dict[str, Any]]]:
    requests: list[tuple[Path, dict[str, Any]]] = []
    for path in request_paths(root, flow_id):
        payload = read_json(path)
        if payload.get("artifact_type") != "replay_request":
            continue
        if str(payload.get("status", "requested")) not in {"requested", "accepted"}:
            continue
        requests.append((path, payload))
    return requests


def mark_status(path: Path, payload: dict[str, Any], status: str, message: str, **extra: Any) -> dict[str, Any]:
    payload["status"] = status
    payload["status_message"] = message
    payload["updated_at"] = now_iso()
    payload.update(extra)
    write_json(path, payload)
    return payload


def run_cmd(cmd: list[str], *, env: dict[str, str] | None = None) -> tuple[int, str]:
    proc = subprocess.run(
        cmd,
        cwd=str(REPO_ROOT),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        env=env,
    )
    return proc.returncode, (proc.stdout or "").strip()


def process_hq_orchestrator_tick(path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    checkpoint_path = Path(str(payload.get("checkpoint_path", "")))
    checkpoint_id = str(payload.get("checkpoint_id", path.stem))
    request_id = str(payload.get("request_id", path.stem))

    if not checkpoint_path.is_file():
        return mark_status(
            path,
            payload,
            "failed",
            "Selected checkpoint artifact is no longer available on disk.",
            completed_at=now_iso(),
        )

    payload = mark_status(
        path,
        payload,
        "running",
        "Validated checkpoint artifact and dispatching a dry-run tick with checkpoint reference metadata.",
        started_at=now_iso(),
    )

    RUN_LOG_DIR.mkdir(parents=True, exist_ok=True)
    log_path = RUN_LOG_DIR / f"{request_id}.log"
    env = dict(os.environ)
    env["LANGGRAPH_REPLAY_CHECKPOINT_ID"] = checkpoint_id
    env["LANGGRAPH_REPLAY_CHECKPOINT_PATH"] = str(checkpoint_path)
    cmd = [python_bin(), "orchestrator/run.py", "--once", "--agent-cap", os.environ.get("ORCHESTRATOR_AGENT_CAP", "6"), "--no-publish"]
    rc, out = run_cmd(cmd, env=env)
    log_path.write_text(out + ("\n" if out and not out.endswith("\n") else ""), encoding="utf-8")

    if rc == 0:
        return mark_status(
            path,
            payload,
            "completed",
            "Checkpoint artifact validated and a dry-run tick completed. Native checkpoint state restore is not yet available in the orchestrator runtime.",
            completed_at=now_iso(),
            executor="orchestrator/run.py --once --no-publish",
            exit_code=rc,
            checkpoint_reference_mode="artifact_reference_only",
            result_log_path=str(log_path),
        )

    return mark_status(
        path,
        payload,
        "failed",
        f"Replay dispatch exited with rc={rc}.",
        completed_at=now_iso(),
        executor="orchestrator/run.py --once --no-publish",
        exit_code=rc,
        checkpoint_reference_mode="artifact_reference_only",
        result_log_path=str(log_path),
    )


def process_request(path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    flow_id = str(payload.get("flow_id", ""))
    payload = mark_status(path, payload, "accepted", "Replay request accepted by executor.", accepted_at=now_iso())

    if flow_id == "hq_orchestrator_tick":
        return process_hq_orchestrator_tick(path, payload)

    return mark_status(
        path,
        payload,
        "failed",
        f"No replay executor is registered for flow {flow_id or '(unknown)'}.",
        completed_at=now_iso(),
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Process Drupal LangGraph replay requests.")
    parser.add_argument("--flow-id", help="Restrict processing to one flow ID.")
    parser.add_argument("--request-id", help="Process only one request ID.")
    parser.add_argument("--limit", type=int, default=25, help="Maximum requests to process.")
    args = parser.parse_args()

    root = discover_request_root()
    if not root.exists():
        print(f"No replay request root found at {root}")
        return 0

    LOCK_PATH.parent.mkdir(parents=True, exist_ok=True)
    with LOCK_PATH.open("w", encoding="utf-8") as lock_fh:
        try:
            fcntl.flock(lock_fh.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print("Replay request worker already running.")
            return 0

        requests = pending_replay_requests(root, args.flow_id)
        if not requests:
            print("No pending replay requests found.")
            return 0

        processed = 0
        for path, payload in requests:
            if args.request_id and str(payload.get("request_id", "")) != args.request_id:
                continue
            process_request(path, payload)
            processed += 1
            print(f"Processed {payload.get('request_id', path.stem)} -> {path}")
            if processed >= max(1, args.limit):
                break

        if processed == 0:
            print("No matching replay requests were processed.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
