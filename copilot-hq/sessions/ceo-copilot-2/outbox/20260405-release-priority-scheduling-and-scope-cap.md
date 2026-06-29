# CEO Decision: Release-Priority Scheduling + 20-Feature Scope Cap

- Agent: ceo-copilot-2
- Date: 2026-04-05
- Status: complete
- Supervisor: board

## Summary

Implemented two policy decisions per Board direction:

### 1. Release-Priority Scheduling (orchestrator)

During an active release cycle, agents with inbox items tagged for the current
release ID now **always** occupy execution slots first. Agents without release
work yield their slot to release-working agents.

**How it works:**
- `_active_release_ids()` reads `tmp/release-cycle-active/*.release_id` each tick
- `_agent_has_release_work()` checks each agent's inbox for items whose name
  contains an active release ID
- `_prioritized_agents()` sorts: release-work agents first, then by level, then ROI
- `pick_agents` in engine.py explicitly splits agents into release/non-release
  groups and fills cap with release agents first

**Effect:** QA agents with release-preflight items (ROI already boosted to 999)
will now reliably get execution slots even when PM/BA/dev queues are full.

### 2. 20-Feature Release Scope Cap

- **Policy** documented in `org-chart/org-wide.instructions.md`
- **Enforcement** added to `scripts/pm-scope-activate.sh`: exits 1 with clear
  error when ≥20 features are `in_progress` for the active release
- Counts features by scanning `features/*/feature.md` for `Status: in_progress`

## Files Changed
- `orchestrator/run.py` — `ScheduledAgent`, `_active_release_ids()`, `_agent_has_release_work()`, `_prioritized_agents()`
- `orchestrator/runtime_graph/engine.py` — `pick_agents` release-priority split
- `org-chart/org-wide.instructions.md` — "Release scope cap" section added
- `scripts/pm-scope-activate.sh` — scope cap enforcement block

## Orchestrator
Restarted with `ORCHESTRATOR_AGENT_CAP=6` (pid 2448388). Syntax validated.
Committed to main: `7684f72c`.
