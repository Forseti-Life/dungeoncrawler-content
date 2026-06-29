# Suite Activation: forseti-langgraph-console-admin

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-20T16:41:24+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-langgraph-console-admin"`**  
   This links the test to the living requirements doc at `features/forseti-langgraph-console-admin/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-langgraph-console-admin-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-langgraph-console-admin",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-langgraph-console-admin"`**  
   Example:
   ```json
   {
     "id": "forseti-langgraph-console-admin-<route-slug>",
     "feature_id": "forseti-langgraph-console-admin",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-langgraph-console-admin",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria — Phase 7: Admin & Configuration

## Route & Auth

### AC-Route-1: All admin routes exist and return 200 for admin
```
GET /langgraph-console/admin → LangGraphConsoleAdminController::index() → 200 OK
GET /langgraph-console/admin/settings → LangGraphConsoleAdminController::settings() → 200 OK
POST /langgraph-console/admin/settings → AdminSettingsForm → 303 (redirect to GET)
GET /langgraph-console/admin/permissions → LangGraphConsoleAdminController::permissions() → 200 OK
GET /langgraph-console/admin/audit-log → LangGraphConsoleAdminController::auditLog() → 200 OK
GET /langgraph-console/admin/health → LangGraphConsoleAdminController::health() → 200 OK
GET /langgraph-console/admin/health.json → REST endpoint → 200 OK (JSON)
GET /langgraph-console/admin/navigation → LangGraphConsoleAdminController::navigation() → 200 OK
POST /langgraph-console/admin/navigation → save navigation prefs → 303
```

### AC-Route-2: Routes require administer console settings permission
```
GET /langgraph-console/admin/settings (no auth) → 303 redirect to /user/login
GET /langgraph-console/admin/settings (authenticated, non-admin) → 403 Forbidden
GET /langgraph-console/admin/settings (authenticated, admin with perm) → 200 OK
```

### AC-Route-3: Form submits require CSRF token
```
POST /langgraph-console/admin/settings (no token) → 403 Access Denied (CSRF validation)
POST /langgraph-console/admin/settings (valid token) → 303 Redirect + flash message "Settings saved"
```

## Admin Settings Form

### AC-Settings-1: Settings form displays all configurable fields
```
Form Fields:
  - Max tick history (numeric, 10–1000, default: 100)
  - Metrics trend window (numeric, 5–50, default: 10)
  - Drift threshold % (numeric, 1–100, default: 50)
  - Alert retention days (numeric, 1–30, default: 7)
  - Canary default duration hours (numeric, 0.5–24, default: 1)
