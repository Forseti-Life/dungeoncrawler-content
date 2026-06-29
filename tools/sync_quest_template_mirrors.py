#!/usr/bin/env python3
"""Sync per-template quest mirror files from the canonical quest template source."""

from __future__ import annotations

import json
from pathlib import Path


def main() -> int:
    repo_root = Path(__file__).resolve().parents[1]
    source_path = repo_root / "content" / "quest_templates.json"
    mirror_dir = repo_root / "templates" / "quests"

    templates = json.loads(source_path.read_text(encoding="utf-8"))
    if not isinstance(templates, list):
        raise RuntimeError("content/quest_templates.json must be a JSON array.")

    mirror_dir.mkdir(parents=True, exist_ok=True)
    expected_files: set[str] = set()

    for template in templates:
        if not isinstance(template, dict):
            raise RuntimeError("Quest template rows must be objects.")
        template_id = str(template.get("template_id", "")).strip()
        if template_id == "":
            raise RuntimeError("Each template must define template_id.")

        mirror_name = f"{template_id}.json"
        expected_files.add(mirror_name)
        mirror_path = mirror_dir / mirror_name
        mirror_path.write_text(
            json.dumps(template, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )

    for file_path in mirror_dir.glob("*.json"):
        if file_path.name not in expected_files:
            file_path.unlink()

    print(f"Synced {len(expected_files)} mirror files from canonical source.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
