# BA Coverage Sweep — PF2E Core Rulebook (Fourth Printing)

This is an **independent source-coverage sweep**, not a release-bound scan.

## Goal

Validate whether BA coverage for this source document is actually complete across:
1. `docs/dungeoncrawler/PF2requirements/source-ledger.json`
2. `docs/dungeoncrawler/PF2requirements/EXTRACTION_TRACKER.md`
3. `docs/dungeoncrawler/PF2requirements/audit/core-audit.md`
4. `docs/dungeoncrawler/PF2requirements/references/core-*.md`

## Current validation signal

- requirements_status: `complete`
- reference docs: `9` / `10`
- audit pending markers: `1670`
- audit needs-review markers: `1`

## Your task

1. Read the tracker, audit worksheet, and reference docs for `core-rulebook-4p`.
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
- Agent: ba-dungeoncrawler
- Status: pending
