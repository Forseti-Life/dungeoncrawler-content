from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any


MEDIUM_PLUS = {"CRITICAL", "HIGH", "MEDIUM"}


def _load_product_teams(repo_root: Path) -> list[dict[str, Any]]:
    path = repo_root / "org-chart" / "products" / "product-teams.json"
    if not path.exists():
        return []
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return []
    return list(data.get("teams") or [])


def _resolve_team_for_release(repo_root: Path, release_id: str) -> dict[str, Any] | None:
    release_id_lc = (release_id or "").lower()
    best_team: dict[str, Any] | None = None
    best_len = 0
    for team in _load_product_teams(repo_root):
        if not team.get("active", False):
            continue
        candidates = [str(team.get("id") or "").strip()]
        candidates.extend(str(a).strip() for a in (team.get("aliases") or []) if str(a).strip())
        for candidate in candidates:
            cand_lc = candidate.lower()
            if cand_lc and cand_lc in release_id_lc and len(cand_lc) > best_len:
                best_team = team
                best_len = len(cand_lc)
    return best_team


def _matching_review_files(repo_root: Path, release_id: str) -> list[Path]:
    outbox = repo_root / "sessions" / "agent-code-review" / "outbox"
    if not outbox.exists():
        return []
    matches: list[Path] = []
    for path in sorted(outbox.glob("*.md")):
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            continue
        if release_id in path.name or release_id in text:
            matches.append(path)
    return matches


def _extract_status(text: str) -> str:
    match = re.search(r"^-\s*Status:\s*(\S+)", text, re.MULTILINE | re.IGNORECASE)
    return match.group(1).lower() if match else ""


def _extract_findings(text: str) -> list[dict[str, str]]:
    findings: list[dict[str, str]] = []
    seen: set[tuple[str, str]] = set()

    for match in re.finditer(
        r"^###\s+(?P<fid>FINDING-[A-Z0-9-]+)\s+—\s+(?P<sev>CRITICAL|HIGH|MEDIUM|LOW)\b",
        text,
        re.MULTILINE,
    ):
        fid = match.group("fid").strip()
        sev = match.group("sev").strip()
        key = (fid, sev)
        if key not in seen:
            findings.append({"id": fid, "severity": sev})
            seen.add(key)

    current_section = ""
    for line in text.splitlines():
        section = re.match(r"^###\s+(CRITICAL|HIGH|MEDIUM|LOW)\b", line)
        if section:
            current_section = section.group(1)
            continue
        finding = re.match(r"^\*\*(?P<fid>(?:[CHML]-\d+|FINDING-[A-Z0-9-]+))\b", line)
        if not finding:
            continue
        fid = finding.group("fid").strip()
        sev = ""
        if fid.startswith("C-"):
            sev = "CRITICAL"
        elif fid.startswith("H-"):
            sev = "HIGH"
        elif fid.startswith("M-"):
            sev = "MEDIUM"
        elif fid.startswith("L-"):
            sev = "LOW"
        elif current_section:
            sev = current_section
        if not sev:
            continue
        key = (fid, sev)
        if key not in seen:
            findings.append({"id": fid, "severity": sev})
            seen.add(key)
    return findings


def _artifact_contains(path: Path, token: str) -> bool:
    try:
        return token in path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return False


def _is_generic_finding_id(finding_id: str) -> bool:
    return bool(re.fullmatch(r"[CHML]-\d+", finding_id))


def _finding_resolved(repo_root: Path, release_id: str, finding_id: str, team: dict[str, Any]) -> bool:
    pm_agent = str(team.get("pm_agent") or "").strip()
    dev_agent = str(team.get("dev_agent") or "").strip()
    release_slug = re.sub(r"[^A-Za-z0-9._-]+", "-", release_id).strip("-")
    generic_id = _is_generic_finding_id(finding_id)

    if pm_agent:
        risk_dir = repo_root / "sessions" / pm_agent / "artifacts" / "risk-acceptances"
        if risk_dir.exists():
            for path in risk_dir.rglob("*.md"):
                name_hit = finding_id in path.name and (not generic_id or release_slug in path.name)
                text_hit = _artifact_contains(path, finding_id) and _artifact_contains(path, release_id)
                if name_hit or text_hit:
                    return True

    if dev_agent:
        for bucket in ("inbox", "outbox"):
            base = repo_root / "sessions" / dev_agent / bucket
            if not base.exists():
                continue
            for path in base.rglob("*"):
                if finding_id in path.name and (not generic_id or release_slug in path.name):
                    return True
                if (
                    path.is_file()
                    and path.suffix == ".md"
                    and _artifact_contains(path, finding_id)
                    and (not generic_id or _artifact_contains(path, release_id))
                ):
                    return True

    return False


def unresolved_medium_plus_findings(repo_root: Path, release_id: str) -> list[dict[str, str]]:
    team = _resolve_team_for_release(repo_root, release_id)
    if team is None:
        return []

    unresolved: list[dict[str, str]] = []
    seen_ids: set[str] = set()
    for path in _matching_review_files(repo_root, release_id):
        text = path.read_text(encoding="utf-8", errors="ignore")
        status = _extract_status(text)
        if status not in {"done", "approved", "approve"}:
            continue
        for finding in _extract_findings(text):
            if finding["severity"] not in MEDIUM_PLUS:
                continue
            fid = finding["id"]
            if fid in seen_ids:
                continue
            if _finding_resolved(repo_root, release_id, fid, team):
                continue
            unresolved.append(
                {
                    "id": fid,
                    "severity": finding["severity"],
                    "source_outbox": path.name,
                }
            )
            seen_ids.add(fid)
    return unresolved
