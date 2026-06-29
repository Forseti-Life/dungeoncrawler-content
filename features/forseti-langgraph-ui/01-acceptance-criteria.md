# Acceptance Criteria: forseti-langgraph-ui (release-g scope)

- Feature: forseti-langgraph-ui
- Release: 20260408-forseti-release-g
- Module: copilot_agent_tracker
- PM owner: pm-forseti
- Version: 1.0 — 2026-04-08
- Theme: Console Build + Test Sections — State schema visualization, node topology, eval scorecards

## KB references
- knowledgebase/lessons/ — none found specific to LangGraph UI console wiring
- Roadmap: features/forseti-langgraph-ui/roadmap.md (authoritative scope reference)

## Scope for this release

Wire the **Build** and **Test** console sections with live data from `engine.py`:
- Build > State Schema: render `LangGraphDeps` fields as a schema table
- Build > Nodes & Routing: list defined functions from `engine.py` that act as nodes/edges
- Test > Eval Scorecards: placeholder scorecard UI linking to future `agent_evaluation` module

## Definition of Done

### AC-1: Build — State Schema panel wired
- Navigating to `/admin/reports/copilot-agent-tracker/langgraph-console/build` shows a "State Schema" subsection
- The panel renders the `LangGraphDeps` TypedDict fields (`run_cmd`, `dispatch_commands_step`, `release_cycle_step`, `coordinated_push_step`, `prioritized_agents`, `health_check_step`, `now_ts`, `kpi_monitor_cmd`) as a table with columns: Field Name, Type, Description
- No PHP errors; page returns 200 for authenticated admin with `administer copilot agent tracker` permission
- Anonymous request returns 403

### AC-2: Build — Nodes & Routing panel wired
- The Build section shows a "Nodes & Routing" subsection listing the orchestrator steps/functions visible in `engine.py`
- At minimum: `run_cmd`, `dispatch_commands_step`, `health_check_step`, `release_cycle_step`, `coordinated_push_step` listed with their callable type
- No PHP errors; 200 for admin, 403 for anonymous

### AC-3: Test — Eval Scorecards placeholder
- Navigating to `/admin/reports/copilot-agent-tracker/langgraph-console/test` shows an "Eval Scorecards" subsection
- The subsection renders a placeholder table with columns: Agent, Task Type, Success Rate, Last Run — populated with static/empty-state messaging explaining that live eval data requires the `agent_evaluation` module (not yet available)
- No PHP errors; 200 for admin, 403 for anonymous

### AC-4: No regression on existing console routes
- All 7 existing console section routes still return 200 (admin) / 403 (anon):
  - `/admin/reports/copilot-agent-tracker/langgraph-console`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/build`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/test`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/run`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/observe`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/release`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/admin`
- All 7 existing telemetry dashboard routes (`/admin/reports/copilot-agent-tracker/langgraph`, `/langgraph/session`, etc.) still return 200 for admin

### AC-5: No XSS / output escaping
- All dynamically-rendered field names and values from `engine.py` are HTML-escaped before output
- Verify: PHP Codesniffer clean on modified controller files; no raw variable output in Twig templates

## Security acceptance criteria

### Authentication/permission surface
- All new Build and Test console routes require `administer copilot agent tracker` permission
- Anonymous access must return 403 on all new routes
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/build` → 403

### CSRF expectations
- These are read-only display pages (GET only); no CSRF token required
- If any form/POST is introduced, apply split-route CSRF pattern per KB lesson (forseti-jobhunter-profile CSRF fix)

### Input validation requirements
- No user-supplied input processed on these pages (read-only display of static schema data)
- File parsing (`engine.py` read) must handle missing/unreadable file gracefully (return empty-state message, not 500)

### PII/logging constraints
- No PII rendered or logged
- No `chat_log` or agent conversation content displayed