```

### AC-Settings-2: Form validation
- For each numeric field:
  - If value < min or > max: show form error "Must be between X and Y"
  - If not numeric: show error "Must be a number"
  - If blank: show error "This field is required"
- Submit button: disabled until all fields valid (client-side + server-side validation)

### AC-Settings-3: Settings saved to Drupal Config
- On form submit: save to `config_factory` at key `copilot_agent_tracker.observe_settings`
- Stored as: `{"max_tick_history": 100, "metrics_trend_window": 10, "drift_threshold_pct": 50, ...}`

### AC-Settings-4: Settings saved to filesystem JSON (fallback)
- Also save to: `$COPILOT_HQ_ROOT/admin/settings.json`
- Format: JSON with same structure as Drupal config
- If Drupal config unavailable, read from this file

### AC-Settings-5: Form pre-fill from config
- On page load: read current config values
- Pre-fill form fields with saved values (or defaults if first time)

### AC-Settings-6: Form submit success feedback
- After POST submit: redirect to GET /langgraph-console/admin/settings
- Display Drupal system message: "✓ Settings saved successfully."
- Form fields pre-filled with just-saved values

### AC-Settings-7: Audit log entry for settings changes
- On each form submit (success or validation failure): create audit table entry
  - `action`: "settings_changed"
  - `resource_id`: field name (e.g., "drift_threshold_pct")
  - `before_value`: old value (JSON)
  - `after_value`: new value (JSON)
  - `operator_id`: current user ID
  - `timestamp`: now
  - `csrf_verified`: 1 if token was valid

## Permissions & Team Assignment

### AC-Perms-1: Permissions matrix displayed
```
| Section | Anonymous | Authenticated | Admin |
|---|---|---|---|
| Home | public | view_langgraph_console | administrator |
| Build | — | administrator | administrator |
| Test | — | administrator | administrator |
| Run | — | administrator | administrator |
| Observe | — | administrator | administrator |
| Release | — | administer_release_cycle | administer_release_cycle |
| Admin | — | — | administer_console_settings |
```

### AC-Perms-2: Matrix is read-only
- No form inputs on this page
- Display only (reference information for operators)
- If future RBAC added: convert to form with checkboxes

### AC-Perms-3: Team Assignment list
- Display: list of available seat IDs (from `sessions/*/inbox/` directories)
- For each seat: checkbox "Assign to my view"
- Save button: POST to admin/permissions/team-assign
- On save: store checked seat IDs in Drupal user data field (`user.settings['langgraph_seats']`)

### AC-Perms-4: Team assignment persistence
- On next login: pre-check boxes for seats saved in user.settings
- If no seats assigned: show message "No seats assigned. You will see all agents by default."

## Audit Log Viewer

### AC-Audit-1: Audit table schema created
```sql
CREATE TABLE copilot_agent_tracker_audit (
  id INT PRIMARY KEY AUTO_INCREMENT,
  timestamp DATETIME NOT NULL,
  operator_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,  -- 'version_created', 'promote_staging', 'settings_changed', etc.
  resource_id VARCHAR(255),
  before_value LONGTEXT,
  after_value LONGTEXT,
  csrf_verified BOOLEAN DEFAULT 1,
  INDEX idx_timestamp (timestamp),
  INDEX idx_operator (operator_id),
  FOREIGN KEY (operator_id) REFERENCES users(uid)
);
```

### AC-Audit-2: Audit log displayed as table
```
| Timestamp | Operator | Action | Resource | Before | After | CSRF |
|---|---|---|---|---|---|---|
| 2026-04-20 14:30:15 | admin (uid: 1) | settings_changed | drift_threshold_pct | "50" | "75" | ✓ |
| 2026-04-20 14:15:00 | architect (uid: 2) | version_created | engine.py | — | "20260420-fd79af60-v1" | ✓ |
```

### AC-Audit-3: Audit log filters
- Dropdown: "Operator" (list of users who have made changes)
- Dropdown: "Action" (settings_changed, version_created, promote_staging, etc.)
- Date range: "From" and "To" (date-time inputs)
- Text search: "Resource ID" (partial match)
- Clear button resets all

### AC-Audit-4: Applied filters
- Filters applied on form change (or when user clicks "Filter" button)
- URL updated with query params: `?operator=1&action=settings_changed&from=2026-04-15&to=2026-04-20&resource=drift`
- Page load with params pre-fills filters + displays filtered results

### AC-Audit-5: Pagination
- Display: last 100 entries (configurable via settings form in Phase 7)
- If > 100: pagination controls (← Previous, Next →)
- Default sort: timestamp DESC (most recent first)

### AC-Audit-6: Audit log export
- Button: "Export as CSV"
- Download: `langgraph-audit-export-{timestamp}.csv`
- Columns: timestamp, operator_id, operator_name, action, resource_id, before_value, after_value, csrf_verified

### AC-Audit-7: Audit log retention
- Cron job (or orchestrator phase) purges entries older than 30 days daily
- If purge occurs: show info message on audit-log page "Note: audit entries older than 30 days are automatically archived."

### AC-Audit-8: Empty audit log
- If no entries: display "No audit log entries yet."

## Health & Status Dashboard

### AC-Health-1: Orchestrator status indicator
- Load latest tick timestamp from `langgraph-ticks.jsonl`
- Compare to now():
  - If < 5 min ago: green ✓ "Orchestrator OK (last tick: 2 min ago)"
  - If 5–15 min ago: yellow ⚠️ "Orchestrator slow (last tick: 12 min ago)"
  - If > 15 min ago: red ✗ "Orchestrator down (last tick: 45 min ago)"

### AC-Health-2: Tick frequency check
- Read last 10 ticks from `langgraph-ticks.jsonl`
- Calculate: avg time delta between ticks
- Display: "Expected: 2 min, Actual: 2.1 min, Variance: +5%"
- If > 5% variance: yellow warning

### AC-Health-3: Parity status
- Read `parity_ok` from latest tick
- Display: "Parity: ✓ OK" (green) or "Parity: ✗ MISMATCH" (red)
- If missing: "Parity: ? UNKNOWN"

### AC-Health-4: LangGraph provider
- Read `provider` from latest tick (e.g., "ShellProvider")
- Display: "Provider: ShellProvider"

### AC-Health-5: Per-agent status table
```
| Seat ID | Module | Status | Last Action | Inbox Size | Last Modified |
|---|---|---|---|---|---|
| dev-forseti | forseti | working | impl-feature-X | 3 items | 2 min ago |
| qa-forseti | forseti | idle | — | 0 items | 5 min ago |
| pm-forseti | forseti | error | — | 1 item | 30 min ago |
```

### AC-Health-6: Per-agent status derivation
- Read `sessions/*/inbox/*/command.md` for current status and last-modified time
- Read `sessions/*/outbox/` for last completed action
- Calculate: inbox_size = count files in inbox
- Status color: green (idle), blue (working), yellow (error), grey (unknown)

### AC-Health-7: Data freshness section
```
Data Freshness:
  ✓ langgraph-ticks.jsonl (2 min ago)
  ✓ FEATURE_PROGRESS.md (32 min ago)
  ✓ executor-failures/ (0 items)
