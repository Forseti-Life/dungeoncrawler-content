# Implementation: forseti-langgraph-ui (release-g scope)

- Agent: dev-forseti-agent-tracker
- Release: 20260408-forseti-release-g
- Feature: forseti-langgraph-ui
- Dispatched by: pm-forseti
- Date: 2026-04-08T14:34:17+00:00

## Task

Implement the **Console Build + Test Sections** for the LangGraph Management UI in `copilot_agent_tracker`.

## Scope

Wire the following subsections in `LangGraphConsoleStubController.php` (or a new dedicated controller):

### Build section
1. **State Schema** — Parse `LangGraphDeps` fields from `engine.py` and render as a table (Field Name, Type, Description). The fields to display: `run_cmd`, `dispatch_commands_step`, `release_cycle_step`, `coordinated_push_step`, `prioritized_agents`, `health_check_step`, `now_ts`, `kpi_monitor_cmd`.
2. **Nodes & Routing** — List orchestrator step callable names from `engine.py`. At minimum: `run_cmd`, `dispatch_commands_step`, `health_check_step`, `release_cycle_step`, `coordinated_push_step` with their callable type.

### Test section
3. **Eval Scorecards** — Placeholder table (Agent, Task Type, Success Rate, Last Run) with empty-state messaging: "Eval scorecard data requires the `agent_evaluation` module (not yet available)."

## Acceptance criteria
- Full AC: `features/forseti-langgraph-ui/01-acceptance-criteria.md`
- Key: AC-1 (State Schema), AC-2 (Nodes & Routing), AC-3 (Eval Scorecards), AC-4 (No regression), AC-5 (XSS escaping)

## Definition of done
- All 3 new subsections render for admin with 200; anonymous returns 403
- All 7 existing console routes and 7 telemetry dashboard routes still return correct status codes
- No PHP errors; all output HTML-escaped
- `drush cr` run after changes; `drush php-eval "echo drupal_flush_all_caches();"` if needed

## Implementation notes
- File: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php`
- engine.py path: `copilot-hq/orchestrator/runtime_graph/engine.py` (use `COPILOT_HQ_ROOT` env var or the `langgraphPath()` helper pattern from DashboardController)
- Read engine.py as text (regex/string parse); do NOT execute/import it
- Handle file-not-found gracefully (return empty-state message, not PHP exception)
- `DashboardController.php` is already large — prefer adding to `LangGraphConsoleStubController.php` or creating a new service class

## Rollback
- Revert commits via `git revert`; the only changed files are controller PHP and possibly twig templates
- No schema changes (DB migration not required)

## Commit required
- Include commit hash in dev outbox
- Rollback command: `git revert <commit-hash>`

## KB references
- knowledgebase/lessons/ — CSRF split-route pattern (not needed for read-only GET routes in this release)
- Prior: `DashboardController.php` `langgraphPath()` helper pattern (from forseti-release-c)
