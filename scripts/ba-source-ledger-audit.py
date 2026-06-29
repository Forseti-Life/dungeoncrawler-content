#!/usr/bin/env python3
"""Validate the BA source-document ledger and its referenced tracking surfaces."""

from __future__ import annotations

import json
import sys
from pathlib import Path


ALLOWED = {
    "requirements_status": {"pending", "in_progress", "complete", "skipped"},
    "issue_mapping_status": {"unmapped", "partial", "mapped"},
    "feature_mapping_status": {"unmapped", "partial", "mapped"},
    "release_handoff_status": {"pending", "submitted", "triaged", "activated", "released"},
}


def main() -> int:
    repo_root = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path("/home/ubuntu/forseti.life")
    ledger_rel = (
        Path(sys.argv[2])
        if len(sys.argv) > 2
        else Path("docs/dungeoncrawler/PF2requirements/source-ledger.json")
    )
    ledger_path = repo_root / ledger_rel
    if not ledger_path.exists():
        print(f"ERROR missing ledger: {ledger_path}")
        return 1

    try:
        ledger = json.loads(ledger_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        print(f"ERROR invalid JSON in {ledger_path}: {exc}")
        return 1

    errors: list[str] = []
    seen_ids: set[str] = set()
    docs = ledger.get("source_documents")
    if not isinstance(docs, list) or not docs:
        errors.append("source_documents must be a non-empty list")
        docs = []

    for idx, doc in enumerate(docs, start=1):
        context = f"source_documents[{idx}]"
        source_id = doc.get("source_id")
        if not source_id or not isinstance(source_id, str):
            errors.append(f"{context}: missing source_id")
            continue
        if source_id in seen_ids:
            errors.append(f"{context}: duplicate source_id {source_id}")
        seen_ids.add(source_id)

        for key in ("source_path", "tracker_path", "audit_path"):
            value = doc.get(key)
            if not value or not isinstance(value, str):
                errors.append(f"{source_id}: missing {key}")
                continue
            if not (repo_root / value).exists():
                errors.append(f"{source_id}: referenced file does not exist: {value}")

        coverage = doc.get("requirements_coverage", {})
        if not isinstance(coverage, dict):
            errors.append(f"{source_id}: requirements_coverage must be an object")
            coverage = {}
        total = coverage.get("total_objects")
        complete = coverage.get("complete_objects", 0)
        skipped = coverage.get("skipped_objects", 0)
        pending = coverage.get("pending_objects", 0)
        for key, value in (
            ("total_objects", total),
            ("complete_objects", complete),
            ("skipped_objects", skipped),
            ("pending_objects", pending),
        ):
            if not isinstance(value, int) or value < 0:
                errors.append(f"{source_id}: {key} must be a non-negative integer")
        if isinstance(total, int) and isinstance(complete, int) and isinstance(skipped, int) and isinstance(pending, int):
            if complete + skipped + pending != total:
                errors.append(
                    f"{source_id}: complete + skipped + pending must equal total_objects "
                    f"({complete} + {skipped} + {pending} != {total})"
                )

        traceability = doc.get("traceability", {})
        if not isinstance(traceability, dict):
            errors.append(f"{source_id}: traceability must be an object")
            continue
        for key, allowed_values in ALLOWED.items():
            value = traceability.get(key)
            if value not in allowed_values:
                errors.append(f"{source_id}: invalid {key}={value!r}")

    if errors:
        print("BA source ledger audit FAILED")
        for error in errors:
            print(f"- {error}")
        return 1

    print(f"BA source ledger audit OK: {len(docs)} source documents validated")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
