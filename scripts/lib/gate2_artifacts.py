from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import re
from typing import Iterable, Optional, Sequence


_APPROVE_PATTERNS: tuple[tuple[str, str], ...] = (
    ("gate2-waiver", "waiver"),
    ("empty-release-self-cert", "empty-release-self-cert"),
    ("gate2-approve", "gate2-approve"),
)
_BLOCK_PATTERNS: tuple[tuple[str, str], ...] = (("gate2-block", "gate2-block"),)


@dataclass(frozen=True)
class Gate2Artifact:
    path: Path
    release_id: str
    verdict: str
    kind: str


def _safe_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return ""


def _has_verdict_token(content: str, verdict: str) -> bool:
    return bool(re.search(rf"\b{re.escape(verdict.upper())}\b", content.upper()))


def classify_gate2_artifact(path: Path, release_id: str) -> Optional[Gate2Artifact]:
    if path.suffix.lower() != ".md":
        return None

    content = _safe_text(path)
    if release_id not in content:
        return None

    name = path.name.lower()
    for pattern, kind in _APPROVE_PATTERNS:
        if pattern in name and _has_verdict_token(content, "APPROVE"):
            return Gate2Artifact(path=path, release_id=release_id, verdict="APPROVE", kind=kind)

    for pattern, kind in _BLOCK_PATTERNS:
        if pattern in name and _has_verdict_token(content, "BLOCK"):
            return Gate2Artifact(path=path, release_id=release_id, verdict="BLOCK", kind=kind)

    return None


def iter_gate2_artifacts(outbox_dir: Path, release_id: str) -> Iterable[Gate2Artifact]:
    if not outbox_dir.exists():
        return []
    artifacts = []
    for path in sorted(outbox_dir.glob("*.md"), key=lambda item: item.name, reverse=True):
        artifact = classify_gate2_artifact(path, release_id)
        if artifact is not None:
            artifacts.append(artifact)
    return artifacts


def latest_gate2_artifact(outbox_dir: Path, release_id: str) -> Optional[Gate2Artifact]:
    for artifact in iter_gate2_artifacts(outbox_dir, release_id):
        return artifact
    return None


def latest_gate2_artifact_with_verdict(
    outbox_dir: Path,
    release_id: str,
    verdict: str,
) -> Optional[Gate2Artifact]:
    wanted = verdict.upper()
    for artifact in iter_gate2_artifacts(outbox_dir, release_id):
        if artifact.verdict == wanted:
            return artifact
    return None


def latest_gate2_artifact_across(
    outbox_dirs: Sequence[Path],
    release_id: str,
) -> Optional[Gate2Artifact]:
    latest: Optional[Gate2Artifact] = None
    for outbox_dir in outbox_dirs:
        artifact = latest_gate2_artifact(outbox_dir, release_id)
        if artifact is None:
            continue
        if latest is None or artifact.path.name > latest.path.name:
            latest = artifact
    return latest
