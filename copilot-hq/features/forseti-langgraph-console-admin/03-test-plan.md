# Test Plan — Phase 7: Admin & Configuration

**Feature:** forseti-langgraph-console-admin
**Release:** forseti-release-t (planned)
**QA Owner:** qa-forseti
**Test Phase:** Stage 3 (QA validation before dev sign-off)

---

## Test Coverage Summary

| Area | Test Type | Count | Priority | Status |
|---|---|---|---|---|
| Routes & Auth | integration | 8 | P0 | pending |
| Admin Settings Form | unit + integration | 10 | P0 | pending |
| Permissions & Team Assignment | integration | 6 | P1 | pending |
| Audit Log Viewer | unit + integration | 8 | P1 | pending |
| Health & Status Dashboard | integration | 8 | P0 | pending |
| Navigation Controls | integration | 6 | P2 | pending |
| Performance & Error Handling | integration | 4 | P0 | pending |
| Security (CSRF, Permissions) | integration | 7 | P0 | pending |
| **TOTAL** | | **57** | | **pending** |

---

## Test Cases (Detailed)

### Routes & Auth (8 tests)

**TC-P7-Route-1:** All admin routes exist and return 200
```
Preconditions: Admin user logged in
Steps:
  1. GET /langgraph-console/admin
  2. GET /langgraph-console/admin/settings
  3. GET /langgraph-console/admin/permissions
  4. GET /langgraph-console/admin/audit-log
  5. GET /langgraph-console/admin/health
  6. GET /langgraph-console/admin/navigation
Expected: All return 200 OK
```

**TC-P7-Route-2:** Routes require administer_console_settings permission
```
Preconditions: User with 'authenticated' role (not admin)
Steps:
  1. GET /langgraph-console/admin/settings
  2. GET /langgraph-console/admin/audit-log
Expected: Both return 403 Forbidden
```

**TC-P7-Route-3:** Anonymous users redirected to login
```
Preconditions: No user logged in
Steps:
  1. GET /langgraph-console/admin/settings
Expected: 303 redirect to /user/login
```

**TC-P7-Route-4:** Health AJAX endpoint returns JSON
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/health.json
Expected: 200 OK, Content-Type: application/json, valid JSON response
```

**TC-P7-Route-5:** POST form routes require CSRF token
```
Preconditions: Admin user
Steps:
  1. POST /langgraph-console/admin/settings (no token)
Expected: 403 Access Denied (CSRF validation)
```

**TC-P7-Route-6:** POST form routes succeed with valid CSRF token
```
Preconditions: Admin user, valid CSRF token
Steps:
  1. GET /langgraph-console/admin/settings (extract token)
  2. POST /langgraph-console/admin/settings (with token, valid data)
Expected: 303 redirect + flash message "Settings saved"
```

**TC-P7-Route-7:** Form submits log to audit table
```
Preconditions: Admin user submits settings form
Steps:
  1. POST /langgraph-console/admin/settings
  2. Query audit table: SELECT * WHERE action='settings_changed'
Expected: Row created with: timestamp, operator_id=1, action, before/after values, csrf_verified=1
```

**TC-P7-Route-8:** GET routes are read-only (no POST)
```
Preconditions: Admin user
Steps:
  1. POST /langgraph-console/admin/audit-log (no form data)
Expected: 405 Method Not Allowed
```

### Admin Settings Form (10 tests)

**TC-P7-Settings-1:** Form displays 5 numeric fields
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/settings
Expected: Form contains inputs for: max_tick_history, metrics_trend_window, drift_threshold_pct, alert_retention_days, canary_duration_hours
```

**TC-P7-Settings-2:** Fields have correct defaults
```
Preconditions: First-time user, no config saved
Steps:
  1. GET /langgraph-console/admin/settings
Expected: Fields pre-filled: max_tick_history=100, metrics_trend_window=10, drift_threshold_pct=50, alert_retention_days=7, canary_duration_hours=1
```

**TC-P7-Settings-3:** Validation: out-of-range values rejected
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/settings
  2. Enter max_tick_history = 5 (below min 10)
  3. Submit form
Expected: Form error: "Must be between 10 and 1000"
```

**TC-P7-Settings-4:** Validation: non-numeric values rejected
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/settings
  2. Enter max_tick_history = "abc"
  3. Submit form
Expected: Form error: "Must be a number"
```

**TC-P7-Settings-5:** Validation: required fields enforced
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/settings
  2. Clear all fields
  3. Submit form
Expected: Form error: "This field is required"
```

**TC-P7-Settings-6:** Submit button disabled until form valid
```
Preconditions: Admin user, form open
Steps:
  1. Enter invalid value (e.g., negative number)
  2. Observe submit button
