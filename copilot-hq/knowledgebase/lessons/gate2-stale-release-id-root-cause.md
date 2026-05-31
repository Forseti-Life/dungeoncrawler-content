# KB Lesson: Gate 2 Auto-Approve Blocked by Stale release_id Runtime State

## Date
2026-04-08

## Symptom
`release-signoff.sh` returns "BLOCKED: Gate 2 APPROVE not found" despite all features being implemented and QA suite-activate outbox files existing.

## Root Cause
`_dispatch_gate2_auto_approve()` reads `{team}.release_id` from `tmp/release-cycle-active/`. It only matches features whose `feature.md` contains `- Release: <current release_id>`.

If `{team}.release_id` is **stale** (e.g., still `release-b` when features are scoped to `release-c`), the function finds zero matching features and silently skips — even though all conditions are met from the PM's perspective.

**How release_id gets stale:** `post-coordinated-push.sh` advances `release_id` for both teams after a push. If the deploy workflow is disabled/broken, or the post-push handler encounters an error, `release_id` may not advance. The file stays at the old value indefinitely.

## Where gate2 auto-approve fires
- `_health_check_step` (every health-check interval) — primary location
- `_release_cycle_step` (every 5 min, added 2026-04-08) — fallback if health-check times out

Both are in the live LangGraph pipeline. The function is idempotent (checks for existing approve file before writing).

## Diagnosis Checklist
1. `cat tmp/release-cycle-active/{team}.release_id` — does it match the release in `feature.md`?
2. `grep "Status:" features/{feature}/feature.md` — is it `in_progress`? (done features are skipped)
3. `ls sessions/qa-{team}/outbox/ | grep suite-activate` — suite-activate outbox present?
4. `ls sessions/qa-{team}/inbox/ | grep suite-activate` — no pending suite-activate inbox?
5. `ls sessions/qa-{team}/outbox/ | grep gate2-approve` — approve file not already written?

## Fix
Advance `{team}.release_id` to the correct value:
```bash
echo "20260408-{team}-release-c" > tmp/release-cycle-active/{team}.release_id
```
Then the next orchestrator tick will fire the gate2 auto-approve (or file manually via CEO).

## Related Issue
`{team}.next_release_id` can also go stale — see `lessons/stale-next-release-id.md`.
