#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_ROOT="${1:-/home/ubuntu/forseti.life}"
LEDGER_REL="${2:-docs/dungeoncrawler/PF2requirements/source-ledger.json}"
BA_AGENT="ba-dungeoncrawler"
TODAY="$(date +%Y%m%d)"

REPORT_JSON="$(python3 scripts/ba-source-coverage-report.py "$PRODUCT_ROOT" "$LEDGER_REL" --json || true)"

REPORT_JSON="$REPORT_JSON" python3 - "$ROOT_DIR" "$PRODUCT_ROOT" "$BA_AGENT" "$TODAY" "$LEDGER_REL" <<'PY'
import json
import os
import sys
from pathlib import Path

root = Path(sys.argv[1])
product_root = Path(sys.argv[2])
agent = sys.argv[3]
today = sys.argv[4]
ledger_rel = sys.argv[5]
rows = json.loads(os.environ["REPORT_JSON"])

ledger = json.loads((product_root / ledger_rel).read_text(encoding="utf-8"))
doc_map = {doc["source_id"]: doc for doc in ledger["source_documents"]}

for row in rows:
    if row["validated_complete"]:
        continue

    source_id = row["source_id"]
    doc = doc_map[source_id]
    item_id = f"{today}-ba-coverage-sweep-{source_id}"
    inbox_dir = root / "sessions" / agent / "inbox" / item_id
    outbox_file = root / "sessions" / agent / "outbox" / f"{item_id}.md"
    if inbox_dir.exists() or outbox_file.exists():
        continue

    inbox_dir.mkdir(parents=True, exist_ok=True)
    (inbox_dir / "roi.txt").write_text("20\n", encoding="utf-8")
    command = f"""# BA Coverage Sweep — {doc['label']}

This is an **independent source-coverage sweep**, not a release-bound scan.

## Goal

Validate whether BA coverage for this source document is actually complete across:
1. `{ledger_rel}`
2. `{doc['tracker_path']}`
3. `{doc['audit_path']}`
4. `{doc['reference_prefix']}*.md`

## Current validation signal

- requirements_status: `{row['requirements_status']}`
- reference docs: `{row['reference_docs']}` / `{row['required_reference_docs']}`
- audit pending markers: `{row['audit_pending_markers']}`
- audit needs-review markers: `{row['audit_needs_review_markers']}`

## Your task

1. Read the tracker, audit worksheet, and reference docs for `{source_id}`.
2. Reconcile whether coverage is truly complete.
3. If complete:
   - update the audit worksheet so pending markers are cleared or intentionally skipped
   - update the ledger gaps/notes as needed
   - write outbox `Status: done` with explicit validation summary
4. If not complete:
   - write outbox `Status: blocked` or `Status: needs-info`
   - list the exact missing source objects / sections still needing review
   - recommend the next highest-ROI continuation pass

## Rules

- Do not treat release-cycle scan cursor state as proof of completeness.
- Do not create release-bound backlog items unless the sweep surfaces a concrete missing requirement/feature gap.
"""
    (inbox_dir / "command.md").write_text(command, encoding="utf-8")
    print(f"queued {item_id}")
PY
