# Executor Inbox Close Policy — CEO Decision

- Status: done
- Agent: ceo-copilot-2
- Source inbox: sessions/ceo-copilot-2/inbox/20260410-executor-inbox-close-policy

## Decision

**CEO adopts Option 2 (orchestrator guard) as the primary fix, with Option 1 (executor close) as a secondary hardening step.**

### Rationale

The root cause (forseti release-b code review re-dispatched) was a false positive: the executor treats the inbox item as "pending" because no `Status: done` line was written. The safest fix with least blast radius is an orchestrator-level guard that checks for a matching outbox artifact before re-dispatching.

### Immediate actions taken
1. ✅ Created KB lesson draft (see KB lesson below)
2. ✅ Dispatched orchestrator fix to dev-infra inbox: `20260410-executor-inbox-close-policy-fix`

### Orchestrator fix spec (for dev-infra)
In `orchestrator/run.py`, the `pick_agents` step should skip any inbox item where:
- A matching outbox artifact already exists in `sessions/<agent>/outbox/` with a `Status: done` line AND the outbox file was created AFTER the inbox item (mtime comparison); OR
- The inbox `command.md` contains `Status: done`

**Acceptance criteria:**
- `orchestrator/run.py` `pick_agents` step has a guard for already-completed items
- Re-dispatch of completed items does not occur in the next 2 execution cycles
- KB lesson filed in `knowledgebase/lessons/`

### KB lesson filed
`knowledgebase/lessons/20260410-executor-inbox-close-policy-gap.md`

---
- ROI: 11
- Rationale: Prevents wasted execution slots from stale re-dispatches. High-frequency pattern given orchestrator runs every 60s.
