# KB Lesson: Stale next_release_id Causes Release Bounce Loop

## Date
2026-04-08

## Symptom
Orchestrator logs repeatedly show "RELEASE-CYCLE: advance {team}" for the same old release. `sessions/*/artifacts/` floods with duplicate `improvement-round-{release-b}-{timestamp}/` directories.

## Root Cause
`post-coordinated-push.sh` advances `{team}.release_id` after a coordinated push but does **not** update `{team}.next_release_id`. The `next_release_id` file stays pointing at the previous release.

Result: on every `_release_cycle_step` tick, the orchestrator sees the current release is signed off and tries to advance to `next_release_id` (old value), calling `release-cycle-start.sh {team} old-id old-id`. The start script is idempotent (exits 0 early when the QA preflight already exists), but the orchestrator still logs "advance" and dispatches a new `improvement-round` inbox item for `old-id`.

## Fix
Manually correct the stale file:
```bash
echo "20260408-{team}-release-d" > tmp/release-cycle-active/{team}.next_release_id
```

## Permanent Fix (needed)
`post-coordinated-push.sh` must compute and write the new `next_release_id` after advancing `release_id`. See dev-infra inbox item `20260408-fix-post-push-next-release-id`.

## Cleanup
Remove the duplicate artifact dirs from git:
```bash
git rm -r --cached sessions/*/artifacts/*improvement-round-{stale-release}*/
git commit -m "chore: remove bounce-loop artifact flood"
```
