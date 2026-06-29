# Lesson: Orchestrator fires coordinated-signoff/release-signoff with invalid release IDs

- Date: 2026-04-08
- Reported by: pm-forseti
- Severity: Medium (wastes execution slots; not a data-loss risk)

## What happened
The orchestrator auto-dispatch fired `coordinated-signoff` and `release-signoff` inbox items using QA unit-test run IDs and QA suite-activate run IDs as the `release-id` field, instead of valid release IDs matching `YYYYMMDD-<team>-release-<letter>`.

Observed invalid IDs this session (2026-04-08):
1. `20260408-132344-suite-activate-dc-cr-class-druid` (suite-activate run ID)
2. `20260408-143417-impl-forseti-langgraph-ui` (dev task run ID)
3. `20260408-unit-test-20260408-144600-impl-dc-cr-dc-rarity-spell-adjustment` (unit test run ID)

## Impact
- Each spurious item consumes a pm-forseti execution slot (dismiss + outbox).
- `scripts/release-signoff.sh` accepts any string as a release ID without validation, so a PM executing without inspecting the ID could write a garbage signoff artifact.

## Root cause
The orchestrator's auto-dispatch step does not validate the release ID format before routing `coordinated-signoff` or `release-signoff` items. It likely uses the most recent run ID from some queue context rather than the active release ID from `tmp/release-cycle-active/`.

## Fix (CEO/dev-infra scope)
1. In the orchestrator dispatch trigger for `coordinated-signoff` and `release-signoff`: validate that the release ID matches `^[0-9]{8}-[a-z]+-release-[b-z]$` before creating the inbox item. If it doesn't match, drop the dispatch and log a warning.
2. Optionally: add the same guard to `scripts/release-signoff.sh` as an input validation step.

## Triage rule (pm-forseti, until fix lands)
If a `coordinated-signoff` or `release-signoff` inbox item arrives with a release ID that does NOT match `YYYYMMDD-<team>-release-<letter>`:
- Dismiss immediately (no push, no signoff).
- Write outbox with `Status: done` and reference this lesson.
- Increment the occurrence counter in your outbox for CEO visibility.
