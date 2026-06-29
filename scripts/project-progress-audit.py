#!/usr/bin/env python3
"""Audit active project roadmap progression SLA from dashboards/PROJECTS.md."""

from __future__ import annotations

import os
import pathlib
import re
import sys
from dataclasses import dataclass
from datetime import datetime, timezone


HQ_ROOT = pathlib.Path(
    os.environ.get("HQ_ROOT_DIR", pathlib.Path(__file__).resolve().parents[1])
).resolve()
PROJECTS_PATH = HQ_ROOT / "dashboards" / "PROJECTS.md"
SECTION_RE = re.compile(r"^## (PROJ-\d+) — (.+)$", re.MULTILINE)
@dataclass
class ProjectSection:
    project_id: str
    name: str
    fields: dict[str, str]


def parse_sections(markdown: str) -> list[ProjectSection]:
    matches = list(SECTION_RE.finditer(markdown))
    sections: list[ProjectSection] = []
    for index, match in enumerate(matches):
        start = match.end()
        end = matches[index + 1].start() if index + 1 < len(matches) else len(markdown)
        body = markdown[start:end]
        normalized: dict[str, str] = {}
        for raw_line in body.splitlines():
            line = raw_line.strip()
            if not line:
                continue
            bold_match = re.match(r"^\*\*(.+?):\*\*\s*(.+)$", line)
            plain_match = re.match(r"^([^:\n]+?):\s*(.+)$", line)
            if bold_match:
                label, value = bold_match.groups()
            elif plain_match:
                label, value = plain_match.groups()
            else:
                continue
            key = re.sub(r"\s*\([^)]+\)\s*$", "", label.strip().lower())
            normalized[key] = re.sub(r"[*`]", "", value).strip()
        fields = normalized
        sections.append(ProjectSection(match.group(1), match.group(2).strip(), fields))
    return sections


def parse_sla_days(text: str) -> int | None:
    match = re.search(r"(\d+)\s*days?", text, flags=re.IGNORECASE)
    return int(match.group(1)) if match else None


def parse_release_date(text: str) -> datetime | None:
    match = re.search(r"(20\d{6})-", text)
    if not match:
        return None
    return datetime.strptime(match.group(1), "%Y%m%d").replace(tzinfo=timezone.utc)


def evaluate(section: ProjectSection) -> tuple[str, str]:
    fields = section.fields
    current_state = fields.get("current state") or fields.get("current status")
    missing: list[str] = []
    if not current_state:
        missing.append("current state/current status")
    for field in ["last scoped release", "progress sla", "next step", "queue status"]:
        if not fields.get(field):
            missing.append(field)
    if missing:
        return "FAIL", f"missing fields: {', '.join(missing)}"

    sla_days = parse_sla_days(fields["progress sla"])
    if sla_days is None:
        return "FAIL", "progress SLA missing day threshold"

    release_date = parse_release_date(fields["last scoped release"])
    queue_status = fields["queue status"].lower()
    if release_date is None:
        if "queued" in queue_status or "active items already queued" in queue_status:
            return "WARN", "no recorded scoped release yet, but PM/agent queue exists"
        return "FAIL", "no parsable last scoped release and no active queue evidence"

    age_days = (datetime.now(timezone.utc) - release_date).days
    if age_days > sla_days:
        return "FAIL", f"last scoped release is {age_days} days old (>{sla_days}-day SLA)"

    return "OK", f"last scoped release {fields['last scoped release']} ({age_days} days old)"


def main() -> int:
    if not PROJECTS_PATH.is_file():
        print(f"FAIL  project registry not found: {PROJECTS_PATH}")
        return 1

    sections = parse_sections(PROJECTS_PATH.read_text())
    if not sections:
        print("FAIL  no PROJ sections found in project registry")
        return 1

    failures = 0
    warnings = 0
    for section in sections:
        status, reason = evaluate(section)
        if status == "FAIL":
            failures += 1
        elif status == "WARN":
            warnings += 1
        print(f"{status:<5} {section.project_id}  {section.name}  {reason}")

    if failures:
        print(f"\nFAIL: {failures} project(s) breached progression requirements; {warnings} warning(s).")
        return 1

    print(f"\nOK: {len(sections)} active project(s) audited; {warnings} warning(s).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
