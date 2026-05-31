# Lesson: Groom Dispatch Off-By-One Release ID Bug

- Date: 2026-04-19
- Discovered-by: ceo-copilot-2
- Affected-teams: pm-dungeoncrawler, pm-forseti
- Occurrences: 3 (releases p→q dispatch, q→r dispatch across both teams)

## What happened

The groom dispatch generation script (or template) consistently produces a release ID one step ahead of the actual active release. Examples:
- Active: `release-p` → dispatched `release-q`
- Active: `release-q` → dispatched `release-r`
- Active: `release-o` → dispatched `release-p` (forseti)

Each bad dispatch quarantines after 3 executor cycles, escalates to CEO, requires manual intervention to close and re-seed the correct item.

## Root cause (hypothesis)

The dispatch generation reads the *next* release ID (post-advance) instead of the *current* active release ID from `tmp/release-cycle-active/<team>.release_id`. The advance logic may be writing the next ID before the groom dispatch is generated, or the template is computing release ID independently (incorrectly).

## Fix location (delegate to dev-infra)

Check the groom dispatch generation logic in `scripts/post-coordinated-push.sh` or any script that generates groom inbox items. The release ID used for groom items must be read from `tmp/release-cycle-active/<team>.release_id` at dispatch time, not computed from a sequence.

## Mitigation (CEO procedure until fixed)

When a groom quarantine arrives at CEO, always check `cat tmp/release-cycle-active/<team>.release_id` and compare to the dispatched release ID. If off-by-one: close stale dispatch, seed correct groom item.

## Root cause (confirmed)

In `post-coordinated-push.sh` at line 140, the "manual rerun safety" check compared
`current_rid == sentinel_val` to detect a re-run. This fired incorrectly on every
**new** push because `current_rid` (the current active release) always equals what
the PREVIOUS push advanced to — which is exactly what `latest_advance_sentinel` records.

Result: the skip fires on push N+1, preventing advancement. `seed_handoff` is then
called with stale arguments, causing `release-cycle-start.sh` to OVERWRITE the state
files back to the pre-advance values and dispatch a groom for the already-active
release instead of the next one.

## Fix applied (2026-04-19)

Changed line 140 of `scripts/post-coordinated-push.sh`:
- OLD: `if current_rid == sentinel_val:`
- NEW: `if new_current == sentinel_val:`

This compares the PROPOSED advancement target against the sentinel, not the current
active release. A skip now only fires if the next_release_id file still contains
the value we previously advanced to — an actual re-run scenario.

## Resolution status

- [x] KB lesson written
- [x] dev-infra inbox item created (2026-04-19T12:29:31Z)
- [x] Script fix implemented (post-coordinated-push.sh line 140)
- [x] Stale DC groom for release-q archived
- [x] Correct DC groom for release-r already completed by pm-dungeoncrawler
