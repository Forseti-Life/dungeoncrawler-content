#!/usr/bin/env python3
"""Extract PF2E Advanced Player's Guide spells into a library-row intermediary format."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from extract_core_rulebook_spells import APG_OUTPUT, APG_SOURCE, build_intermediary_records


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=APG_SOURCE, help="Path to the Advanced Player's Guide raw text file.")
    parser.add_argument("--output", type=Path, default=APG_OUTPUT, help="Path to write intermediary JSON output.")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    payload = build_intermediary_records(args.source)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {payload['record_count']} intermediary spell records to {args.output}")


if __name__ == "__main__":
    main()