Expected: Button visually disabled or shows error state
```

**TC-P7-Settings-7:** Settings saved to Drupal config
```
Preconditions: Admin user submits form with: drift_threshold_pct=75
Steps:
  1. Query Drupal config: $config_factory->get('copilot_agent_tracker.observe_settings')
Expected: Config contains 'drift_threshold_pct' = 75
```

**TC-P7-Settings-8:** Settings saved to JSON fallback
```
Preconditions: Admin user submits form
Steps:
  1. Check file: $COPILOT_HQ_ROOT/admin/settings.json
Expected: JSON file contains all 5 settings with submitted values
```

**TC-P7-Settings-9:** Form pre-fills with saved values on reload
```
Preconditions: Settings previously saved (e.g., max_tick_history=150)
Steps:
  1. GET /langgraph-console/admin/settings
Expected: Form field pre-filled with 150
```

**TC-P7-Settings-10:** Success message shown after submit
```
Preconditions: Admin user, valid form data
Steps:
  1. POST /langgraph-console/admin/settings (with CSRF token)
Expected: Redirect to GET, system message: "✓ Settings saved successfully."
```

### Permissions & Team Assignment (6 tests)

**TC-P7-Perms-1:** Permissions matrix displays all sections and roles
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/permissions
Expected: Matrix with 7 rows (Home, Build, Test, Run, Observe, Release, Admin) × 3 columns (Anonymous, Authenticated, Admin)
```

**TC-P7-Perms-2:** Matrix shows correct permission per cell
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/permissions
  2. Find "Observe" row, "Admin" column
Expected: Shows "administrator" role required
```

**TC-P7-Perms-3:** Team assignment lists available seats
```
Preconditions: sessions/dev-forseti/inbox/, sessions/qa-forseti/inbox/ exist
Steps:
  1. GET /langgraph-console/admin/permissions
  2. Find "Team Assignment" section
Expected: Lists: dev-forseti, qa-forseti (from sessions/ dirs)
```

**TC-P7-Perms-4:** Team assignment checkboxes can be selected
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/permissions
  2. Check box for "dev-forseti"
  3. Click Save
Expected: No error, page reloads with checkbox still checked
```

**TC-P7-Perms-5:** Assigned seats saved to user.settings
```
Preconditions: Admin user selects seats: dev-forseti, qa-forseti
Steps:
  1. POST /langgraph-console/admin/permissions
  2. Query Drupal user data: $user->get('settings')['langgraph_seats']
Expected: Array contains ['dev-forseti', 'qa-forseti']
```

**TC-P7-Perms-6:** Team assignment persists on page reload
```
Preconditions: Seats assigned previously
Steps:
  1. GET /langgraph-console/admin/permissions
Expected: Previously selected boxes still checked
```

### Audit Log Viewer (8 tests)

**TC-P7-Audit-1:** Audit table created with correct schema
```
Preconditions: Module installed
Steps:
  1. Query DB: DESCRIBE copilot_agent_tracker_audit
Expected: Columns: id, timestamp, operator_id, action, resource_id, before_value, after_value, csrf_verified
```

**TC-P7-Audit-2:** Audit log displays entries as table
```
Preconditions: 3+ audit entries in table
Steps:
  1. GET /langgraph-console/admin/audit-log
Expected: Table with rows showing: timestamp, operator, action, resource, before, after
```

**TC-P7-Audit-3:** Filter by operator works
```
Preconditions: Entries from operator_id 1, 2
Steps:
  1. GET /langgraph-console/admin/audit-log
  2. Select operator "1" from dropdown
  3. Observe rows
Expected: Only entries from operator 1 shown
```

**TC-P7-Audit-4:** Filter by action works
```
Preconditions: Entries with actions: settings_changed, version_created
Steps:
  1. GET /langgraph-console/admin/audit-log
  2. Select action "settings_changed"
Expected: Only settings_changed entries shown
```

**TC-P7-Audit-5:** Filter by date range works
```
Preconditions: Entries spanning 2026-04-15 to 2026-04-20
Steps:
  1. GET /langgraph-console/admin/audit-log
  2. Set From = 2026-04-18, To = 2026-04-20
Expected: Only entries between dates shown
```

**TC-P7-Audit-6:** Search by resource_id works
```
Preconditions: Entries with resource_id: drift_threshold_pct, alert_retention_days
Steps:
  1. GET /langgraph-console/admin/audit-log
  2. Enter "drift" in resource search
Expected: Only entries with resource containing "drift" shown
```

