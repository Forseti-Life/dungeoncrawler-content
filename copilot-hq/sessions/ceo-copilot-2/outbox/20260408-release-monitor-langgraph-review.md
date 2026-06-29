# CEO Outbox: Release Cycle Monitoring + LangGraph/GenAI Review
**Date:** 2026-04-08T14:12Z
**Triggered by:** Board request

## Release Cycle Status (as of 14:12 UTC)

| Team | Release | Features | State |
|---|---|---|---|
| forseti | release-g | 0 scoped | ⚠️ Empty — no backlog remaining |
| dungeoncrawler | release-f | 10 in_progress | 🔄 Dev implementing, QA suites activating |

**Forseti is idle**: All 13 features shipped (6 were stuck at `done` — fixed to `shipped`). No new feature briefs exist. **Board decision needed: what features should forseti.life build next?**

**DungeonCrawler is healthy**: 10 features in release-f, QA activating test suites (bard/champion done, cleric/druid in progress). 69 features in ready backlog.

## LangGraph Architecture Summary

The LangGraph graph in this org is an **orchestration scheduler**, not an AI pipeline:
- **Location**: `copilot-hq/orchestrator/runtime_graph/engine.py`
- **Type**: Linear 9-node `StateGraph` (consume_replies → dispatch → release_cycle → coordinated_push → pick_agents → exec_agents → health_check → kpi_monitor → publish)
- **No LLM in the graph itself** — the graph calls `agent-exec-next.sh` which then invokes the LLM

### Model Routing (`llm/routing.yaml`)

| Role | Configured Model | Actual Today |
|---|---|---|
| CEO, PM, Dev | copilot | ✅ GitHub Copilot CLI (Claude Sonnet 4.6) |
| QA/Tester | phi-3-mini | ⚠️ Fallback to Copilot (model not downloaded) |
| BA, Security | mistral-7b-instruct | ⚠️ Fallback to Copilot (model not downloaded) |
| Code review | deepseek-coder | ⚠️ Fallback to Copilot (model not downloaded) |
| Explore agents | phi-3-mini | ⚠️ Fallback to Copilot (model not downloaded) |

**`llm/models/` is empty** — 4 GGUF models defined in manifest but never downloaded.
All agents currently run on GitHub Copilot CLI (frontier model).

### Drupal Integration
`copilot_agent_tracker` module reads tick telemetry (JSONL) written by the orchestrator's `publish` node. No LLM calls from Drupal. The `forseti-langgraph-ui` feature is building a management console (stubs in place).

## Actions Taken This Session

1. ✅ Fixed `ceo-release-health.sh` — orphan check was timing out (130 bash subprocs → single Python call)
2. ✅ Triaged 16 orphaned features (8 shipped, 8 reset to ready)
3. ✅ Marked 6 forseti features shipped (stuck at `done`)
4. ✅ Fixed 10 QA inbox items missing `Agent:`/`Status:` fields
5. ✅ Deleted 358 stale CEO artifacts (238 stagnation + 120 improvement-round) accumulated since March 22
6. ✅ KB lesson: artifact accumulation root cause + cleanup commands + TODO items for orchestrator

## Board Decisions Needed

1. **Forseti backlog**: Team is idle — define next features for forseti.life job hunter
2. **Local models**: Download 4 GGUF models (~16GB disk) to realize routing intent, or update routing.yaml to `copilot` everywhere explicitly
