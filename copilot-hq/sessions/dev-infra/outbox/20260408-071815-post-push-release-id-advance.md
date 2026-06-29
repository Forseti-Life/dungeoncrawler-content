The bug: `combined_key` in Step 2 is built from the current `release_id` files. After Step 3 advances them, a second run reads the new (advanced) values and produces a different combined_key, so the `.pushed` marker check doesn't fire. 

Fix: add a per-team advancement sentinel recording `new_current`. On second run, if `current_release_id == sentinel_value`, skip (already advanced).

Now update the test's idempotency assertion to match the actual output (the inner SKIP message, not "MARKER already exists"):

The sentinel guard isn't firing. Let me inspect the actual Step 3 implementation:

The bug is clear: the sentinel check compares `current_rid == new_current` (where `new_current` was just read from the `next_release_id` file), but the test modified that file. We should compare against the **sentinel file's content** instead:

All 5 pass. Now run the orchestrator regression suite:

35/35 pass. Commit and write outbox:

Test file was already committed in the prior checkpoint. Commit `d027a67c6` contains the sentinel fix. Now write the outbox:

---

- Status: done
- Summary: Fixed `post-coordinated-push.sh` to atomically advance each team's `release_id` after a coordinated push. Root cause of the sentinel idempotency bug: the check compared `current_rid` against the freshly-read `next_release_id` value (which tests/external code can modify) instead of the sentinel's stored value. Fixed to compare `current_rid == sentinel_val`. All 5 new pytest tests pass; 35 orchestrator regression tests pass. Commits: `d027a67c6` (sentinel fix), `efb19e430` (outbox).

## ROI estimate
- ROI: 55
- Rationale: Eliminates a manual correction step that occurred during `dungeoncrawler-release-c`, making all future coordinated pushes fully atomic with cycle state.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260408-071815-post-push-release-id-advance
- Generated: 2026-04-08T07:56:08+00:00
