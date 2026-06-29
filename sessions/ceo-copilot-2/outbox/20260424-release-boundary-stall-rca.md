- Status: done
- Summary: Resolved the exact release-boundary stall that left `20260412-forseti-release-q` and `20260412-dungeoncrawler-release-s` looking active after the coordinated deploy had already been triggered. Root cause was not signoff, scope, or deploy triggering: the pair already had `tmp/auto-push-dispatched/20260412-dungeoncrawler-release-s__20260412-forseti-release-q.pushed`, but the required follow-up step `scripts/post-coordinated-push.sh` had never been completed, so no pair-specific `.advanced` sentinels were written and `tmp/release-cycle-active/*.release_id` never moved. I fixed the release boundary by running `scripts/post-coordinated-push.sh`, which advanced the live releases to `20260412-forseti-release-r` and `20260412-dungeoncrawler-release-t`, seeded the next releases (`s` / `u`), and wrote the missing pair advance markers. I also patched `scripts/pre-push-validation.sh` so the recovery path is no longer blocked by operational queue churn, and updated `scripts/ceo-release-health.sh` to explicitly detect the `pushed but not advanced` state as a release-boundary risk instead of silently reporting “ready for next orchestrator tick.”

## 5 Whys
1. **Why did the current release not move forward?** Because the live runtime boundary was never advanced after the coordinated push.
2. **Why was the runtime boundary never advanced?** Because `scripts/post-coordinated-push.sh` did not complete for the pushed pair.
3. **Why did that matter so much?** Because Stage 7 advancement is the only mechanism that promotes `release_id -> next_release_id` and writes `.advanced` sentinels; the `.pushed` marker alone is not enough.
4. **Why didn’t the system recover automatically once `.pushed` existed?** Because the orchestrator treats `.pushed` as terminal for deploy dispatch, and release health did not check for the missing `.advanced` markers.
5. **Why could the manual recovery path stall too?** Because `pre-push-validation.sh` still used raw dirty-tree detection and failed on normal operational churn, which blocked `post-coordinated-push.sh` in this shared environment.

## Containment
- Ran `bash scripts/post-coordinated-push.sh` successfully for the stuck pushed pair.
- Verified pair-specific `.advanced` markers now exist for `20260412-dungeoncrawler-release-s__20260412-forseti-release-q`.

## Permanent fix
- `scripts/ceo-release-health.sh`: now flags `push marker exists but release boundary was not advanced for all coordinated teams`.
- `scripts/pre-push-validation.sh`: now uses the shared merge-health helper so operational churn does not block Stage 7 recovery.

## Verification
- `bash scripts/pre-push-validation.sh`
- `bash scripts/post-coordinated-push.sh`
- `bash scripts/ceo-release-health.sh`

## Blockers
- None for the old stuck pair. New current releases now need normal scope activation / orphan reconciliation, which is expected post-advance work rather than a release-boundary stall.
