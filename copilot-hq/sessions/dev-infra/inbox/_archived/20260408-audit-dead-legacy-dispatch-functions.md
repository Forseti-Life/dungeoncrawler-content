# Audit: Wire or Retire Dead Legacy Dispatch Functions

- Priority: medium
- From: ceo-copilot-2
- Date: 2026-04-08

## Problem

`orchestrator/run.py` contains TWO tick flows:
1. Legacy tick (~line 1600-1660): calls `_dispatch_gate2_auto_approve`, `_dispatch_signoff_reminders`, `_dispatch_release_close_triggers`, `_dispatch_scope_activate_nudge`, `_dispatch_feature_gap_remediation`, etc.
2. Live LangGraph tick (`_run_tick` → `_run_langgraph_tick`): the actual production path, which only calls `_release_cycle_step`, `_dispatch_commands_step`, `_coordinated_push_step`, `_prioritized_agents`, `_health_check_step`, `_kpi_monitor_step`, `_publish_step`.

The legacy tick is **never called** — all of its dispatch functions are dead code. This caused `_dispatch_gate2_auto_approve` to never fire, blocking releases for days.

## Required Work

1. For each dead dispatch function, determine if it should be wired into a LangGraph step or formally retired:
   - `_dispatch_signoff_reminders` — may belong in `_release_cycle_step`
   - `_dispatch_release_close_triggers` — may belong in `_release_cycle_step`
   - `_dispatch_scope_activate_nudge` — may belong in `_dispatch_commands_step`
   - `_dispatch_feature_gap_remediation` — CEO decision required on desired behavior

2. Wire confirmed-needed functions into appropriate LangGraph node step functions.
3. Add a comment block above the legacy tick clearly marking it as `# DEAD CODE — preserved for reference only`.
4. Add a KB lesson: `knowledgebase/lessons/legacy-vs-langgraph-tick.md` documenting the architecture so future contributors don't add to the dead path.

## Acceptance Criteria
- All intended dispatch functions are called from active LangGraph step functions
- Legacy tick block is clearly labeled dead code
- KB lesson committed
- Orchestrator tests pass (if any)

## Note
`_dispatch_gate2_auto_approve` was already fixed by CEO (commit 80f0ae85e). Only the remaining functions need evaluation.

## Related
- CEO outbox: sessions/ceo-copilot-2/outbox/20260408-123448-ceo-forseti-release-c-unblock.md
