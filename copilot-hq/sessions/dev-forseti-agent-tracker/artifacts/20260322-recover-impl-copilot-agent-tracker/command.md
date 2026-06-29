# Dev task: Implement EXTEND items for copilot_agent_tracker (subtree recovery)

## Context
Original delegation (`20260320-recover-impl-copilot-agent-tracker`) was written to the HQ repo at commit `c759023c` but is not present in the `forseti.life/copilot-hq` subtree. This is the subtree-path recovery copy.

## Scope
Module: `web/modules/custom/copilot_agent_tracker` in `/home/keithaumiller/forseti.life`

## Work items (EXTEND)

### 1. CSRF protection on approve/dismiss endpoints
- Add CSRF token validation to any routes that perform write operations.
- Verify: `curl -X POST /copilot-agent-tracker/approve/<id>` without valid CSRF token → 403.

### 2. Upsert deduplication on agent record ingestion
- Ensure re-ingesting an existing agent updates rather than duplicates.
- Table: `copilot_agent_tracker_agents`.
- Verify: ingest same agent twice → exactly one row with updated timestamp.

### 3. `hook_uninstall` cleanup
- Implement `hook_uninstall` in `copilot_agent_tracker.install` to drop tables on module uninstall.
- Verify: `drush pm:uninstall copilot_agent_tracker` → tables absent from DB.

## Acceptance criteria (summary)
- All three EXTEND items implemented with verification steps documented.
- Module installs/uninstalls cleanly via drush.
- No regressions in the existing 22 QA suite test cases.

## Definition of done
- Code committed to `/home/keithaumiller/forseti.life` with commit hash in outbox.
- Dev outbox written at `sessions/dev-forseti-agent-tracker/outbox/<date>-recover-impl-copilot-agent-tracker/update.md` (or flat .md).

## ROI
See roi.txt (7)

## Delegated by
pm-forseti-agent-tracker — 20260322 subtree-path recovery delegation
