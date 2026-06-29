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

from orchestrator.langgraph_request_paths import control_requests_root

RUN_LOG_DIR = REPO_ROOT / "tmp" / "langgraph-runtime-request-runs"
LOCK_PATH = REPO_ROOT / "tmp" / ".langgraph-runtime-requests.lock"


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def discover_request_root() -> Path:
    override = os.environ.get("DRUPAL_LANGGRAPH_RUNTIME_REQUEST_DIR", "").strip()
    if override:
        return Path(override)

    return control_requests_root()


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


def pending_runtime_requests(root: Path, flow_id: str | None) -> list[tuple[Path, dict[str, Any]]]:
    requests: list[tuple[Path, dict[str, Any]]] = []
    for path in request_paths(root, flow_id):
        payload = read_json(path)
        if payload.get("artifact_type") != "runtime_request":
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


def supersede_older_requests(all_requests: list[tuple[Path, dict[str, Any]]]) -> None:
    latest_for_key: dict[tuple[str, str], tuple[Path, dict[str, Any]]] = {}
    for path, payload in all_requests:
        key = (str(payload.get("flow_id", "")), str(payload.get("action", "")))
        latest_for_key.setdefault(key, (path, payload))

    for path, payload in all_requests:
        key = (str(payload.get("flow_id", "")), str(payload.get("action", "")))
        latest_path, latest_payload = latest_for_key[key]
        if latest_path == path:
            continue
        mark_status(
            path,
            payload,
            "cancelled",
            f"Superseded by newer request {latest_payload.get('request_id', latest_path.stem)}.",
            superseded_by=str(latest_payload.get("request_id", latest_path.stem)),
            completed_at=now_iso(),
        )


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


def loop_status() -> str:
    rc, out = run_cmd(["bash", "scripts/orchestrator-loop.sh", "status"])
    return out if rc == 0 else f"status unavailable (rc={rc}) {out}".strip()


def process_hq_orchestrator_tick(path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    action = str(payload.get("action", ""))
    request_id = str(payload.get("request_id", path.stem))
    actor = str(payload.get("requested_by", "unknown"))
    reason = str(payload.get("reason", "")).strip()

    if action == "manual-run":
      payload = mark_status(path, payload, "running", "Running one LangGraph tick.", started_at=now_iso())
      RUN_LOG_DIR.mkdir(parents=True, exist_ok=True)
      log_path = RUN_LOG_DIR / f"{request_id}.log"
      cmd = [python_bin(), "orchestrator/run.py", "--once", "--agent-cap", os.environ.get("ORCHESTRATOR_AGENT_CAP", "6")]
      requested_mode = str(payload.get("requested_mode", "dry_run"))
      if requested_mode == "dry_run":
          cmd.append("--no-publish")
      rc, out = run_cmd(cmd)
      log_path.write_text(out + ("\n" if out and not out.endswith("\n") else ""), encoding="utf-8")
      if rc == 0:
          return mark_status(
              path,
              payload,
              "completed",
              f"Completed one orchestrator tick in {requested_mode} mode.",
              completed_at=now_iso(),
              executor="orchestrator/run.py --once",
              exit_code=rc,
              result_log_path=str(log_path),
              runtime_state=loop_status(),
          )
      return mark_status(
          path,
          payload,
          "failed",
          f"orchestrator/run.py exited with rc={rc}.",
          completed_at=now_iso(),
          executor="orchestrator/run.py --once",
          exit_code=rc,
          result_log_path=str(log_path),
          runtime_state=loop_status(),
      )

    if action == "pause-resume":
        requested_state = str(payload.get("requested_state", "")).strip().lower()
        if requested_state not in {"pause", "resume"}:
            return mark_status(path, payload, "failed", f"Unsupported requested_state: {requested_state or '(empty)'}", completed_at=now_iso())

        verb = "disable" if requested_state == "pause" else "enable"
        action_label = "Paused" if requested_state == "pause" else "Resumed"
        cmd = ["bash", "scripts/org-control.sh", verb, "--by", actor]
        cmd.extend(["--reason", f"LangGraph runtime request {request_id}: {reason or requested_state}"])
        rc, out = run_cmd(cmd)
        if rc == 0:
            return mark_status(
                path,
                payload,
                "completed",
                f"{action_label} HQ orchestrator automation via org control.",
                completed_at=now_iso(),
                executor="scripts/org-control.sh",
                exit_code=rc,
                command_output=out,
                runtime_state=loop_status(),
            )
        return mark_status(
            path,
            payload,
            "failed",
            f"org-control.sh {verb} exited with rc={rc}.",
            completed_at=now_iso(),
            executor="scripts/org-control.sh",
            exit_code=rc,
            command_output=out,
            runtime_state=loop_status(),
        )

    return mark_status(path, payload, "failed", f"Unsupported runtime action: {action}", completed_at=now_iso())


def process_request(path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    flow_id = str(payload.get("flow_id", ""))
    payload = mark_status(path, payload, "accepted", "Runtime request accepted by executor.", accepted_at=now_iso())

    if flow_id == "hq_orchestrator_tick":
        return process_hq_orchestrator_tick(path, payload)

    return mark_status(
        path,
        payload,
        "failed",
        f"No runtime executor is registered for flow {flow_id or '(unknown)'}.",
        completed_at=now_iso(),
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Process Drupal LangGraph runtime control requests.")
    parser.add_argument("--flow-id", help="Restrict processing to one flow ID.")
    parser.add_argument("--request-id", help="Process only one request ID.")
    parser.add_argument("--limit", type=int, default=25, help="Maximum requests to inspect.")
    args = parser.parse_args()

    root = discover_request_root()
    if not root.exists():
        print(f"No runtime request root found at {root}")
        return 0

    LOCK_PATH.parent.mkdir(parents=True, exist_ok=True)
    with LOCK_PATH.open("w", encoding="utf-8") as lock_fh:
        try:
            fcntl.flock(lock_fh.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print("Runtime request worker already running.")
            return 0

        requests = pending_runtime_requests(root, args.flow_id)
        if not requests:
            print("No pending runtime requests found.")
            return 0

        supersede_older_requests(requests)
        requests = pending_runtime_requests(root, args.flow_id)
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
            print("No matching runtime requests were processed.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
