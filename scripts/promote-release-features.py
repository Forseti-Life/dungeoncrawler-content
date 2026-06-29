#!/usr/bin/env python3
"""Promote shipped release features from `done` to `shipped`.

Usage:
  python3 scripts/promote-release-features.py --release <release-id> [--release <release-id> ...]

Only features whose `feature.md` has:
  - `- Release: <release-id>`
  - `- Status: done`
are updated. Existing `shipped` statuses are left untouched.
"""

from __future__ import annotations

import argparse
import re
from pathlib import Path


STATUS_RE = re.compile(r"^-\s*Status:\s*(.+)$", re.MULTILINE)
RELEASE_RE = re.compile(r"^-\s*Release:\s*(.+)$", re.MULTILINE)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Promote shipped release features.")
    parser.add_argument(
        "--release",
        dest="releases",
        action="append",
        required=True,
        help="Release ID to promote from done -> shipped.",
    )
    parser.add_argument(
        "--features-dir",
        default=str(Path(__file__).resolve().parents[1] / "features"),
        help="Features directory (default: <repo>/features).",
    )
    return parser.parse_args()


def extract_value(pattern: re.Pattern[str], text: str) -> str:
    match = pattern.search(text)
    return match.group(1).strip() if match else ""


def promote_feature(feature_md: Path, release_ids: set[str]) -> tuple[bool, str]:
    text = feature_md.read_text(encoding="utf-8")
    release_id = extract_value(RELEASE_RE, text)
    if release_id not in release_ids:
      return False, ""

    status = extract_value(STATUS_RE, text).lower()
    if status != "done":
      return False, ""

    updated = STATUS_RE.sub("- Status: shipped", text, count=1)
    feature_md.write_text(updated, encoding="utf-8")
    return True, feature_md.parent.name


def main() -> int:
    args = parse_args()
    features_dir = Path(args.features_dir)
    if not features_dir.is_dir():
        raise SystemExit(f"Features directory not found: {features_dir}")

    release_ids = {rid.strip() for rid in args.releases if rid.strip()}
    promoted: list[str] = []

    for feature_md in sorted(features_dir.glob("*/feature.md")):
        changed, feature_id = promote_feature(feature_md, release_ids)
        if changed:
            promoted.append(feature_id)

    if promoted:
        print("PROMOTED " + ", ".join(promoted))
    else:
        print("PROMOTED none")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