```

### AC-Health-8: Stale data warning
- If `langgraph-ticks.jsonl` mtime > 5 min: yellow ⚠️
- If `FEATURE_PROGRESS.md` mtime > 60 min: yellow ⚠️
- If executor-failures/ has items: orange warning "Executor errors detected"

### AC-Health-9: Auto-refresh via AJAX
- Every 30 seconds: fetch `/langgraph-console/admin/health.json`
- Update displayed values silently (no spinner, no page reload)
- Show: "Last refreshed: 14:32:45" with manual "Refresh now" button

### AC-Health-10: AJAX endpoint schema
```json
GET /langgraph-console/admin/health.json
Response:
{
  "orchestrator_status": "ok",  // ok, slow, down
  "last_tick_timestamp": "2026-04-20T14:30:15Z",
  "tick_frequency_variance": 5,  // %
  "parity_ok": true,
  "provider": "ShellProvider",
  "agents": [
    {"seat_id": "dev-forseti", "status": "working", "inbox_size": 3, "last_modified": "2026-04-20T14:30:00Z"},
    ...
  ],
  "data_freshness": {
    "ticks_mtime": "2026-04-20T14:30:15Z",
    "feature_progress_mtime": "2026-04-20T13:30:00Z",
    "executor_failures_count": 0
  }
}
```

## Console Navigation Controls

### AC-Nav-1: Navigation form
- Dropdown: "Landing page" (options: home, build, test, run, observe, release, admin)
- Default: "home"
- Checkboxes: "Visible sections" (check to show, uncheck to hide)
- Radio buttons: "Theme" (light / dark)
- Save button

### AC-Nav-2: Settings persist per user
- On save: POST to `/langgraph-console/admin/navigation`
- Store in Drupal user data: `user.settings['langgraph_nav']` = JSON
- On next load: apply stored preferences

### AC-Nav-3: Theme applied to body
- If dark mode selected: add `data-theme="dark"` to `<body>` tag
- Provide CSS variable: `--theme-bg: #1a1a1a` (dark) / `#ffffff` (light)

### AC-Nav-4: Landing page redirect
- When user navigates to `/langgraph-console` (no subsection):
- If landing_page set to "observe": redirect to `/langgraph-console/observe`
- Otherwise: show "home" section

### AC-Nav-5: Visible sections applied to nav menu
- Build navigation menu based on selected visible sections
- Hide nav items for unchecked sections
- If all hidden: show message "No sections visible. Edit navigation settings to show sections."

## Error Handling

