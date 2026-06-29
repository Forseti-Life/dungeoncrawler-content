#!/usr/bin/env python3

import argparse

from qa_suite_lib import ValidationError, validate_all


def main() -> None:
    parser = argparse.ArgumentParser(description="Validate QA suite manifests and feature overlays.")
    parser.add_argument("--product", help="Optional product id filter, e.g. forseti")
    parser.add_argument("--feature-id", help="Optional feature overlay filter")
    args = parser.parse_args()

    try:
        result = validate_all(product_filter=args.product, feature_filter=args.feature_id)
    except ValidationError as exc:
        raise SystemExit(str(exc)) from exc

    print(
        f"OK: validated {result['manifest_count']} suite manifest(s) and {result['overlay_count']} feature overlay(s)"
    )


if __name__ == "__main__":
    main()
