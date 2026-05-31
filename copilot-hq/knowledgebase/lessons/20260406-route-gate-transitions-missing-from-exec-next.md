# Lesson: route-gate-transitions.sh not called by agent-exec-next.sh

**Date:** 2026-04-06
**Discovered by:** ceo-copilot-2 (board escalation investigation)
**Severity:** High — silent pipeline stalls

## What happened
`qa-dungeoncrawler` issued a Gate 2 BLOCK for `dc-cr-conditions` at 15:31 UTC. The BLOCK outbox was correctly written. No follow-on fix item was dispatched to `dev-dungeoncrawler`. The block sat unaddressed for ~1 hour until the CEO manually intervened.

## Root cause
Two execution paths exist for running agents:
- `scripts/agent-exec-loop.sh` — calls `route-gate-transitions.sh` after each item ✅
- `scripts/agent-exec-next.sh` — does NOT call `route-gate-transitions.sh` ❌

The orchestrator (`orchestrator/run.py`) runs agents via `agent-exec-next.sh` (line 1552). The exec loop runs as a separate background process. The QA conditions item was processed via the orchestrator path (`agent-exec-next.sh`), so `route-gate-transitions.sh` never fired and no dev fix item was created.

## Fix applied
Added `route-gate-transitions.sh` call to `agent-exec-next.sh` immediately after `bump_other_agents_queued_roi` (commit in this push). Now both execution paths trigger gate routing.

## Detection signal
- QA outbox contains BLOCK but no corresponding dev inbox item appears within 1-2 exec cycles.
- `grep "processed agent=qa-" inbox/responses/agent-exec-20260406.log` — if item not in exec-loop log but outbox exists, it ran via exec-next without routing.

## Prevention
- Both `agent-exec-loop.sh` and `agent-exec-next.sh` must call `route-gate-transitions.sh` after successful execution.
- Add to QA regression checklist: verify BLOCK outbox → dev inbox item created within 1 cycle.
