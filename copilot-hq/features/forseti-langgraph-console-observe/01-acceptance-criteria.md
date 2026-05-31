# Acceptance Criteria — Phase 5: Observe & Monitoring

## Route & Auth

### AC-Route-1: All observe routes exist and return 200 for admin
```
GET /langgraph-console/observe → LangGraphConsoleObserveController::index() → 200 OK
GET /langgraph-console/observe/traces → LangGraphConsoleObserveController::traces() → 200 OK
GET /langgraph-console/observe/metrics → LangGraphConsoleObserveController::metrics() → 200 OK
GET /langgraph-console/observe/drift → LangGraphConsoleObserveController::drift() → 200 OK
GET /langgraph-console/observe/alerts → LangGraphConsoleObserveController::alerts() → 200 OK
GET /langgraph-console/observe/feature-progress → LangGraphConsoleObserveController::featureProgress() → 200 OK
```

### AC-Route-2: Routes require administrator role
```
GET /langgraph-console/observe/traces (no auth) → 303 redirect to /user/login
GET /langgraph-console/observe/traces (authenticated, non-admin role) → 403 Forbidden
GET /langgraph-console/observe/traces (authenticated, administrator) → 200 OK
```

### AC-Route-3: Routes use LangGraphConsoleObserveController in routing.yml
```yaml
/langgraph-console/observe:
  requirements:
    _permission: 'view content'  # inherit from parent
```

## Node Traces

### AC-Traces-1: Traces page loads with data from langgraph-ticks.jsonl
- File location: `$COPILOT_HQ_ROOT/tmp/langgraph-ticks.jsonl` (verified via DashboardController::langgraphPath())
- Parse last line of file (most recent tick)
- Extract `step_results[]` array
- Render table with rows for each step result

### AC-Traces-2: Traces table shows required columns
```
| Node/Agent ID | Timestamp | Duration (ms) | Status | Summary |
|---|---|---|---|---|
| agent-exec | 14:32:45 | 245 | ✓ | Dispatched 3 agents |
| health-check | 14:32:50 | 180 | ✓ | Parity OK |
```

### AC-Traces-3: Timestamp formatting
- Input: ISO 8601 from tick (e.g., "2026-04-20T14:32:45.123Z")
- Output: HH:MM:SS in user's local timezone
- Use Drupal's date formatter

### AC-Traces-4: Truncate summaries to 120 chars
- If step result text > 120 chars: truncate + "…"
- No HTML tags in output (strip or escape)
- Test with: result text with newlines, XML tags, emoji

### AC-Traces-5: Expandable detail view
- Click row → expand with full step result (I/O, error message if failed)
- Show: full input JSON (prettified), full output JSON, error field (if present)
- If data is large (>5KB): show warning "This result is large (12KB). Showing excerpt." + show first 2KB
- Hide/show handled via <details> element (no JS library required)

### AC-Traces-6: Empty traces fallback
- If file missing: display yellow info box "No trace data available — no ticks recorded yet."
- If file exists but `step_results` is empty: same message
- If file exists but not valid JSON: display error "Could not parse trace data. Contact admin." + log to watchdog

### AC-Traces-7: Filter by node name
- Text input field: "Filter by node/agent ID"
- Applied client-side (filter visible rows) or server-side (if > 100 steps, lazy-load)
- Clear button resets filter
- Test: filter for "agent-exec", "health-check", partial matches

### AC-Traces-8: Filter by timestamp range
- Two date-time inputs: "From" and "To"
- Parse user input, filter step_results where timestamp is within range
- If invalid date: show form error "Invalid date format"
- Test: 1-hour range, 1-day range, edge cases (same start/end time)

### AC-Traces-9: COPILOT_HQ_ROOT env check
- Before rendering, verify `COPILOT_HQ_ROOT` is available via DashboardController::langgraphPath()
- If not available: render yellow banner at top of page "⚠️ Live data unavailable: COPILOT_HQ_ROOT not configured."
- Do not crash; render fallback UI

## Runtime Metrics

### AC-Metrics-1: Metrics dashboard displays tick-level summary
```
Current Tick Metrics:
  Duration: 234 ms
  Agents Dispatched: 3
  Agents in Backlog: 0
  Concurrency Level: 3
```

### AC-Metrics-2: Data source is latest tick from langgraph-ticks.jsonl
- Parse last line (most recent tick)
- Extract fields: `timestamp`, `step_results[]` (to count steps), `selected_agents` (from context or derived)
- Derive: duration = T-end − T-start (if both available), agents_in_backlog = total_agents − selected_agents

