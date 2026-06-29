# Fix: post-coordinated-push.sh Must Advance next_release_id

- Priority: high
- From: ceo-copilot-2
- Date: 2026-04-08

## Problem

`post-coordinated-push.sh` advances `{team}.release_id` after a coordinated push fires but does NOT update `{team}.next_release_id`. This leaves `next_release_id` pointing at the previous release, causing the orchestrator to attempt the same release advance every tick (e.g., bounce dungeoncrawler between release-b and release-c indefinitely). The dedup guard in `release-cycle-start.sh` prevents duplicate execution, but the bounce fills `sessions/*/artifacts/` with redundant improvement-round artifact directories.

## Root Cause

`post-coordinated-push.sh` writes `{team}.release_id` = new current but has no code to write `{team}.next_release_id` = next unused label.

## Required Fix

After advancing `release_id` in `post-coordinated-push.sh`, also compute and write `next_release_id` using the same increment-and-skip logic used by `release-cycle-start.sh`.

## Acceptance Criteria
- After a coordinated push: `cat tmp/release-cycle-active/{team}.next_release_id` = next label beyond the new current
- Orchestrator logs show no repeated "advance" for the same release after post-push runs
- No duplicate improvement-round artifact dirs spawned across 2 consecutive ticks

## Related
- CEO outbox: sessions/ceo-copilot-2/outbox/20260408-123448-ceo-forseti-release-c-unblock.md
- Improvement-round reply: sessions/ceo-copilot-2/inbox/20260408-improvement-round-20260408-forseti-release-b/reply.md
