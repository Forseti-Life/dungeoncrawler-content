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

from orchestrator.langgraph_request_paths import (
    flow_versions_root,
    promoted_versions_root,
    release_requests_root,
)

LOCK_PATH = REPO_ROOT / "tmp" / ".langgraph-promotion-requests.lock"


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def promotion_request_root() -> Path:
    override = os.environ.get("DRUPAL_LANGGRAPH_PROMOTION_REQUEST_DIR", "").strip()
    if override:
        return Path(override)
    return release_requests_root()


def version_snapshot_root() -> Path:
    override = os.environ.get("DRUPAL_LANGGRAPH_VERSION_DIR", "").strip()
    if override:
        return Path(override)
    return flow_versions_root()


def promotion_state_root() -> Path:
    override = os.environ.get("DRUPAL_LANGGRAPH_PROMOTION_STATE_DIR", "").strip()
    if override:
        return Path(override)
    return promoted_versions_root()


def read_json(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        data = {}
    return data if isinstance(data, dict) else {}


def write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    os.replace(tmp, path)


def sanitize_slug(value: str) -> str:
    import re
    sanitized = re.sub(r"[^a-zA-Z0-9._-]+", "-", value) or "value"
    return sanitized.strip("-") or "value"


def request_paths(root: Path, flow_id: str | None) -> list[Path]:
    paths = list(root.glob(f"{flow_id}/*.json")) if flow_id else list(root.glob("*/*.json"))
    paths.sort(key=lambda path: path.stat().st_mtime, reverse=True)
    return paths


def pending_requests(root: Path, flow_id: str | None) -> list[tuple[Path, dict[str, Any]]]:
    requests: list[tuple[Path, dict[str, Any]]] = []
    for path in request_paths(root, flow_id):
        payload = read_json(path)
        if payload.get("artifact_type") != "promotion_request":
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


def release_cycle_control_state() -> dict[str, Any]:
    proc = subprocess.run(
        ["bash", "scripts/release-cycle-control.sh", "status", "--json"],
        cwd=str(REPO_ROOT),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )
    if proc.returncode != 0:
        return {"enabled": False, "error": (proc.stdout or "").strip()}
    try:
        data = json.loads(proc.stdout or "{}")
    except Exception:
        data = {}
    return data if isinstance(data, dict) else {}


def process_request(path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    flow_id = str(payload.get("flow_id", ""))
    version_id = str(payload.get("version_id", "")).strip()
    actor = str(payload.get("requested_by", "unknown"))
    payload = mark_status(path, payload, "accepted", "Promotion request accepted by executor.", accepted_at=now_iso())

    if flow_id == "" or version_id == "":
        return mark_status(path, payload, "failed", "Promotion request is missing flow or version information.", completed_at=now_iso())

    control_state = release_cycle_control_state()
    if not bool(control_state.get("enabled", True)):
        return mark_status(
            path,
            payload,
            "failed",
            "Release-cycle control is disabled; refusing promotion until release automation is re-enabled.",
            completed_at=now_iso(),
            release_cycle_control=control_state,
        )

    snapshot_path = version_snapshot_root() / flow_id / f"{sanitize_slug(version_id)}.json"
    if not snapshot_path.is_file():
        return mark_status(
            path,
            payload,
            "failed",
            f"Version snapshot {version_id} does not exist for flow {flow_id}.",
            completed_at=now_iso(),
            release_cycle_control=control_state,
        )

    payload = mark_status(path, payload, "running", "Validating promotion and recording current promoted version.", started_at=now_iso(), release_cycle_control=control_state)

    state_path = promotion_state_root() / flow_id / "current.json"
    previous_state = read_json(state_path) if state_path.is_file() else {}
    state_payload = {
        "schema_version": 1,
        "artifact_type": "promotion_state",
        "flow_id": flow_id,
        "flow_label": str(payload.get("flow_label", "")),
        "version_id": version_id,
        "promoted_at": now_iso(),
        "promoted_by": actor,
        "request_id": str(payload.get("request_id", path.stem)),
        "request_path": str(path),
        "snapshot_path": str(snapshot_path),
        "previous_version_id": previous_state.get("version_id"),
        "release_cycle_control": control_state,
    }
    write_json(state_path, state_payload)

    return mark_status(
        path,
        payload,
        "completed",
        f"Recorded {version_id} as the current promoted version.",
        completed_at=now_iso(),
        promoted_state_path=str(state_path),
        release_cycle_control=control_state,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Process Drupal LangGraph promotion requests.")
    parser.add_argument("--flow-id", help="Restrict processing to one flow ID.")
    parser.add_argument("--request-id", help="Process only one request ID.")
    parser.add_argument("--limit", type=int, default=25, help="Maximum requests to process.")
    args = parser.parse_args()

    root = promotion_request_root()
    if not root.exists():
        print(f"No promotion request root found at {root}")
        return 0

    LOCK_PATH.parent.mkdir(parents=True, exist_ok=True)
    with LOCK_PATH.open("w", encoding="utf-8") as lock_fh:
        try:
            fcntl.flock(lock_fh.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print("Promotion request worker already running.")
            return 0

        requests = pending_requests(root, args.flow_id)
        if not requests:
            print("No pending promotion requests found.")
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
            print("No matching promotion requests were processed.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
