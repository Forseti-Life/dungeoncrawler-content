#!/usr/bin/env python3
"""Reset stale release-scoped features after a release has closed.

Usage:
  python3 scripts/cleanup-stale-release-features.py --release <release-id> [--release <release-id> ...]

For matching feature briefs:
  - if Status is `in_progress` and the feature still points at the closed release,
    clear `- Release:` and move the feature back to backlog state
  - features with an explicit defer/consolidation marker become `deferred`
  - all other stale in-progress features become `ready`
"""

from __future__ import annotations

import argparse
import re
from pathlib import Path


STATUS_RE = re.compile(r"^-\s*Status:\s*(.+)$", re.MULTILINE)
RELEASE_RE = re.compile(r"^-\s*Release:\s*(.*)$", re.MULTILINE)
DEFER_REASON_RE = re.compile(r"^-\s*Defer reason:\s*(.+)$", re.MULTILINE)
MERGED_INTO_RE = re.compile(r"^-\s*(Merged into|Consolidated into):\s*(.+)$", re.MULTILINE)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Clean stale in-progress features for closed releases.")
    parser.add_argument(
        "--release",
        dest="releases",
        action="append",
        required=True,
        help="Closed release ID whose stale in-progress features should be reset.",
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


def reset_target_status(text: str) -> str:
    if extract_value(DEFER_REASON_RE, text) or extract_value(MERGED_INTO_RE, text):
        return "deferred"
    return "ready"


def cleanup_feature(feature_md: Path, release_ids: set[str]) -> tuple[bool, str]:
    text = feature_md.read_text(encoding="utf-8")
    release_id = extract_value(RELEASE_RE, text)
    if release_id not in release_ids:
        return False, ""

    status = extract_value(STATUS_RE, text).lower()
    if status != "in_progress":
        return False, ""

    target_status = reset_target_status(text)
    updated = STATUS_RE.sub(f"- Status: {target_status}", text, count=1)
    updated = RELEASE_RE.sub("- Release:", updated, count=1)
    if updated == text:
        return False, ""

    feature_md.write_text(updated, encoding="utf-8")
    return True, f"{feature_md.parent.name}:{target_status}"


def main() -> int:
    args = parse_args()
    features_dir = Path(args.features_dir)
    if not features_dir.is_dir():
        raise SystemExit(f"Features directory not found: {features_dir}")

    release_ids = {rid.strip() for rid in args.releases if rid.strip()}
    cleaned: list[str] = []

    for feature_md in sorted(features_dir.glob("*/feature.md")):
        changed, feature_info = cleanup_feature(feature_md, release_ids)
        if changed:
            cleaned.append(feature_info)

    if cleaned:
        print("CLEANED " + ", ".join(cleaned))
    else:
        print("CLEANED none")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
