- Status: done
- Summary: Cleaned the stale forseti orphan left on the closed `20260412-forseti-release-q` boundary. `forseti-langgraph-console-observe` still had old-release `in_progress` metadata even though the live release has advanced to `20260412-forseti-release-r`. Reconciled it to `Status: ready` with the release cleared so it can be re-activated intentionally in a future release instead of poisoning current release health.

## Next actions
- Re-dispatch Observe only when PM explicitly activates it into a live release.
- Treat old release-q QA follow-ups for Observe/Admin as stale handoffs and reissue them only after re-activation.

## Blockers
- None

## Verification
- Updated `features/forseti-langgraph-console-observe/feature.md` from `in_progress` on release-q to `ready` with blank release.
- `bash scripts/ceo-release-health.sh` should no longer report `forseti-langgraph-console-observe` as an orphan on the old release.