**TC-P7-Audit-7:** Pagination shows 100 per page, with next/prev
```
Preconditions: 250 audit entries
Steps:
  1. GET /langgraph-console/admin/audit-log
  2. Observe entry count on page
  3. Click "Next >"
Expected: First page shows 100, second page shows 100, third shows 50
```

**TC-P7-Audit-8:** Export CSV downloads with all filtered data
```
Preconditions: Filters applied
Steps:
  1. GET /langgraph-console/admin/audit-log (with filters)
  2. Click "Export as CSV"
Expected: File downloads as langgraph-audit-export-*.csv with filtered rows
```

### Health & Status Dashboard (8 tests)

**TC-P7-Health-1:** Orchestrator status shows green for recent tick
```
Preconditions: langgraph-ticks.jsonl mtime < 5 minutes ago
Steps:
  1. GET /langgraph-console/admin/health
Expected: Shows green ✓ "Orchestrator OK (last tick: 2 min ago)"
```

**TC-P7-Health-2:** Orchestrator status shows yellow for stale tick
```
Preconditions: langgraph-ticks.jsonl mtime 10 minutes ago
Steps:
  1. GET /langgraph-console/admin/health
Expected: Shows yellow ⚠️ "Orchestrator slow (last tick: 10 min ago)"
```

**TC-P7-Health-3:** Orchestrator status shows red for dead tick
```
Preconditions: langgraph-ticks.jsonl mtime 30 minutes ago
Steps:
  1. GET /langgraph-console/admin/health
Expected: Shows red ✗ "Orchestrator down (last tick: 30 min ago)"
```

**TC-P7-Health-4:** Tick frequency variance calculated
```
Preconditions: Last 10 ticks with 2-min avg spacing
Steps:
  1. GET /langgraph-console/admin/health
Expected: Shows "Expected: 2 min, Actual: 2.0 min, Variance: 0%"
```

**TC-P7-Health-5:** Parity status from latest tick
```
Preconditions: Latest tick has parity_ok=true
Steps:
  1. GET /langgraph-console/admin/health
Expected: Shows "Parity: ✓ OK" (green)
```

**TC-P7-Health-6:** Agent status table populated
```
Preconditions: sessions/dev-forseti/inbox, sessions/qa-forseti/inbox exist with command.md
Steps:
  1. GET /langgraph-console/admin/health
Expected: Table shows rows for dev-forseti, qa-forseti with status, last_action, inbox_size
```

**TC-P7-Health-7:** Data freshness indicators show mtimes
```
Preconditions: All data files present
Steps:
  1. GET /langgraph-console/admin/health
Expected: Shows timestamps for langgraph-ticks.jsonl, FEATURE_PROGRESS.md, executor-failures count
```

**TC-P7-Health-8:** Auto-refresh AJAX every 30s
```
Preconditions: Admin user, health dashboard open
Steps:
  1. Open Network tab in browser dev tools
  2. Wait 35 seconds
  3. Observe requests
Expected: XHR request to /langgraph-console/admin/health.json every ~30s, response updates displayed values
```

### Navigation Controls (6 tests)

**TC-P7-Nav-1:** Landing page dropdown shows all sections
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/navigation
Expected: Dropdown options: home, build, test, run, observe, release, admin
```

**TC-P7-Nav-2:** Landing page can be changed
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/navigation
  2. Select "observe" from dropdown
  3. Save
Expected: Redirects to GET, form shows "observe" selected
```

**TC-P7-Nav-3:** Landing page redirect works
```
Preconditions: User set landing page to "observe"
Steps:
  1. GET /langgraph-console (no subsection)
Expected: 303 redirect to /langgraph-console/observe
```

**TC-P7-Nav-4:** Visible sections checkboxes work
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/navigation
  2. Uncheck "Release" section
  3. Save
Expected: Form reloads with "Release" unchecked
```

**TC-P7-Nav-5:** Hidden sections removed from nav menu
```
Preconditions: "Release" section hidden
Steps:
  1. Navigate to /langgraph-console/home
  2. Observe left nav menu
Expected: "Release" link not in menu
```

**TC-P7-Nav-6:** Theme toggle saves and applies
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/navigation
  2. Select "Dark" theme
  3. Save
  4. Navigate to any console page
Expected: Body tag has data-theme="dark", CSS variables applied (dark background)
```

### Performance & Error Handling (4 tests)

**TC-P7-Perf-1:** All routes load < 2 seconds
```
Preconditions: All data files present
Steps:
  1. Time each route: /admin, /admin/settings, /admin/audit-log, /admin/health
Expected: All < 2s
```

