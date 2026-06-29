#!/usr/bin/env python3
"""Generate dashboards/FEATURE_PROGRESS.md from features/*/feature.md.

Run manually or from orchestrator tick to keep the dashboard current:
  python3 scripts/generate-feature-progress.py

Output: dashboards/FEATURE_PROGRESS.md
"""
from __future__ import annotations

import os
import pathlib
import re
from datetime import datetime, timezone

SCRIPT_DIR = pathlib.Path(__file__).parent
HQ_ROOT = SCRIPT_DIR.parent
FEATURES_DIR = HQ_ROOT / "features"
OUTPUT = HQ_ROOT / "dashboards" / "FEATURE_PROGRESS.md"


def _field(text: str, key: str) -> str:
    m = re.search(rf"^-\s+{re.escape(key)}:\s*(.+)$", text, re.MULTILINE)
    return m.group(1).strip() if m else ""


def collect_features() -> list[dict]:
    rows = []
    for feature_dir in sorted(FEATURES_DIR.iterdir()):
        if not feature_dir.is_dir():
            continue
        fmd = feature_dir / "feature.md"
        if not fmd.exists():
            continue
        text = fmd.read_text(encoding="utf-8")
        rows.append({
            "work_item": feature_dir.name,
            "website": _field(text, "Website"),
            "module": _field(text, "Module"),
            "status": _field(text, "Status"),
            "priority": _field(text, "Priority"),
            "pm": _field(text, "PM"),
            "dev": _field(text, "Dev"),
            "qa": _field(text, "QA"),
        })
    return rows


def render_table(rows: list[dict]) -> str:
    header = "| Work item | Website | Module | Status | Priority | PM | Dev | QA |"
    sep    = "|-----------|---------|--------|--------|----------|----|-----|----|"
    lines  = [header, sep]
    for r in rows:
        lines.append(
            f"| {r['work_item']} | {r['website']} | {r['module']} | "
            f"{r['status']} | {r['priority']} | {r['pm']} | {r['dev']} | {r['qa']} |"
        )
    return "\n".join(lines)


def main() -> None:
    rows = collect_features()
    now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    # Preserve hand-written sections below the table (everything after first H2)
    existing = OUTPUT.read_text(encoding="utf-8") if OUTPUT.exists() else ""
    suffix_match = re.search(r"^## ", existing, re.MULTILINE)
    suffix = ("\n\n" + existing[suffix_match.start():].strip()) if suffix_match else ""

    content = f"# Feature Progress\n\nGenerated: {now}\n\n{render_table(rows)}{suffix}\n"
    OUTPUT.write_text(content, encoding="utf-8")
    print(f"Generated {OUTPUT} ({len(rows)} features, {now})")


if __name__ == "__main__":
    main()
