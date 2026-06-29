- Status: done
- Summary: Cleaned the three stale dungeoncrawler orphans left on closed release `20260412-dungeoncrawler-release-s`. `dc-cr-ceaseless-shadows`, `dc-cr-halfling-resolve`, and `dc-cr-halfling-weapon-expertise` all had dev evidence but no clean Gate 2 completion, so they were deferred correctly by resetting them to `Status: ready` and clearing the old release assignment instead of leaving them marked `in_progress` on a closed boundary.

## Next actions
- Re-activate these features deliberately in a future live release when PM is ready to drive QA to APPROVE.
- Do not reuse the stale release-s QA handoffs; create fresh QA dispatches after re-activation.

## Blockers
- None

## Verification
- Updated the three affected feature briefs from `in_progress` on release-s to `ready` with blank release.
- `bash scripts/ceo-release-health.sh` should no longer report these three features as dungeoncrawler orphans.
