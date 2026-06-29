#!/usr/bin/env python3
"""Audit feature/project linkage so portfolio initiatives stay visible in PROJECTS.md."""

from __future__ import annotations

import os
import re
import sys
from dataclasses import dataclass
from pathlib import Path


ACTIVE_STATUSES = {
    "in_progress",
    "ready",
    "active_buildout",
    "foundation_in_place",
    "public_platform_track",
}


@dataclass
class FeatureRecord:
    path: Path
    title: str
    status: str
    project: str
    work_item_id: str
    executive_sponsor: str

    @property
    def is_active(self) -> bool:
        return self.status.lower() in ACTIVE_STATUSES

    @property
    def is_legacy_format(self) -> bool:
        return bool(self.status) and not self.work_item_id


def hq_root() -> Path:
    env_root = os.environ.get("HQ_ROOT_DIR")
    if env_root:
        return Path(env_root)
    return Path(__file__).resolve().parents[1]


def first_field(text: str, *names: str) -> str:
    for name in names:
        pattern = re.compile(
            rf"^\s*(?:-\s*)?{re.escape(name)}:\s*(.+)$",
            flags=re.IGNORECASE | re.MULTILINE,
        )
        match = pattern.search(text)
        if match:
            return match.group(1).strip()
    return ""


def parse_feature(path: Path) -> FeatureRecord:
    text = path.read_text(encoding="utf-8")
    title_match = re.search(r"^#\s+(.+)$", text, flags=re.MULTILINE)
    return FeatureRecord(
        path=path,
        title=title_match.group(1).strip() if title_match else path.parent.name,
        status=first_field(text, "Status", "status"),
        project=first_field(text, "Project", "project"),
        work_item_id=first_field(text, "Work item id", "work item id"),
        executive_sponsor=first_field(text, "Executive sponsor", "executive_sponsor"),
    )


def main() -> int:
    root = hq_root()
    features_dir = root / "features"
    projects_path = root / "dashboards" / "PROJECTS.md"

    if not features_dir.is_dir():
        print(f"FAIL  features directory not found: {features_dir}")
        return 1
    if not projects_path.is_file():
        print(f"FAIL  project registry not found: {projects_path}")
        return 1

    project_ids = set(re.findall(r"\bPROJ-\d+\b", projects_path.read_text(encoding="utf-8")))
    if not project_ids:
        print("FAIL  no PROJ ids found in project registry")
        return 1

    records = [
        parse_feature(path)
        for path in sorted(features_dir.glob("*/feature.md"))
    ]
    active_records = [record for record in records if record.is_active]

    failures = 0
    warnings = 0

    legacy_missing = [
        record
        for record in active_records
        if record.is_legacy_format and not record.project
    ]
    invalid_links = [
        record
        for record in active_records
        if record.project and record.project not in project_ids
    ]
    missing_links = [
        record
        for record in active_records
        if not record.project
    ]

    for record in legacy_missing:
        failures += 1
        sponsor = f" sponsor={record.executive_sponsor}" if record.executive_sponsor else ""
        print(
            f"FAIL  {record.path.relative_to(root)}  active legacy-format feature is missing Project link:"
            f" status={record.status}{sponsor}"
        )

    for record in invalid_links:
        failures += 1
        print(
            f"FAIL  {record.path.relative_to(root)}  references unknown project id {record.project}"
        )

    if missing_links:
        warnings += len(missing_links)
        print(
            f"WARN  {len(missing_links)} active feature(s) are missing a Project backlink;"
            " roadmap rollup currently relies on narrative project sections instead."
        )

    if failures:
        print(
            f"\nFAIL: {failures} blocking feature/project linkage issue(s);"
            f" {warnings} warning(s)."
        )
        return 1

    print(
        f"OK: {len(active_records)} active feature(s) audited against {len(project_ids)} project id(s);"
        f" {warnings} warning(s)."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
