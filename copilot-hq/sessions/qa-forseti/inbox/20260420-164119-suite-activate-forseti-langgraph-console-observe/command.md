# Suite Activation: forseti-langgraph-console-observe

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-20T16:41:19+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-langgraph-console-observe"`**  
   This links the test to the living requirements doc at `features/forseti-langgraph-console-observe/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-langgraph-console-observe-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-langgraph-console-observe",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-langgraph-console-observe"`**  
   Example:
   ```json
   {
     "id": "forseti-langgraph-console-observe-<route-slug>",
     "feature_id": "forseti-langgraph-console-observe",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-langgraph-console-observe",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — Phase 5: Observe & Monitoring

**Feature:** forseti-langgraph-console-observe
**Release:** forseti-release-r (planned)
**QA Owner:** qa-forseti
**Test Phase:** Stage 3 (QA validation before dev sign-off)

---

## Test Coverage Summary

| Area | Test Type | Count | Priority | Status |
|---|---|---|---|---|
| Routes & Auth | integration | 6 | P0 | pending |
| Node Traces | unit + integration | 8 | P0 | pending |
| Runtime Metrics | unit + integration | 8 | P0 | pending |
| Drift Detection | unit + integration | 8 | P1 | pending |
| Alerts & Incidents | unit + integration | 8 | P1 | pending |
| Feature Progress | integration | 4 | P2 | pending |
| Performance & Error Handling | integration | 5 | P0 | pending |
| Security & Sanitization | integration | 6 | P0 | pending |
| **TOTAL** | | **53** | | **pending** |

---

## Test Cases (Detailed)

### Routes & Auth (6 tests)

**TC-P5-Route-1:** Observe index route responds 200 for admin
```
Preconditions: User logged in with administrator role
Steps:
  1. GET /langgraph-console/observe
Expected: 200 OK, page contains "Node Traces", "Runtime Metrics"
```

**TC-P5-Route-2:** All 6 observe routes require administrator role
```
Preconditions: Non-admin user logged in
Steps:
  1. GET /langgraph-console/observe/traces
  2. GET /langgraph-console/observe/metrics
  3. GET /langgraph-console/observe/drift
  4. GET /langgraph-console/observe/alerts
  5. GET /langgraph-console/observe/feature-progress
Expected: 403 Forbidden for all
```

**TC-P5-Route-3:** Anonymous users redirected to login
```
Preconditions: No user logged in
Steps:
  1. GET /langgraph-console/observe/traces
Expected: 303 redirect to /user/login
```

**TC-P5-Route-4:** Routes respond when COPILOT_HQ_ROOT set
```
Preconditions: Admin user, COPILOT_HQ_ROOT env var set
Steps:
  1. GET /langgraph-console/observe/traces
Expected: 200 OK
```

**TC-P5-Route-5:** Warning banner shown if COPILOT_HQ_ROOT unset
```
Preconditions: Admin user, COPILOT_HQ_ROOT env var unset
Steps:
  1. GET /langgraph-console/observe/traces
Expected: 200 OK with yellow banner: "⚠️ Live data unavailable: COPILOT_HQ_ROOT not configured"
```

**TC-P5-Route-6:** Routes have correct HTTP method
```
Preconditions: Admin user
Steps:
  1. HEAD /langgraph-console/observe/traces (should work)
  2. POST /langgraph-console/observe/traces (should fail)
Expected: HEAD 200, POST 405 Method Not Allowed
```

### Node Traces (8 tests)

**TC-P5-Traces-1:** Timeline renders with 5+ sample steps
```
Preconditions: Admin user, langgraph-ticks.jsonl exists with 5+ steps
Steps:
  1. GET /langgraph-console/observe/traces
  2. Parse response HTML for table rows
Expected: Table with ≥5 rows, each showing: node_id, timestamp, duration, status
```

**TC-P5-Traces-2:** Timestamps formatted correctly (HH:MM:SS)
```
Preconditions: Latest tick has steps at times like "2026-04-20T14:32:45.123Z"
Steps:
  1. GET /langgraph-console/observe/traces
  2. Extract timestamp from first row