### AC-Metrics-3: Trend chart shows last 10 ticks
```
Tick Duration Trend (last 10 ticks):
  Tick#1: 200 ms
  Tick#2: 245 ms
  Tick#3: 210 ms
  ...
  Tick#10: 230 ms ← Current
```
- Render as ASCII table or simple HTML table (no external charting library)
- Current tick highlighted in blue (#0066cc), others in grey

### AC-Metrics-4: Calculate and display rolling mean
- Mean of last 10 ticks: sum(durations) / 10
- Show: "Average duration (last 10 ticks): 225 ms"

### AC-Metrics-5: Anomaly detection — 2σ check
- If current tick > mean + 2σ:
  - Calculate: σ = stdev(last 10 ticks)
  - If current > mean + 2σ: display orange warning
  - Show: "⚠️ Slow tick detected (45% above average). Check node traces for bottlenecks."
- If current <= mean + 2σ: no warning

### AC-Metrics-6: Empty metrics fallback
- If file missing or < 1 tick: display "Metrics unavailable — no tick data yet."
- If file exists but not valid JSON: display error "Could not parse metrics. Contact admin."

### AC-Metrics-7: Performance: < 2s load time
- Tick file parsing and metrics calculation must complete in < 2s
- Cache parsed ticks in memory for same request (if multiple routes fetch same file)
- No N+1 file reads

## Drift Detection

### AC-Drift-1: Baseline calculation
- Read ALL entries from langgraph-ticks.jsonl (entire file)
- For each unique node/agent ID in `step_results[]`:
  - Collect all `step_duration` values across all ticks
  - Calculate: mean(duration), stdev(duration)
  - Store as baseline

### AC-Drift-2: Current metrics collection
- Read last 5 ticks from langgraph-ticks.jsonl
- For each node/agent in step_results:
  - Collect step_duration values from these 5 ticks

### AC-Drift-3: Variance detection
- For each node: calculate % variance = |current − baseline| / baseline × 100
- If variance > 50%: generate alert

### AC-Drift-4: Drift alert table
```
| Node | Baseline (ms) | Current (ms) | Variance | Tick# |
|---|---|---|---|---|
| health-check | 180 | 280 | +55% | #45 |
| agent-exec | 245 | 135 | -45% | #46 |
```

### AC-Drift-5: Filter by variance threshold
- Dropdown: "Show alerts with > X% variance" (50% / 75% / 100%)
- Default: 50%
- Applied client-side or server-side

### AC-Drift-6: Export CSV
- Button: "Export metrics (last 100 ticks)"
- CSV format: node,tick_num,duration_ms,baseline_ms,variance_pct
- File download with name: `langgraph-drift-export-{timestamp}.csv`

### AC-Drift-7: Empty drift page
- If file missing or < 5 ticks: display "Drift detection requires at least 5 ticks of history."
- If no alerts: display "No performance anomalies detected. System running nominal."

## Alerts & Incidents

### AC-Alerts-1: Incident list from last 24h
- Read from 3 sources:
  1. `$COPILOT_HQ_ROOT/tmp/executor-failures/*.json` — executor failures
  2. Scan `sessions/*/inbox/*/command.md` for `Status: blocked` — agent blocks
  3. Parse `$COPILOT_HQ_ROOT/orchestrator/logs/` for "tick timeout" messages (if available)

### AC-Alerts-2: Incident categories
```
Incident Type | Severity | Source | Fields |
|---|---|---|---|
| executor-failure | error | /tmp/executor-failures/*.json | agent_id, error_msg, timestamp |
| agent-blocked | warn | sessions/*/inbox/*/command.md | seat_id, item_name, timestamp, reason |
| tick-timeout | error | orchestrator logs | tick_num, duration_ms, timestamp |
```

### AC-Alerts-3: Incident table columns
```
| Timestamp | Severity | Category | Summary | Affected Agent(s) |
|---|---|---|---|---|
| 2026-04-20T14:30:00Z | error | executor-failure | Failed to execute: timeout | dev-forseti |
| 2026-04-20T14:25:30Z | warn | agent-blocked | Awaiting user input | qa-forseti |
```

### AC-Alerts-4: Parse executor-failures
- Read each file in `/tmp/executor-failures/` created in last 24h
- Extract: agent_id, error message, timestamp
- Link to agent in agent tracker UI

### AC-Alerts-5: Scan agent blocks
- Glob: `sessions/*/inbox/*/command.md`
- Read `Status:` line; if `Status: blocked` and mtime > now − 24h:
  - Extract seat ID from path (e.g., "qa-forseti" from `sessions/qa-forseti/inbox/...`)
  - Extract item name from folder name
  - Read reason from `command.md` description
  - Add to incident list

### AC-Alerts-6: Filter & search
- Filters: severity (error/warn/info), category (dropdown), date range (start/end), seat ID (text)
- Search: full-text on summary text
- Clear button resets all

### AC-Alerts-7: Sort
- Default: most recent first (timestamp DESC)
- Sortable by: timestamp, severity, category

### AC-Alerts-8: Pagination
- Display last 50 incidents (configurable in Phase 7 settings)
- If > 50: pagination controls (← Previous, Next →)

### AC-Alerts-9: Empty alerts page
- If no incidents in last 24h: display green checkmark "✓ No incidents detected."

## Feature Progress

### AC-FP-1: Embed Feature Progress dashboard
- `/langgraph-console/observe/feature-progress` displays feature progress
- Data source: `$COPILOT_HQ_ROOT/dashboards/FEATURE_PROGRESS.md` (auto-refreshed by orchestrator)
- Either embed as iframe or inline the markdown content in Twig template

### AC-FP-2: Feature summary
```
Total Features: 48
In Progress: 7
Done: 35
Blocked: 6
```

### AC-FP-3: Features grouped by phase
```
Phase 1 (Stubs): 12 done, 0 in progress, 0 blocked
Phase 2 (Build/Test): 15 done, 3 in progress, 2 blocked
Phase 3 (Run/Session): 8 done, 4 in progress, 4 blocked
...
```

### AC-FP-4: Quick links to feature detail
- Each feature row links to existing feature detail view (if available)
- Or provide link to feature file on GitHub

### AC-FP-5: Data freshness
- Show: "Last updated: 2026-04-20 14:32 UTC"
- If > 1 hour old: display yellow warning "Feature progress may be out of sync."

## Performance & Error Handling

### AC-Perf-1: Page load < 2s
- All observe routes must load and render in < 2s (measured from server start to DOM ready)
- Profile with: `time curl -s ... | wc`

### AC-Perf-2: No N+1 file reads
- Each route should read the tick file once (not once per step result)
- Cache parsed tick data in memory for same request

### AC-Perf-3: Graceful empty file handling
- Missing files: display informative message, do not crash
- Invalid JSON: log error to watchdog, display safe fallback
- Partial read errors: show what's available + error note

### AC-Perf-4: COPILOT_HQ_ROOT check
- All routes verify env var before rendering live data
- If unset: display yellow banner, render static UI only

## Security

### AC-Sec-1: No hardcoded paths
- All paths resolved via `DashboardController::langgraphPath()`
- Grep should show 0 results for hardcoded `langgraph-ticks`, `executor-failures`, `FEATURE_PROGRESS`

### AC-Sec-2: Output sanitization
- All step result text sanitized before rendering (strip tags, HTML escape)
- Truncate long strings (120 chars max for summaries)
- No raw JSON dumps in output

### AC-Sec-3: PII protection
- Do not log full tick data to watchdog (sensitive: agent inbox contents, step results)
- Render only summary data in admin-only context
- If step result contains error, truncate error message

### AC-Sec-4: No state mutations
- All routes are GET-only, read-only
- No POST, PUT, DELETE endpoints in Observe section

## Testing Checklist

- [ ] All 6 routes return 200 for admin user
- [ ] Non-admin users get 403 on all routes
- [ ] Anonymous users redirected to login
- [ ] Node traces table renders with ≥ 5 sample steps
- [ ] Trace filters work (node name, timestamp range)
- [ ] Expandable detail view works for one trace
- [ ] Metrics dashboard shows current tick summary
- [ ] Trend chart displays last 10 ticks
- [ ] Anomaly detection triggers on 2σ deviation
- [ ] Drift detection calculates baseline correctly
- [ ] Drift alerts show > 50% variance
- [ ] Drift export CSV downloads and opens
- [ ] Incident list populated from executor-failures
- [ ] Incident list populated from blocked agents
- [ ] Incident filters work (severity, category, date range)
- [ ] Feature progress dashboard loads
- [ ] Feature counts (total, in_progress, done, blocked) correct
- [ ] Page load times < 2s for all routes
- [ ] Missing COPILOT_HQ_ROOT shows warning banner
- [ ] Empty files show informative messages
- [ ] Invalid JSON shows error, does not crash
- [ ] No hardcoded paths found in grep
- [ ] No PII logged to watchdog
- [ ] All output sanitized (no raw JSON, no HTML tags)
