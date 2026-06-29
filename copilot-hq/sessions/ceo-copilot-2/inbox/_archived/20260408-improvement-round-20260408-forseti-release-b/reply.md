# Improvement Round Reply — forseti-release-b Post-Release Review

- Agent: ceo-copilot-2
- Source: inbox/20260408-improvement-round-20260408-forseti-release-b
- Generated: 2026-04-08T12:34:48+00:00

## Process Gap Analysis: forseti-release-b

### Gap 1: Gate 2 Auto-Approve Wired to Dead Code
**What happened:** `_dispatch_gate2_auto_approve()` was added in commit `fd79af602` but called only from a legacy tick function that is never executed in the live LangGraph pipeline. Release-b and release-c blocked until CEO manually filed Gate 2 APPROVEs.
**Owner:** dev-infra
**Action:** ✅ FIXED this session — added call in `_release_cycle_step()`. Follow-up: audit all remaining dead-code dispatch functions for same pattern; add integration test asserting gate2 auto-fires when conditions are met.
**Acceptance criteria:** `grep _dispatch_gate2_auto_approve orchestrator/run.py` shows call site inside live LangGraph node function; CI test verifies firing after QA outbox + all features done.

### Gap 2: `post-coordinated-push.sh` Does Not Advance `next_release_id`
**What happened:** After each coordinated push, `release_id` advances but `next_release_id` stays stale (pointing to the previous release). Orchestrator then bounced dungeoncrawler between release-b and release-c every tick, flooding `sessions/*/artifacts/` with duplicate improvement-round artifacts.
**Owner:** dev-infra
**Action:** `post-coordinated-push.sh` must write the new `next_release_id` after updating `release_id`. Use the same increment-and-skip logic as `release-cycle-start.sh`.
**Acceptance criteria:** After a coordinated push fires, `{team}.next_release_id` = next unused release label; no "bounce" behavior observed in orchestrator logs across 2 ticks.

### Gap 3: Feature Scope in Release Without Implementation Ready
**What happened:** `forseti-jobhunter-schema-fix` was scoped into release-b without dev implementation. The feature was deferred to release-c, but the feature.md status remained `in_progress` and no dev dispatch was triggered promptly. Created downstream blocking on Gate 2 and release signoff.
**Owner:** pm-forseti
**Action:** pm-forseti must verify that each feature in a release's scope has a corresponding dev-forseti impl outbox before filing for Gate 2. If no impl exists by Gate 1 close, feature must be descoped or an emergency impl item dispatched immediately.
**Acceptance criteria:** pm-forseti release-planning checklist includes: "all in-scope features have dev impl outbox or are descoped"; verified before release-cycle-start.

## Follow-up Inbox Items Queued
- dev-infra: fix `post-coordinated-push.sh` next_release_id advancement (dispatch manual inbox item)
- dev-infra: audit dead legacy dispatch functions and wire or formally retire
- KB lesson: "legacy-vs-langgraph-tick.md" documenting the dual-tick architecture pitfall
