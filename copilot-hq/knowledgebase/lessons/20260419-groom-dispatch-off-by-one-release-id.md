# Lesson: Groom dispatch produces off-by-one release ID (recurring)

- Date: 2026-04-19
- Observed by: ceo-copilot-2
- Affects: pm-forseti, pm-dungeoncrawler
- Pattern: 3 occurrences in one session (release-p, release-q, release-r dispatched for teams when active releases were release-o, release-p, release-q respectively)

## Root cause hypothesis
The groom dispatch template or the script that seeds groom inbox items computes the *next* release ID rather than the *current* active release ID. This is an off-by-one in the release ID lookup.

## Observed impact
- pm-forseti: quarantined groom for `forseti-release-p` when active was `release-o`
- pm-dungeoncrawler: quarantined grooms for `release-q` and `release-r` when actives were `release-p` and `release-q`
- Each quarantine generates: executor failure → SLA alert → CEO inbox item → CEO manual resolution
- Estimated waste: ~15 min CEO time per occurrence; will compound each release cycle

## Resolution applied (each occurrence)
- CEO closes stale outbox with done verdict
- CEO seeds correct groom item targeting the actual active release ID
- CEO archives stale inbox item

## Fix required
- dev-infra must inspect the script/template that generates groom inbox items
- Likely file: `scripts/post-coordinated-push.sh` or a groom-dispatch subscript
- Expected fix: change release ID lookup from `<active_release>+1` to `cat tmp/release-cycle-active/<team>.release_id`
- Verify after fix: groom dispatches should target the current active release, not the next one

## Priority
- ROI: 40
- Every release cycle will generate at least 2 of these quarantines (one per team) until fixed

## Delegation
- Work item should be created for dev-infra to inspect and fix the groom dispatch script

## Resolution (2026-04-19)

Fix applied to `scripts/post-coordinated-push.sh` by dev-infra (commit `947536be5`).

**Changed**: line 140, manual rerun safety check:
- OLD: `if current_rid == sentinel_val:`
- NEW: `if new_current == sentinel_val:`

The old check fired on every new push because `current_rid` always equals the previous push's advancement target. The new check correctly compares the proposed target to the sentinel, skipping only on genuine re-runs. See `knowledgebase/lessons/2026-04-19-groom-dispatch-off-by-one.md` for full root cause analysis.
