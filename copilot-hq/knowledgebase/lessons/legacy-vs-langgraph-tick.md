# Lesson: Legacy tick vs LangGraph tick — orchestrator architecture

- Date: 2026-04-08
- Discovered by: dev-infra (audit of dead dispatch functions)
- Fixed by: commit `fd79af602` (wire gate2-auto-approve into LangGraph tick)

## Problem

`orchestrator/run.py` originally had TWO tick flows:

1. **Legacy tick** (~lines 1600–1660 at the time): contained `_dispatch_gate2_auto_approve`,
   `_dispatch_signoff_reminders`, `_dispatch_release_close_triggers`,
   `_dispatch_scope_activate_nudge`, `_dispatch_feature_gap_remediation`.
   This block was **never called** from the active `_run_tick` path.

2. **LangGraph tick** (`_run_tick` → `_run_langgraph_tick`): the actual production path.
   At that time, it only called `_release_cycle_step`, `_dispatch_commands_step`,
   `_coordinated_push_step`, `_prioritized_agents`, `_health_check_step`, and `_kpi_monitor_step`.

Because the legacy dispatch functions were never called, `_dispatch_gate2_auto_approve` never
fired, blocking releases for days.

## Fix applied

The legacy dispatch functions were consolidated into `_health_check_step`, which IS passed to
`LangGraphDeps` and called every health-check interval via the LangGraph tick.

Current wiring (post-fix):
- `_health_check_step` calls: `_dispatch_signoff_reminders`, `_dispatch_release_close_triggers`,
  `_dispatch_gate2_auto_approve`, `_dispatch_scope_activate_nudge`,
  `_dispatch_feature_gap_remediation`, `_audit_inbox_data_quality`
- `_release_cycle_step` also calls `_dispatch_gate2_auto_approve` as a fallback

## Rule for future contributors

**Do NOT add new dispatch logic to any function outside the LangGraph node functions:**
- `_health_check_step`
- `_release_cycle_step`
- `_dispatch_commands_step`
- `_coordinated_push_step`

Any dispatch function added outside these four call sites will be **dead code** and will never
fire during normal orchestrator operation.

## Verification

To confirm a dispatch function is actually called:
```bash
grep -n "dispatch_function_name" copilot-hq/orchestrator/run.py
# Should appear in _health_check_step, _release_cycle_step, or another LangGraph node
```

To confirm the LangGraph deps wiring:
```bash
grep -n "LangGraphDeps\|_run_langgraph_tick" copilot-hq/orchestrator/run.py
# _health_check_step should appear in LangGraphDeps constructor
```