Expected: Formatted as "14:32:45" (user's local timezone)
```

**TC-P5-Traces-3:** Step summaries truncated to 120 chars
```
Preconditions: Latest tick has step result with >200 char summary
Steps:
  1. GET /langgraph-console/observe/traces
  2. Check summary text length
Expected: Summary ≤ 120 chars, ends with "…" if truncated
```

**TC-P5-Traces-4:** Expandable detail view shows full step result
```
Preconditions: Admin user, at least one trace
Steps:
  1. GET /langgraph-console/observe/traces
  2. Click expand button on first row
Expected: <details> element expands, shows full input/output JSON
```

**TC-P5-Traces-5:** Filter by node name works
```
Preconditions: Multiple traces with different node names (e.g., "health-check", "agent-exec")
Steps:
  1. GET /langgraph-console/observe/traces
  2. Enter "agent-exec" in filter box
  3. Observe rows
Expected: Only rows with "agent-exec" shown, others hidden
```

**TC-P5-Traces-6:** Filter by timestamp range works
```
Preconditions: Traces from 14:30–14:40
Steps:
  1. GET /langgraph-console/observe/traces
  2. Set From = 14:32, To = 14:38
  3. Observe rows
Expected: Only traces between 14:32–14:38 shown
```

**TC-P5-Traces-7:** Clear filter button resets all
```
Preconditions: Filters applied
Steps:
  1. GET /langgraph-console/observe/traces (with filters)
  2. Click "Clear" button
Expected: All filters reset, all rows shown
```

**TC-P5-Traces-8:** Empty traces fallback message
```
Preconditions: langgraph-ticks.jsonl missing or empty
Steps:
  1. GET /langgraph-console/observe/traces
Expected: Yellow info box: "No trace data available — no ticks recorded yet."
```

### Runtime Metrics (8 tests)

**TC-P5-Metrics-1:** Dashboard displays tick summary
```
Preconditions: Admin user, latest tick available
Steps:
  1. GET /langgraph-console/observe/metrics
Expected: Shows: "Duration: X ms", "Agents Dispatched: Y", "Concurrency: Z"
```

**TC-P5-Metrics-2:** Trend chart shows last 10 ticks
```
Preconditions: ≥10 ticks in langgraph-ticks.jsonl
Steps:
  1. GET /langgraph-console/observe/metrics
Expected: Table/chart with 10 rows, last row highlighted (current tick)
```

**TC-P5-Metrics-3:** Rolling mean calculated and displayed
```
Preconditions: 10 ticks with durations: 200, 245, 210, 220, 230, 225, 215, 235, 240, 225 ms
Steps:
  1. GET /langgraph-console/observe/metrics
Expected: Average shown as 224 ms (approx)
```

**TC-P5-Metrics-4:** Anomaly detection triggers on 2σ deviation
```
Preconditions: 10 ticks with mean 225 ms, stdev 15 ms (so 2σ = 255 ms); 11th tick = 300 ms
Steps:
  1. GET /langgraph-console/observe/metrics
Expected: Orange warning shown: "⚠️ Slow tick detected (45% above average)"
```

**TC-P5-Metrics-5:** No anomaly warning if current ≤ mean+2σ
```
Preconditions: Current tick duration within 2σ
Steps:
  1. GET /langgraph-console/observe/metrics
Expected: No orange warning
```

**TC-P5-Metrics-6:** Performance: load < 2 seconds
```
Preconditions: Admin user, langgraph-ticks.jsonl present
Steps:
  1. Measure load time: curl -s -w "%{time_total}\n" https://forseti.life/langgraph-console/observe/metrics
Expected: < 2 seconds
```

**TC-P5-Metrics-7:** No N+1 file reads
```
Preconditions: Observe controller code
Steps:
  1. Add logging to count file opens during request
  2. GET /langgraph-console/observe/metrics
Expected: File opened once (tick file cached in memory)
```

**TC-P5-Metrics-8:** Empty metrics fallback
```
Preconditions: langgraph-ticks.jsonl missing
Steps:
  1. GET /langgraph-console/observe/metrics
Expected: "Metrics unavailable — no tick data yet."
```

### Drift Detection (8 tests)

**TC-P5-Drift-1:** Baseline calculated from all ticks
```
Preconditions: 20 ticks for node "health-check" with durations: [180, 185, 182, 179, 181, ...]
Steps:
  1. GET /langgraph-console/observe/drift
  2. Find baseline for health-check
Expected: Baseline ≈ 181 ms (mean)
```

**TC-P5-Drift-2:** Variance detected for ±50% change
```
Preconditions: baseline 180 ms, last 5 ticks: [280, 270, 285, 275, 280] ms (>50% higher)
Steps:
  1. GET /langgraph-console/observe/drift
Expected: Alert row for health-check with >50% variance
```

**TC-P5-Drift-3:** No alert for <50% variance
```
Preconditions: baseline 200 ms, current 220 ms (10% higher)
Steps:
  1. GET /langgraph-console/observe/drift
Expected: No alert for this node
```

**TC-P5-Drift-4:** Variance threshold filter works
```
Preconditions: 2 alerts (55% and 75% variance)
Steps:
  1. GET /langgraph-console/observe/drift
  2. Set filter: "Show alerts with > 75% variance"
Expected: Only 75% variance alert shown
```

**TC-P5-Drift-5:** Export CSV downloads
```
Preconditions: Admin user, drift data available
Steps:
  1. GET /langgraph-console/observe/drift
  2. Click "Export metrics (last 100 ticks)"
Expected: CSV file downloads with name pattern `langgraph-drift-export-*.csv`
```

**TC-P5-Drift-6:** CSV format correct
```
Preconditions: CSV downloaded
Steps:
  1. Open CSV file
  2. Verify columns: node, tick_num, duration_ms, baseline_ms, variance_pct
Expected: 100+ rows, all fields populated
```

**TC-P5-Drift-7:** Empty drift page
```
Preconditions: <5 ticks in file
Steps:
  1. GET /langgraph-console/observe/drift
Expected: "Drift detection requires at least 5 ticks of history."
```

**TC-P5-Drift-8:** Performance: baseline calc < 2s
```
Preconditions: 1000 ticks in file
Steps:
  1. Measure: curl -s -w "%{time_total}\n" https://forseti.life/langgraph-console/observe/drift
Expected: < 2 seconds
```

### Alerts & Incidents (8 tests)

**TC-P5-Alerts-1:** Executor-failures parsed and listed
```
Preconditions: tmp/executor-failures/uuid1.json, uuid2.json exist from last 24h
Steps:
  1. GET /langgraph-console/observe/alerts
Expected: Incident list includes rows from both files, category = "executor-failure"
```

**TC-P5-Alerts-2:** Agent blocks parsed from inbox
```
Preconditions: sessions/qa-forseti/inbox/item1/command.md contains "Status: blocked"
Steps:
  1. GET /langgraph-console/observe/alerts
Expected: Incident list includes row with category = "agent-blocked", affected_agent = "qa-forseti"
```

**TC-P5-Alerts-3:** Incidents older than 24h excluded
```
Preconditions: One incident from 30 days ago, one from 1 hour ago
Steps:
  1. GET /langgraph-console/observe/alerts
Expected: Only 1-hour-old incident shown
```

**TC-P5-Alerts-4:** Filter by severity
```
Preconditions: 2 error, 2 warn incidents
Steps:
  1. GET /langgraph-console/observe/alerts
  2. Filter: severity = "error"
Expected: Only 2 error incidents shown
```

**TC-P5-Alerts-5:** Search by seat ID
```
Preconditions: Incidents for dev-forseti, qa-forseti, pm-forseti
Steps:
  1. GET /langgraph-console/observe/alerts
  2. Search: "dev-forseti"
Expected: Only dev-forseti incidents shown
```

**TC-P5-Alerts-6:** Pagination works (50 per page)
```
Preconditions: 125 incidents
Steps:
  1. GET /langgraph-console/observe/alerts
  2. Click "Next" page
Expected: First page shows 50, second page shows 50, third shows 25
```

**TC-P5-Alerts-7:** Empty alerts fallback
```
Preconditions: No incidents from last 24h
Steps:
  1. GET /langgraph-console/observe/alerts
Expected: Green checkmark "✓ No incidents detected."
```

**TC-P5-Alerts-8:** Sort by timestamp DESC (most recent first)
```
Preconditions: 3 incidents at different times
Steps:
  1. GET /langgraph-console/observe/alerts
  2. Verify row order
Expected: Most recent incident in first row
```

### Feature Progress (4 tests)

**TC-P5-FP-1:** Feature progress dashboard loads
```
Preconditions: dashboards/FEATURE_PROGRESS.md exists
Steps:
  1. GET /langgraph-console/observe/feature-progress
Expected: 200 OK, page contains feature summary counts
```

**TC-P5-FP-2:** Feature counts accurate
```
Preconditions: FEATURE_PROGRESS.md shows 48 total, 35 done, 7 in_progress, 6 blocked
Steps:
  1. GET /langgraph-console/observe/feature-progress
Expected: Counts displayed match file
```

**TC-P5-FP-3:** Per-phase breakdown shown
```
Preconditions: FEATURE_PROGRESS.md organized by phases
Steps:
  1. GET /langgraph-console/observe/feature-progress
Expected: Sections for Phase 1, Phase 2, etc., with counts
```

**TC-P5-FP-4:** Data freshness indicator
```
Preconditions: FEATURE_PROGRESS.md mtime within last hour
Steps:
  1. GET /langgraph-console/observe/feature-progress
Expected: Shows "Last updated: {timestamp}" (no warning if <1h old)
```

### Performance & Error Handling (5 tests)

**TC-P5-Perf-1:** All routes load < 2 seconds
```
Preconditions: All data files present
Steps:
  1. Time each route: /observe, /observe/traces, /observe/metrics, /observe/drift, /observe/alerts, /observe/feature-progress
Expected: All < 2s
```

**TC-P5-Perf-2:** No PHP fatal errors on missing files
```
Preconditions: langgraph-ticks.jsonl deleted
Steps:
  1. GET /langgraph-console/observe/traces
Expected: 200 OK, safe fallback message (no 500 error)
```

**TC-P5-Perf-3:** Invalid JSON handled gracefully
```
Preconditions: langgraph-ticks.jsonl contains malformed JSON
Steps:
  1. GET /langgraph-console/observe/traces
Expected: Error logged to watchdog, user sees "Could not parse trace data" message
```

**TC-P5-Perf-4:** No watchdog spam on PII
```
Preconditions: Tick data contains agent seat IDs and step results
Steps:
  1. GET /langgraph-console/observe/traces (10 times)
  2. Check watchdog logs
Expected: No full tick data logged; only safe summaries
```

**TC-P5-Perf-5:** Cache parsed ticks in request
```
Preconditions: Page fetches tick data multiple times (e.g., traces + metrics)
Steps:
  1. Add file-open logging
  2. GET /langgraph-console/observe/traces?include_metrics=1 (hypothetical multi-section page)
Expected: Tick file opened once, parsed data cached in memory
```

### Security & Sanitization (6 tests)

**TC-P5-Sec-1:** No hardcoded paths (grep check)
```
Steps:
  1. grep -r "langgraph-ticks" sites/forseti/web/modules/custom/copilot_agent_tracker/src/ | grep -v "langgraphPath()"
Expected: 0 results (no hardcoded paths)
```

**TC-P5-Sec-2:** Output sanitized (no HTML tags)
```
Preconditions: Step result contains "<script>alert('xss')</script>"
Steps:
  1. GET /langgraph-console/observe/traces
  2. View page source
Expected: No <script> tag; content escaped as "&lt;script&gt;"
```

**TC-P5-Sec-3:** Long strings truncated
```
Preconditions: Step result is 500 chars
Steps:
  1. GET /langgraph-console/observe/traces
Expected: Summary truncated to ≤120 chars
```

**TC-P5-Sec-4:** No state mutations (GET-only)
```
Steps:
  1. POST /langgraph-console/observe/traces (should fail)
Expected: 405 Method Not Allowed or 403 Forbidden
```

**TC-P5-Sec-5:** CSRF token not required (read-only)
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/observe/traces (no CSRF token)
Expected: 200 OK
```

**TC-P5-Sec-6:** No PII logged to watchdog
```
Preconditions: Tick contains agent seat IDs
Steps:
  1. GET /langgraph-console/observe/traces
  2. Grep watchdog for agent IDs
Expected: Agent IDs not in watchdog (safe to check in production logs)
```

---

## Test Execution Plan

### Stage 1: Unit Testing (Dev)
- MetricsAggregator service: baseline calc, drift detection, formatting
- IncidentCollector service: file parsing, correlation logic
- Target: 20 tests, 30 minutes

### Stage 2: Integration Testing (Dev + QA)
- Route auth (6 tests)
- Data binding (trace filter, metrics trend, drift alerts)
- Error handling (empty files, invalid JSON)
- Target: 15 tests, 1 hour

### Stage 3: Manual QA Testing (QA)
- Smoke test: all routes accessible
- Data accuracy: verify tick parsing, filtering, trends
- Performance: load times <2s
- Security: no hardcoded paths, output sanitized
- Target: 10 scenarios, 2 hours

### Stage 4: Automated Suite (CI/CD)
- All 53 tests run on every commit
- Coverage target: >90%

---

## Acceptance Criteria (QA Gate)

- [x] All 53 tests passing
- [x] >90% code coverage
- [x] Page load times <2s for all routes
- [x] No PHP errors or warnings
- [x] No hardcoded paths
- [x] Output sanitized (no XSS)
- [x] CSRF tokens not required (read-only)
- [x] All edge cases handled (missing files, invalid JSON, empty data)

---

## Risk & Mitigation

| Risk | Likelihood | Mitigation |
|---|---|---|
| Slow tick file parsing | Medium | Cache in memory, set <2s timeout |
| Missing executor-failures/ dir | High | Graceful fallback: "0 executor failures" |
| Invalid JSON in ticks | Medium | Try-catch + error log, show fallback UI |
| Drift baseline with <5 ticks | Low | Check tick count, show "need more history" |
| PII logged to watchdog | Medium | Audit logger output before production |

---

## Sign-Off

| Role | Name | Date | Status |
|---|---|---|---|
| QA Owner | qa-forseti | — | pending |
| Dev Owner | dev-forseti | — | pending |
| PM Owner | pm-forseti | — | pending |

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