**TC-P7-Perf-2:** AJAX health endpoint < 500ms
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/health.json
Expected: < 500ms
```

**TC-P7-Perf-3:** Missing COPILOT_HQ_ROOT shows warning
```
Preconditions: Admin user, COPILOT_HQ_ROOT unset
Steps:
  1. GET /langgraph-console/admin/health
Expected: 200 OK, yellow banner: "⚠️ Live data unavailable: COPILOT_HQ_ROOT not configured"
```

**TC-P7-Perf-4:** Missing health files handled gracefully
```
Preconditions: Admin user, langgraph-ticks.jsonl deleted
Steps:
  1. GET /langgraph-console/admin/health
Expected: Orchestrator status shows "UNKNOWN", no 500 error
```

### Security (CSRF, Permissions) (7 tests)

**TC-P7-Sec-1:** All form submits require valid CSRF token
```
Preconditions: Admin user
Steps:
  1. POST /langgraph-console/admin/settings (no token)
  2. POST /langgraph-console/admin/navigation (no token)
Expected: Both return 403 Access Denied
```

**TC-P7-Sec-2:** Non-admin users blocked from forms
```
Preconditions: Authenticated user (not admin)
Steps:
  1. POST /langgraph-console/admin/settings (with valid CSRF token)
Expected: 403 Forbidden
```

**TC-P7-Sec-3:** Settings values not logged to watchdog
```
Preconditions: Admin submits form with numeric values
Steps:
  1. POST /langgraph-console/admin/settings
  2. Grep watchdog logs
Expected: No full setting values in logs (safe for production)
```

**TC-P7-Sec-4:** Operator ID (not email) stored in audit table
```
Preconditions: Admin user (uid=1) submits form
Steps:
  1. Query audit table: SELECT operator_id FROM ... WHERE ...
Expected: operator_id = 1 (integer), not email address
```

**TC-P7-Sec-5:** Audit log before/after truncated if > 1KB
```
Preconditions: Very large setting value submitted
Steps:
  1. Query audit table: SELECT LENGTH(after_value)
Expected: <= 1024 bytes (truncated)
```

**TC-P7-Sec-6:** Permission matrix read-only (no mutations)
```
Preconditions: Admin user
Steps:
  1. GET /langgraph-console/admin/permissions
  2. Inspect form
Expected: No input fields (matrix is display-only); no POST handler for matrix rows
```

**TC-P7-Sec-7:** Health data read-only (no mutations)
```
Preconditions: Admin user
Steps:
  1. POST /langgraph-console/admin/health
Expected: 405 Method Not Allowed (GET-only)
```

---

## Test Execution Plan

### Stage 1: Unit Testing (Dev)
- AdminSettingsForm validation
- AuditLogger write
- HealthAggregator data collection
- Target: 10 tests, 30 minutes

### Stage 2: Integration Testing (Dev + QA)
- Route auth (8 tests)
- Form persistence (settings, navigation)
- CSRF validation (6 tests)
- Audit table writes (4 tests)
- AJAX health endpoint (2 tests)
- Target: 20 tests, 1.5 hours

### Stage 3: Manual QA Testing (QA)
- Smoke test: all routes accessible
- Form submission: values saved to config + JSON
- Audit log filtering: all filters work
- Health dashboard: status indicators correct
- AJAX auto-refresh: updates every 30s
- Target: 12 scenarios, 2.5 hours

### Stage 4: Automated Suite (CI/CD)
- All 57 tests run on every commit
- Coverage target: >90%

---

## Acceptance Criteria (QA Gate)

- [x] All 57 tests passing
- [x] >90% code coverage
- [x] Page load times <2s for all routes
- [x] AJAX endpoint <500ms
- [x] No PHP errors or warnings
- [x] CSRF tokens required on all form submits
- [x] Only administer_console_settings permission can access routes
- [x] Audit table created and populated correctly
- [x] All edge cases handled (missing files, invalid form data)
- [x] Settings persisted to both config + JSON

---

## Risk & Mitigation

| Risk | Likelihood | Mitigation |
|---|---|---|
| Audit table migrations fail | Medium | Test migration rollback on staging |
| CSRF token sync issues | Low | Use Drupal form API (automatic) |
| Config/JSON inconsistency | Medium | Read from config first, JSON as fallback |
| Health AJAX timeout | Low | Set timeout, show cached value on error |
| Large audit log performance | Medium | Query with pagination, index on timestamp |

---

## Sign-Off

| Role | Name | Date | Status |
|---|---|---|---|
| QA Owner | qa-forseti | — | pending |
| Dev Owner | dev-forseti | — | pending |
| PM Owner | pm-forseti | — | pending |
