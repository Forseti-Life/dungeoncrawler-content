from __future__ import annotations

import os
from pathlib import Path

DEFAULT_PRIVATE_ROOT = Path("/var/www/html/forseti/web/sites/default/files/private/drupal_langgraph")


def repo_root() -> Path:
    return Path(__file__).resolve().parent.parent


def forseti_root() -> Path:
    configured = os.environ.get("FORSETI_ROOT", "").strip()
    if configured:
        return Path(configured).expanduser()
    return repo_root().parent


def fallback_control_requests_root() -> Path:
    return forseti_root() / "tmp" / "langgraph-control-requests"


def private_artifact_root() -> Path | None:
    configured = os.environ.get("DRUPAL_LANGGRAPH_PRIVATE_ROOT", "").strip()
    if configured:
        return Path(configured).expanduser()
    if DEFAULT_PRIVATE_ROOT.exists():
        return DEFAULT_PRIVATE_ROOT
    return None


def control_requests_root() -> Path:
    private_root = private_artifact_root()
    if private_root is not None:
        return private_root / "control-requests"
    return fallback_control_requests_root()


def checkpoint_replays_root() -> Path:
    private_root = private_artifact_root()
    if private_root is not None:
        return private_root / "checkpoint-replays"
    return fallback_control_requests_root() / "checkpoint-replays"


def flow_versions_root() -> Path:
    private_root = private_artifact_root()
    if private_root is not None:
        return private_root / "flow-versions"
    return fallback_control_requests_root() / "versions"


def release_requests_root() -> Path:
    private_root = private_artifact_root()
    if private_root is not None:
        return private_root / "release-requests"
    return fallback_control_requests_root() / "release-requests"


def promoted_versions_root() -> Path:
    private_root = private_artifact_root()
    if private_root is not None:
        return private_root / "promoted-versions"
    return fallback_control_requests_root() / "promoted-versions"
