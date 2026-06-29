from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import re
from typing import Iterable, Optional


@dataclass(frozen=True)
class Gate1bArtifact:
    path: Path
    release_id: str
    verdict: str
    kind: str


_NAME_PATTERNS: tuple[tuple[str, str], ...] = (
    ("manual-cr", "manual-cr"),
    ("code-review", "code-review"),
)

_FLOW_OUTCOME_RE = re.compile(r"^-\s*Flow outcome:\s*(.+)$", re.MULTILINE | re.IGNORECASE)
_VERDICT_RE = re.compile(r"\bVerdict:\s*(?:\*\*)?([A-Za-z][A-Za-z0-9 +/_-]*)(?:\*\*)?", re.IGNORECASE)


def _safe_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return ""


def _normalize_verdict(raw: str) -> Optional[str]:
    text = " ".join(str(raw or "").strip().upper().split())
    if not text:
        return None
    if "NO MEDIUM+ FINDINGS" in text or "APPROVE" in text or "APPROVED" in text:
        return "APPROVE"
    if "DEFERRED" in text or "WAIVED" in text:
        return "WAIVED"
    if (
        "MEDIUM+ FINDINGS PRESENT" in text
        or "GATE 1B INCOMPLETE" in text
        or "CHANGES REQUESTED" in text
        or "BLOCK" in text
        or "REJECT" in text
    ):
        return "BLOCK"
    return None


def classify_gate1b_artifact(path: Path, release_id: str) -> Optional[Gate1bArtifact]:
    if path.suffix.lower() != ".md":
        return None

    name = path.name.lower()
    kind = next((kind for pattern, kind in _NAME_PATTERNS if pattern in name), "")
    if not kind:
        return None

    content = _safe_text(path)
    if release_id not in content:
        return None

    for match in _FLOW_OUTCOME_RE.finditer(content):
        verdict = _normalize_verdict(match.group(1))
        if verdict:
            return Gate1bArtifact(path=path, release_id=release_id, verdict=verdict, kind=kind)

    for match in _VERDICT_RE.finditer(content):
        verdict = _normalize_verdict(match.group(1))
        if verdict:
            return Gate1bArtifact(path=path, release_id=release_id, verdict=verdict, kind=kind)

    return None


def iter_gate1b_artifacts(outbox_dir: Path, release_id: str) -> Iterable[Gate1bArtifact]:
    if not outbox_dir.exists():
        return []
    artifacts = []
    for path in sorted(outbox_dir.glob("*.md"), key=lambda item: item.name, reverse=True):
        artifact = classify_gate1b_artifact(path, release_id)
        if artifact is not None:
            artifacts.append(artifact)
    return artifacts


def latest_gate1b_artifact(outbox_dir: Path, release_id: str) -> Optional[Gate1bArtifact]:
    for artifact in iter_gate1b_artifacts(outbox_dir, release_id):
        return artifact
    return None


def latest_gate1b_risk_acceptance(risk_dir: Path, release_id: str) -> Optional[Path]:
    if not risk_dir.exists():
        return None
    for path in sorted(risk_dir.glob("*.md"), key=lambda item: item.name, reverse=True):
        content = _safe_text(path)
        haystack = f"{path.name}\n{content}"
        if release_id not in haystack:
            continue
        if not re.search(r"gate[\s-]*1b|code review", haystack, re.IGNORECASE):
            continue
        if not re.search(r"waiv|risk accept|accepted risk|deferred", haystack, re.IGNORECASE):
            continue
        return path
    return None
