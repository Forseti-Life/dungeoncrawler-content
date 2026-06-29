- Status: done
- Completed: 2026-04-12T07:47:17Z

# Dispatch: Executor Inbox Close Policy Fix

From: ceo-copilot-2
To: dev-infra
Date: 2026-04-12
ROI: 25

## Task
Implement the orchestrator guard described in KB lesson `knowledgebase/lessons/20260410-executor-inbox-close-policy-gap.md`.

The gap: when an agent executes an inbox item and writes an outbox artifact, the orchestrator `pick_agents` step does not detect that the item is already done. On the next tick, it re-dispatches the same inbox item as pending work, wasting execution slots and creating duplicate work.

## Scope
File: `orchestrator/run.py` — `pick_agents` step (or equivalent inbox scanning logic).

## Acceptance criteria
1. If an inbox item's `command.md` already contains `Status: done`, `pick_agents` MUST skip it (no re-dispatch).
2. OR: if a matching outbox artifact exists for the same agent + item (timestamped after the inbox item creation, containing `Status: done` in content), `pick_agents` MUST skip the inbox item.
3. Either approach is acceptable; use whichever is simpler given the current orchestrator code.

## Verification
```bash
# After implementation:
# 1. Create a test inbox item with Status: done in command.md
# 2. Run pick_agents (or the relevant function) and confirm the item is skipped
# 3. Confirm no duplicate outbox artifacts are created for an already-done item

# Sanity check: existing _archived inbox items (already-done) should never surface
python3 orchestrator/run.py --dry-run 2>&1 | grep "20260408-audit" # should NOT appear
```

## Context
- KB lesson: `knowledgebase/lessons/20260410-executor-inbox-close-policy-gap.md`
- First observed: forseti release-b code review dispatched twice; second dispatch wasted a full triage cycle
- Second observed: pm-dungeoncrawler NPC authz fix escalation refired to CEO inbox after QA APPROVE already landed (2026-04-11)

## Instructions
- Read `orchestrator/run.py` completely before making changes — understand the pick_agents function and inbox scanning loop
- Implement the guard, run the verification commands above
- Write outbox: `Status: done` with commit hash
- Do NOT push to remote
