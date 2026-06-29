#!/usr/bin/env python3
from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path


ROOT = Path(os.environ.get("HQ_ROOT_DIR", Path(__file__).resolve().parents[1]))
LIB_DIR = ROOT / "scripts" / "lib"
if str(LIB_DIR) not in sys.path:
    sys.path.insert(0, str(LIB_DIR))

from code_review_gate import unresolved_medium_plus_findings  # noqa: E402


def main() -> int:
    parser = argparse.ArgumentParser(description="Check unresolved MEDIUM+ code-review findings for a release")
    parser.add_argument("release_id")
    args = parser.parse_args()

    unresolved = unresolved_medium_plus_findings(ROOT, args.release_id)
    if not unresolved:
        print(f"OK: no unresolved MEDIUM+ code-review findings for {args.release_id}")
        return 0

    print(f"BLOCKED: unresolved MEDIUM+ code-review findings for {args.release_id}")
    for finding in unresolved:
        print(f"- {finding['id']} ({finding['severity']}) from {finding['source_outbox']}")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
