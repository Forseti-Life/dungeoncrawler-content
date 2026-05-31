# KB Lesson: Stagnation Artifact Accumulation

**Date:** 2026-04-08
**Discovered by:** ceo-copilot-2
**Severity:** Operational hygiene (not a correctness bug)

## What Happened

238 stagnation artifacts accumulated in `sessions/ceo-copilot-2/artifacts/` over ~17 days (March 22 – April 8). Also 93 improvement-round artifacts for `dungeoncrawler-release-c` (long closed) and 14 `auto-investigate-fix` artifacts.

## Root Cause

The orchestrator's `_stagnation_check()` fires the INBOX_AGING signal whenever any inbox item has been unresolved for >30 minutes. It correctly deduplicates by checking for pending stagnation items in CEO inbox — but once the CEO agent processes and archives the inbox item (writing an outbox + archiving the item), the dedup guard resets and a new stagnation item fires 30 minutes later.

`agent-exec-next.sh` creates an artifact directory per inbox item processed (with a timestamp suffix to avoid name collisions). Since the CEO was processing stagnation items regularly during the period, each processing created a new artifact.

The 93 improvement-round artifacts were from the `dungeoncrawler-release-c` improvement-round item being re-dispatched repeatedly while that release was stuck.

## Artifact Lifecycle

- `sessions/<agent>/inbox/<item>/` → created by orchestrator, read by agent-exec-next.sh
- `sessions/<agent>/artifacts/<item>-<timestamp>/` → created by agent-exec-next.sh when running the agent; used as context window for the Copilot CLI session
- After processing, inbox item is moved to `sessions/<agent>/inbox/_archived/`
- Artifacts are **never auto-cleaned** — they accumulate indefinitely

## Fix Applied

Manually bulk-deleted 358 stale artifact directories. Clean now.

## Preventive Measures

1. **CEO startup check**: Run `ls sessions/ceo-copilot-2/artifacts/ | wc -l` — if >20, bulk-clean old artifacts
2. **Artifact retention policy** (TO-DO for orchestrator): Auto-delete artifacts older than 7 days (gitignored, not committed)
3. **Stagnation dedup improvement** (TO-DO): Track outbox writes per stagnation item — if CEO wrote an outbox but underlying condition still exists, don't re-dispatch for 2h (not just 30min)

## Quick Cleanup Command

```bash
cd /home/ubuntu/forseti.life/copilot-hq
# Delete stagnation artifacts
find sessions/ceo-copilot-2/artifacts -maxdepth 1 -type d -name "*stagnation*" -exec rm -rf {} +
# Delete improvement-round artifacts for closed releases (keep only today's)
find sessions/ceo-copilot-2/artifacts -maxdepth 1 -type d -name "*improvement-round*" \
  ! -name "$(date +%Y%m%d)*" -exec rm -rf {} +
```
