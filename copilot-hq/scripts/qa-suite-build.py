#!/usr/bin/env python3

import argparse
import json
from pathlib import Path

from qa_suite_lib import ValidationError, build_manifest


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Build a product QA suite manifest with optional feature overlays."
    )
    parser.add_argument("--product", required=True, help="Product id, e.g. forseti")
    parser.add_argument(
        "--include-feature",
        action="append",
        default=[],
        help="Feature overlay(s) to merge into the compiled manifest.",
    )
    parser.add_argument(
        "--write",
        help="Optional file path to write the compiled manifest to. Defaults to stdout.",
    )
    args = parser.parse_args()

    try:
        manifest = build_manifest(args.product, args.include_feature)
    except ValidationError as exc:
        raise SystemExit(str(exc)) from exc

    payload = json.dumps(manifest, indent=2) + "\n"
    if args.write:
        target = Path(args.write)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(payload, encoding="utf-8")
        print(f"Wrote compiled manifest to {target}")
        return

    print(payload, end="")


if __name__ == "__main__":
    main()
