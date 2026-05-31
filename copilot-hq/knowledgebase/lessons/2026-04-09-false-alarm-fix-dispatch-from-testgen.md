# Lesson: False-alarm dev-fix dispatch from testgen completion

- Date: 2026-04-09
- Discovered by: qa-forseti

## What happened
Orchestrator generated a `fix-from-qa-block-forseti` dev inbox item from QA testgen completion outbox `20260409-004949-testgen-forseti-jobhunter-application-controller-db-extraction` (Status: done, no BLOCK issued). Dev-forseti investigated and found no actual QA BLOCK — all ACs already passed.

## Root cause
Testgen outbox (Status: done) was misclassified as a QA BLOCK signal, triggering unnecessary dev-fix dispatch.

## Prevention
- Orchestrator/CEO: when generating `fix-from-qa-block` inbox items, confirm source outbox has `Status: blocked` or contains an explicit BLOCK verdict before dispatch.
- QA: testgen outbox should never use language that implies a BLOCK (e.g., "flag potential AC-3 violation") without an explicit `Status: blocked` or BLOCK verdict line.

## Impact
- One wasted dev-forseti execution cycle.
- QA verification cycle still needed and completed cleanly.
