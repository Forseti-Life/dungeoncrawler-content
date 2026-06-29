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