### AC-Error-1: Missing COPILOT_HQ_ROOT
- Display yellow banner: "⚠️ Live data unavailable: COPILOT_HQ_ROOT not configured."
- Do not crash; show static admin forms only

### AC-Error-2: Missing tick data
- If `langgraph-ticks.jsonl` missing: health dashboard shows "Orchestrator status: UNKNOWN"
- Do not show red error, show yellow warning

### AC-Error-3: Invalid JSON in tick files
- If parsing fails: log to watchdog, display safe fallback
- Show: "Could not parse tick data. Last known good data: {timestamp}"

### AC-Error-4: DB errors
- If audit table missing: create it (via hook_schema)
- If audit write fails: log to watchdog, display message "Failed to log this action. Contact admin."

## Performance

### AC-Perf-1: Settings form load < 1s
- Simple form, minimal data loaded

### AC-Perf-2: Audit log load < 2s
- Query with index on timestamp + operator_id
- Limit to last 100 entries (configurable)

### AC-Perf-3: Health dashboard load < 2s
- Tick file read + agent status glob should be fast
- Cache per-request if needed

### AC-Perf-4: Health AJAX endpoint < 500ms
- Lightweight JSON response
- No heavy computation

## Security

### AC-Sec-1: No hardcoded paths
- All paths via `DashboardController::langgraphPath()`

### AC-Sec-2: Settings values sanitized
- No shell metacharacters in numeric fields (int validation)
- Before/after values in audit log: truncate if > 1KB

### AC-Sec-3: Operator ID in audit log
- Store uid only (no PII); name resolved from users table at display time
- Do not log actual user email in audit table

### AC-Sec-4: CSRF validation on all forms
- POST to settings, permissions, navigation: require valid CSRF token
- Use Drupal form API (automatic)

### AC-Sec-5: Permission checks
- All routes: `_permission: 'administer console settings'` in routing.yml
- Form submits: additional check via form submit handler

## Testing Checklist

- [ ] All 8 routes return 200 for admin user
- [ ] Non-admin users get 403 on all routes
- [ ] Settings form displays 5 fields
- [ ] Settings validation: out-of-range values rejected
- [ ] Settings save persists to Drupal config + JSON file
- [ ] Settings form pre-fill works on page reload
- [ ] Audit log entry created on form submit
- [ ] Permissions matrix displays correctly
- [ ] Team assignment checkboxes work
- [ ] Team assignment persists to user.settings
- [ ] Audit log table populated with ≥ 2 sample entries
- [ ] Audit log filters work (operator, action, date, resource)
- [ ] Audit log export CSV downloads
- [ ] Audit log pagination works (if > 100 entries)
- [ ] Health dashboard shows orchestrator status
- [ ] Health dashboard shows agent table with ≥ 2 agents
- [ ] Health dashboard shows data freshness
- [ ] Health AJAX endpoint returns valid JSON
- [ ] Health auto-refresh every 30s works
- [ ] Navigation landing page dropdown works
- [ ] Navigation visible sections checkboxes work
- [ ] Navigation theme radio buttons work
- [ ] Navigation settings persist per user
- [ ] Theme applied to body (dark mode CSS)
- [ ] Landing page redirect works
- [ ] Nav menu respects hidden sections
- [ ] Missing COPILOT_HQ_ROOT shows warning
- [ ] Invalid JSON shows error, no crash
- [ ] All form submits have CSRF tokens
- [ ] No hardcoded paths found in grep
- [ ] Performance: all pages load < 2s
- [ ] Performance: AJAX endpoint < 500ms

## Audit Log Sample Entries (for manual testing)

```sql
INSERT INTO copilot_agent_tracker_audit (timestamp, operator_id, action, resource_id, before_value, after_value, csrf_verified)
VALUES
  (NOW(), 1, 'settings_changed', 'drift_threshold_pct', '50', '75', 1),
  (NOW() - INTERVAL 10 MINUTE, 1, 'settings_changed', 'alert_retention_days', '7', '14', 1),
  (NOW() - INTERVAL 30 MINUTE, 2, 'version_created', 'engine.py', NULL, '20260420-fd79af60-v1', 1);
```
- Agent: qa-forseti
- Status: pending
