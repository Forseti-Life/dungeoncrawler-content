# KB Lesson: DC Empty Release — Auto-Close Fires Immediately When 10 Features Activated (GAP-DC-PM-AUTO-CLOSE-IMMEDIATE)

- Date: 2026-04-09
- Release: 20260409-dungeoncrawler-release-d
- Pattern: Third consecutive empty dungeoncrawler release (release-b, release-c, release-d)

## What happened
pm-dungeoncrawler activated exactly 10 features for release-d in a single scope-activate pass. The org-wide auto-close threshold is ≥10 in_progress for any site. The `release-close-now` trigger fired immediately after the 10th feature was stamped `in_progress`. Dev inbox items had been dispatched seconds before (commit `21019574d`), but auto-close had already fired — dev had zero execution time. All 10 features deferred back to `Status: ready`. Release-d closed empty.

## Root cause
PM violated its own documented ≤7 feature cap rule (pm-dungeoncrawler.instructions.md line 162). The cap of 7 exists precisely to prevent this: activating 10 = auto-close fires before dev can pick up any work.

## Why the rule was violated
The scope-activate inbox item referenced "10 deferred release-c features" and PM activated all 10 at once. The pre-activate count check (grep in_progress features before activating) was not performed.

## Prevention
1. **Always count in_progress features before activation** — see pre-activate count check added to pm-dungeoncrawler.instructions.md (HARD STOP gate).
2. **Activate at most 7 features**, regardless of how many deferred features are in the queue.
3. If a deferred backlog of 10 exists, split across two releases: activate 7, ship, then activate remaining 3 in the next cycle.

## Structural note
The auto-close threshold of 10 was intended as a release-size cap, not an instant-close mechanism. However, the orchestrator fires `release-close-now` the moment the count reaches 10 — there is no grace window. PM must treat the threshold as a hard wall, not a target.

## References
- Prior lesson (same pattern, release-c): `knowledgebase/lessons/20260409-dc-empty-release-no-dev-dispatch.md`
- PM seat instructions: `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` (scope cap section)
