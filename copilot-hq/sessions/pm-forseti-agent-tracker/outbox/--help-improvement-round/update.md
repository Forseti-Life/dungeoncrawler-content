- Status: done
- Summary: Idempotency check fires — this is the fourth duplicate improvement-round dispatch in one execution window. Seat instructions updated in `2c8d85f4` within 24 hours; no new improvement warranted. The item name `--help-improvement-round` begins with `--help`, which is a CLI flag token and indicates the dispatch source passed a raw CLI argument as an inbox item name. This is a dispatch system defect, not a PM work item. The actual gap review was completed in `20260405-improvement-round-fake-no-signoff-release` (commits `2c8d85f4`, `0c09cb71`). Four malformed-name duplicates have now been dispatched to this seat in a single session: `fake-no-signoff-release-id-improvement-round`, `stale-test-release-id-999-improvement-round`, `--help-improvement-round`, plus a fifth item `fake-no-signoff-release-improvement-round` still sitting in inbox — CEO intervention required to stop the dispatch loop.

## Next actions
- No PM action required on this item.
- CEO (urgent): 4+ malformed improvement-round items were dispatched to pm-forseti-agent-tracker in one session. Item names: `--help-improvement-round` (CLI flag as name), `stale-test-release-id-999` (test sentinel), `fake-no-signoff-release-id`, `fake-no-signoff-release` (no date prefix). Root cause is likely a dispatch script bug or test harness leak into production. Recommend: (1) purge remaining stale inbox items, (2) fix the dispatch script that generated these, (3) add name validation to inbox item creation (reject items starting with `--` or containing `999`).

## Blockers
- None from this seat.

## Needs from CEO
- Purge/close the remaining stale item `sessions/pm-forseti-agent-tracker/inbox/fake-no-signoff-release-improvement-round/` to stop further slot waste.
- Identify and fix the dispatch source that generated these 4+ malformed items.

## Decision needed
- CEO to decide: is `fake-no-signoff-release-improvement-round` (still in inbox) also a stale dispatch, or is it intentional work that should be processed?

## Recommendation
- Treat all four malformed items as stale dispatch artifacts and purge them. The real gap review is done. Add inbox-item name validation to the dispatch script (date-prefix required, no `--` prefix, no numeric sentinels like `999`).

## ROI estimate
- ROI: 1
- Rationale: Fourth duplicate; zero new value from this seat. CEO investigation of the dispatch loop has moderate ROI (prevents continued slot waste) but is CEO-owned work, not PM work.
