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
