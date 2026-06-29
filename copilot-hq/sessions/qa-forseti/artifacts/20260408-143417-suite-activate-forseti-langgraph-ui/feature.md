# Feature Brief: forseti-langgraph-ui

- Work item id: forseti-langgraph-ui
- Website: forseti.life
- Module: copilot_agent_tracker
- Status: ready
- Priority: P1
- PM owner: pm-forseti-agent-tracker
- Dev owner: dev-forseti-agent-tracker
- QA owner: qa-forseti-agent-tracker
- Architect owner: architect-copilot
- Roadmap: features/forseti-langgraph-ui/roadmap.md

## Goal

Build a full-featured LangGraph management UI embedded in the Drupal admin interface on forseti.life. This gives the Board (human) and the CEO direct visibility into and control over the LangGraph orchestrator that runs all Copilot agents for this org.

The UI has two layers:
1. **Telemetry Dashboard** — read-only observability over the running orchestrator (already operational)
2. **Management Console** — interactive control plane for the LangGraph graph lifecycle (stubs in place, wiring in progress)

## Module
`copilot_agent_tracker` — `web/modules/custom/copilot_agent_tracker/`

Key controllers:
- `src/Controller/DashboardController.php` (~4,636 lines) — telemetry dashboard
- `src/Controller/LangGraphConsoleStubController.php` — console stubs (buildout target)

## Console Sections (from LangGraphConsoleStubController)

| Section | Route | Status |
|---|---|---|
| Home | `/admin/reports/copilot-agent-tracker/langgraph-console` | stub |
| Build | `.../build` | stub |
| Test | `.../test` | stub |
| Run | `.../run` | stub |
| Observe | `.../observe` | stub |
| Release | `.../release` | stub |
| Config | `.../config` | stub |

## Telemetry Dashboard Routes (operational)

| Panel | Route | Status |
|---|---|---|
| Main Dashboard | `.../langgraph` | live |
| Session Health | `.../langgraph/session` | live |
| Feature Progress | `.../langgraph/feature-progress` | live |
| Parity Health | `.../langgraph/parity` | live |
| Release Status | `.../langgraph/release-status` | live |
| Release Notes | `.../langgraph/release-notes` | live |
| Release Triage | `.../langgraph/release-troubleshooting` | live |

## Data Sources

- `copilot-hq/inbox/responses/langgraph-ticks.jsonl` — orchestrator tick telemetry
- `copilot-hq/inbox/responses/langgraph-parity-latest.json` — parity health snapshot
- `copilot-hq/dashboards/FEATURE_PROGRESS.md` — feature flow (auto-regenerated each tick)
- `copilot-hq/orchestrator/runtime_graph/engine.py` — LangGraph graph definition

## Risks
- Console wiring requires careful read of orchestrator state without disrupting the running process
- Release control panel (canary, promotion) must have strong CSRF + permission guards before going live
- `DashboardController.php` is already large; refactor into services when adding console logic

## Latest Updates
- 2026-04-08: Project directory and roadmap created (architect-copilot)
- 2026-04-06: QA verified Phase 1 telemetry: engine_mode, provider, feature-progress all clean
- 2026-04-05: LangGraph console stubs Phase 1 — scaffold of all 7 sections with subsection maps
- 2026-04-05: LangGraph telemetry integration — DashboardController path fix, engine.py schema fix

## Security acceptance criteria
- Authentication/permission surface: All console routes (Build, Test, Run, Observe, Release, Config) require `administer copilot agent tracker` permission; anonymous returns 403
- CSRF expectations: Release-g scope is read-only GET pages; no CSRF token required. If any state-changing POST is introduced in future phases, apply split-route CSRF pattern per KB lesson
- Input validation requirements: No user-supplied input processed in release-g (read-only display of schema data from engine.py); file-not-found handled gracefully (empty-state, not 500)
- PII/logging constraints: No PII rendered or logged; no chat_log or agent conversation content displayed
