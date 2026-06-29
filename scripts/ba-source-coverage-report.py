#!/usr/bin/env python3
"""Report whether BA source-document coverage is actually validated as complete."""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("repo_root", nargs="?", default="/home/ubuntu/forseti.life")
    parser.add_argument(
        "ledger_path",
        nargs="?",
        default="docs/dungeoncrawler/PF2requirements/source-ledger.json",
    )
    parser.add_argument("--json", action="store_true", dest="as_json")
    return parser.parse_args()


def count_reference_docs(repo_root: Path, reference_prefix: str) -> int:
    prefix_path = Path(reference_prefix)
    parent = repo_root / prefix_path.parent
    name_prefix = prefix_path.name
    if not parent.exists():
        return 0
    return sum(1 for path in parent.glob("*.md") if path.name.startswith(name_prefix))


def audit_counts(audit_path: Path) -> tuple[int, int]:
    text = audit_path.read_text(encoding="utf-8")
    pending = len(re.findall(r"(?m)^(?:##\s+|- \s*)\[\s\]", text))
    needs_review = text.count("[!]")
    return pending, needs_review


def main() -> int:
    args = parse_args()
    repo_root = Path(args.repo_root).resolve()
    ledger_path = (repo_root / args.ledger_path).resolve()

    ledger = json.loads(ledger_path.read_text(encoding="utf-8"))
    results = []

    for doc in ledger.get("source_documents", []):
        coverage = doc["requirements_coverage"]
        complete_objects = coverage["complete_objects"]
        skipped_objects = coverage["skipped_objects"]
        required_reference_docs = max(complete_objects, 0)
        ref_count = count_reference_docs(repo_root, doc["reference_prefix"])
        audit_path = repo_root / doc["audit_path"]
        pending, needs_review = audit_counts(audit_path)
        requirements_status = doc["traceability"]["requirements_status"]

        validated = (
            requirements_status == "complete"
            and pending == 0
            and needs_review == 0
            and ref_count >= required_reference_docs
        )
        results.append(
            {
                "source_id": doc["source_id"],
                "label": doc["label"],
                "validated_complete": validated,
                "requirements_status": requirements_status,
                "reference_docs": ref_count,
                "required_reference_docs": required_reference_docs,
                "audit_pending_markers": pending,
                "audit_needs_review_markers": needs_review,
            }
        )

    if args.as_json:
        print(json.dumps(results, indent=2))
    else:
        for row in results:
            status = "VALIDATED" if row["validated_complete"] else "NEEDS_REVIEW"
            print(
                f"{status}\t{row['source_id']}\trefs={row['reference_docs']}/{row['required_reference_docs']}"
                f"\tpending={row['audit_pending_markers']}\tneeds_review={row['audit_needs_review_markers']}"
            )

    return 0 if all(row["validated_complete"] for row in results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
