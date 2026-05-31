# Feature Brief

- Work item id: forseti-langgraph-console-run-session
- Website: forseti.life
- Module: copilot_agent_tracker
- Project: PROJ-001
- Group Order: 3
- Group: console-ui
- Group Title: Console Routes & UI
- Group Sort: 4
- Status: shipped
- Release: 20260412-forseti-release-i
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: P1
- Source: LangGraph UI roadmap (PROJ-001, release-e theme: Console Wiring — Run + Session) 2026-04-12

## Summary

The LangGraph Console Run section (`/langgraph-console/run`) and the Session Health subsection currently render stub placeholders. This feature wires those stubs to live orchestrator tick data: Run panel reads `langgraph-ticks.jsonl` to show active thread IDs, run status, step events, blocked/needs-info agent items, and concurrency metrics; Session Health reads `parity_ok`, `provider`, and last-tick timestamp. All data is read-only. No mutations to orchestrator state are permitted from this panel.

## Goal

Deliver operational observability for the LangGraph Run/Session phases so the CEO and operators can inspect in-progress agent orchestration without reading raw JSON files.

## Acceptance criteria

### AC-1: Run — Threads & Runs
- `/langgraph-console/run` shows a table of recent run entries parsed from `COPILOT_HQ_ROOT/tmp/langgraph-ticks.jsonl` (last 20 entries)
- Each row includes: run timestamp, thread ID (if present), `selected_agents` count, tick sequence number
- If `langgraph-ticks.jsonl` does not exist or is empty: display "No run data available — start a workflow to populate this panel."
- Data is read via `DashboardController::langgraphPath()` helper (not hardcoded path)

### AC-2: Run — Stream Events
- A "Stream Events" subsection renders the `step_results` array from the most recent tick entry
- Each step result row shows: agent ID (or step name), status, and any result summary text (truncated to 120 chars)
- If `step_results` is empty: display "No step events in latest tick."

### AC-3: Run — Resume & Retry
- A "Resume & Retry" subsection lists agent inbox items currently at `Status: blocked` or `Status: needs-info` by scanning `sessions/*/inbox/*/command.md` (or the orchestrator state summary if available)
- Displays: seat ID, item folder name, status, and last-modified timestamp
- If none: "No blocked or needs-info items detected."

### AC-4: Run — Concurrency
- A "Concurrency" subsection shows: `selected_agents` count from the latest tick, and worker count derived from `pick_agents` step result (if present)
- If not available: "Concurrency data not yet available in latest tick."

### AC-5: Session Health
- The Session Health subsection (`/langgraph-console/run` or its tab/subsection) shows:
  - `parity_ok`: true/false from latest tick (green ✓ or red ✗)
  - `provider`: value from latest tick (e.g. `ShellProvider`)
  - Last tick timestamp (ISO 8601, formatted as human-readable local time)
  - Tick sequence number
- If tick file is missing: "Session health unavailable — no tick data."

### AC-6: Auth and access
- All Run/Session routes require `administrator` Drupal role (existing console route auth — no new permissions needed)
- No unauthenticated access to tick data

### AC-7: COPILOT_HQ_ROOT env availability
- Before rendering any live data, the controller verifies `COPILOT_HQ_ROOT` env var is set (via `DashboardController::langgraphPath()` existing check)
- If unset: display a yellow warning banner "Live data unavailable: COPILOT_HQ_ROOT environment variable is not configured in the web server context."

## Out of scope
- Writing/mutating any orchestrator state
- Streaming/auto-refresh (static read on page load is sufficient for this release)
- Run history beyond the last 20 ticks

## Technical notes

- Data source: `$COPILOT_HQ_ROOT/tmp/langgraph-ticks.jsonl` — line-delimited JSON, read last N lines
- Blocked/needs-info scan: read `Status:` lines from `sessions/*/inbox/*/command.md` — lightweight glob, no DB query
- All rendering is in `LangGraphConsoleStubController` or a new `LangGraphConsoleRunController` — PM preference: extend existing stub controller to minimize routing changes
- `DashboardController::langgraphPath()` already handles `COPILOT_HQ_ROOT` resolution — reuse it

## Verification

```bash
# Smoke test: load Run panel as admin
curl -s -b admin_cookies.txt https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/run | grep -i "threads\|stream events\|session health"

# Verify no hardcoded paths
grep -r "langgraph-ticks" sites/forseti/web/modules/custom/copilot_agent_tracker/src/ | grep -v "langgraphPath()"

# Verify session health block present
curl -s -b admin_cookies.txt https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/run | grep -i "parity_ok\|provider\|Session Health"
```

## Security acceptance criteria

- Authentication/permission surface: All Run/Session routes require `administrator` Drupal role (enforced by existing console route access check). No unauthenticated access to tick data or blocked-agent scan results.
- CSRF expectations: Panel is read-only (GET only). No state-mutation POST routes introduced in this feature. CSRF tokens not required.
- Input validation: No direct user input to server. File path resolution uses `DashboardController::langgraphPath()` (env-var resolved, no user-controlled path segments). Tick JSON is parsed from a trusted local file; truncate/sanitize output before rendering in Twig.
- PII/logging constraints: Tick data may contain agent seat IDs and session state. Do not log tick data to Drupal watchdog. Render output in admin-only context only.

## Dependencies

- `forseti-copilot-agent-tracker` — shipped ✓ (DB tables, telemetry API)
- `DashboardController::langgraphPath()` — shipped ✓ (COPILOT_HQ_ROOT-aware path helper)
- `LangGraphConsoleStubController` — shipped ✓ (route structure, 7 console sections)
