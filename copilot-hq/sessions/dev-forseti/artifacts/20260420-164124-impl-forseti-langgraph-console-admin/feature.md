# Feature Brief

- Work item id: forseti-langgraph-console-admin
- Website: forseti.life
- Module: copilot_agent_tracker
- Project: PROJ-001
- Group Order: 5
- Group: console-ui
- Group Title: Console Routes & UI
- Group Sort: 5
- Status: ready
- Release: forseti-release-t (planned)
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: P2
- Source: LangGraph UI roadmap (PROJ-001, Phase 7: Admin & Configuration)

## Summary

The LangGraph Console Admin section (`/langgraph-console/admin`) provides operators with configuration, permission, and audit controls for the console and orchestration system. This feature adds: Admin Settings form (configurable thresholds, retention policies); Permissions & Team Assignment UI (role matrix, seat scoping); Audit Log Viewer (mutation history with filtering); Health & Status Dashboard (orchestrator status, agent pool health, data freshness); Console Navigation controls (show/hide sections, set landing page). All configuration changes are logged and require `administer console settings` permission. Access is admin-only.

## Goal

- Operators can tune console behavior (thresholds, retention, display options) without code changes
- Full audit trail of all console mutations for compliance and troubleshooting
- Real-time visibility into system health and agent execution state
- Permission matrix for controlling which roles can access which console sections

## Acceptance criteria

### AC-1: Admin Settings Form
- `/langgraph-console/admin/settings` displays form for tunable parameters:
  - **Max tick history:** how many ticks to retain in display (range: 10–1000, default: 100)
  - **Metrics trend window:** ticks to include in trend calculation (range: 5–50, default: 10)
  - **Drift threshold %:** variance threshold to trigger alert (range: 1–100, default: 50)
  - **Alert retention days:** how long to keep incident records (range: 1–30, default: 7)
  - **Canary default duration (hours):** suggested canary duration for Phase 6 (range: 0.5–24, default: 1)

### AC-2: Admin Settings Persistence
- Settings saved to: `$COPILOT_HQ_ROOT/admin/settings.json` (JSON format) AND Drupal Config (via `config_factory`)
- On form submit: validate input ranges, save to both backends, log to audit table
- Settings loaded on page load from Drupal config (fallback to JSON if config not available)
- If both backends differ: config wins; re-sync JSON from config on next load

### AC-3: Admin Settings Validation
- All numeric fields: validate min/max ranges (return form error if out of range)
- Drift threshold: cannot be 0 or negative
- Retention: cannot be less than 1 day
- Submit button disabled until form is valid

### AC-4: Permissions Matrix
- `/langgraph-console/admin/permissions` displays read-only matrix:
  - Rows: console sections (Home, Build, Test, Run, Observe, Release, Admin)
  - Columns: Drupal roles (administrator, authenticated user, anonymous)
  - Cell value: permission required for that role to access that section
  - Example: "Run section requires: administrator" (grey box for non-admin roles)

### AC-5: Team Assignment
- Section: "Team Assignment" on permissions page
- Allow admin to scope agent tracking to specific seat IDs (future: multi-agent filtering)
- List of seat IDs with: seat name, module, current status (idle/working)
- Checkbox to "Assign this seat to my team view" (multi-select)
- Selected seats are stored in Drupal user preferences (`user.settings` field)

### AC-6: Audit Log Viewer
- `/langgraph-console/admin/audit-log` displays all console mutations:
  - Columns: timestamp (ISO 8601), operator (Drupal user ID + name), action (version_created / promote_staging / promote_prod / settings_changed / permission_updated), resource ID, before value, after value, CSRF verified
  - Rows: last 100 audit entries (configurable via settings)

### AC-7: Audit Log Filtering
- Filters: operator (dropdown), action (dropdown), date range (start/end date inputs), resource ID (text search)
- Apply filters client-side (if <1000 entries) or server-side query (if DB-backed)
- Clear button resets all filters
- Export button: download filtered results as CSV (timestamp, operator, action, resource, before, after)

### AC-8: Audit Log Retention
- Audit table (`copilot_agent_tracker_audit`) keeps entries for last 30 days
- Cron job (or orchestrator phase): purge entries older than 30 days daily
- If purged entries exist in UI: show "Note: entries older than 30 days are archived" hint

### AC-9: Health & Status Dashboard
- `/langgraph-console/admin/health` displays system health:
  - **Orchestrator status:** green ✓ (last tick < 5 min ago) / yellow ⚠️ (5–15 min ago) / red ✗ (> 15 min ago or unknown)
  - **Last tick:** timestamp (ISO 8601, human-readable local time), tick sequence number
  - **Tick frequency:** 2 minutes (expected), measured from last 10 ticks' time deltas
  - **Parity status:** `parity_ok` from latest tick (green if true, red if false)
  - **LangGraph provider:** provider name (e.g., `ShellProvider`)

### AC-10: Per-Agent Status
- Subsection: "Agent Pool Status"
- Table with columns: seat ID, module, current status (idle / working / error), last action, inbox size, last modified time
- Data source: `sessions/*/inbox/*/command.md` (parse `Status:` line), `sessions/*/outbox/` (last file mtime)
- Click agent row → drill-down to recent inbox/outbox items

### AC-11: Data Freshness Indicators
- Subsection: "Data Freshness"
- Show: `langgraph-ticks.jsonl` mtime (green if < 5 min ago), `FEATURE_PROGRESS.md` mtime (green if < 1 hour ago), `executor-failures/` count (green if 0)
- If any stale: display yellow warning "Data may be out of sync. Check orchestrator status."

### AC-12: Health Auto-Refresh
- Health dashboard auto-refreshes every 30 seconds (AJAX fetch to `/langgraph-console/admin/health.json`)
- Show "Last refreshed" timestamp with manual refresh button
- No spinner or loading state (silent update)

### AC-13: Console Navigation Controls
- `/langgraph-console/admin/navigation` allows customizing console UI:
  - **Landing page:** dropdown to set which section loads on `/langgraph-console` (default: home)
  - **Visible sections:** checkboxes for each section (Home, Build, Test, Run, Observe, Release, Admin) — uncheck to hide from nav
  - **Theme:** radio buttons for light / dark mode (applied via `data-theme` attribute on body)
  - Settings saved to Drupal Config per user

### AC-14: Auth & Permissions (All Admin Routes)
- All Admin routes (`/langgraph-console/admin*`) require `administer console settings` permission (new permission, added via hook_permission)
- Additionally, mutations (form submits) require valid CSRF token
- Authenticated non-admin → 403 Forbidden
- Anonymous → 303 redirect to login

### AC-15: COPILOT_HQ_ROOT Env Availability
- All Admin routes verify `COPILOT_HQ_ROOT` is set before rendering
- If unset: display yellow warning banner, do not crash
- Health dashboard: gracefully handle missing health files

### AC-16: Audit Logging of Admin Actions
- Every form submit on admin pages (settings, permissions, navigation) logged to audit table:
  - Timestamp, operator ID, action (e.g., `settings_changed`), resource ID (e.g., `drift_threshold`), before value, after value, CSRF verified flag
- Log even if validation fails (log the failed attempt)
- Do not log sensitive data (e.g., API keys, tokens)

## Out of scope
- Role-based access control (RBAC) beyond current admin/non-admin
- Multi-team or org-level permission scoping (future phase)
- Integration with external auth systems (Okta, etc.) — future
- Alert notification setup (Slack, email) — Phase 8+

## Technical notes

- **DB Schema:**
  ```sql
  CREATE TABLE copilot_agent_tracker_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    timestamp DATETIME NOT NULL,
    operator_id INT NOT NULL,  -- Drupal user ID
    action VARCHAR(255) NOT NULL,  -- 'version_created', 'promote_staging', 'settings_changed', etc.
    resource_id VARCHAR(255),
    before_value LONGTEXT,
    after_value LONGTEXT,
    csrf_verified BOOLEAN DEFAULT 1,
    INDEX (timestamp),
    INDEX (operator_id),
    FOREIGN KEY (operator_id) REFERENCES users(uid)
  );
  ```

- **New Permissions (in .module):**
  - `administer console settings`
  - `administer release cycle`

- **Controllers:**
  - New `LangGraphConsoleAdminController` with methods: `settings()`, `permissions()`, `auditLog()`, `health()`, `navigation()`
  - New form: `AdminSettingsForm` extending `ConfigFormBase`

- **Helper Services:**
  - New `AuditLogger` service: log mutations to audit table
  - New `HealthAggregator` service: collect orchestrator status, agent status, data freshness

- **Rendering:**
  - Twig templates in `templates/langgraph-console/admin/` (settings.html.twig, permissions.html.twig, audit-log.html.twig, health.html.twig, navigation.html.twig)

- **AJAX Endpoints:**
  - New JSON route: `/langgraph-console/admin/health.json` (returns `{status, timestamp, agents[], fresh}`)

## Verification

```bash
# Smoke test: load all Admin routes as admin
curl -s -b admin_cookies.txt https://forseti.life/langgraph-console/admin | grep -i "Settings\|Permissions"
curl -s -b admin_cookies.txt https://forseti.life/langgraph-console/admin/settings | grep -i "Drift threshold\|Retention"
curl -s -b admin_cookies.txt https://forseti.life/langgraph-console/admin/permissions | grep -i "administrator\|authenticated"
curl -s -b admin_cookies.txt https://forseti.life/langgraph-console/admin/audit-log | grep -i "Action\|Operator"
curl -s -b admin_cookies.txt https://forseti.life/langgraph-console/admin/health | grep -i "Orchestrator\|Status"

# Verify audit table created
mysql forseti -e "DESCRIBE copilot_agent_tracker_audit;"

# Verify settings persisted
curl -s -b admin_cookies.txt https://forseti.life/langgraph-console/admin/settings | grep -o "value=\"[0-9]*\"" | head -5

# Verify auth: non-admin should get 403
curl -s -b user_cookies.txt https://forseti.life/langgraph-console/admin/settings | grep -i "403\|forbidden"
```

## Security acceptance criteria

- **Authentication/permission surface:** All Admin routes require `administer console settings` permission (enforced via `_permission` in routing.yml). Form submits additionally require valid CSRF token. No unauthenticated access.
- **CSRF expectations:** All form-based routes (settings, permissions, navigation) require CSRF token validation on POST. Health dashboard (GET-only) does not require CSRF.
- **Input validation:** Settings form validates numeric ranges before save. Team assignment list validated against existing seat IDs. Audit log filters (e.g., user ID, date) validated before DB query (prevent SQL injection).
- **PII/logging constraints:** Audit log may contain config values (e.g., threshold settings) but NOT sensitive values (passwords, API keys). Do not log raw settings JSON if it contains secrets. Truncate operator ID to integer (no PII). Audit table retention: 30 days (compliant with typical audit policies).

## Dependencies

- `forseti-copilot-agent-tracker` — shipped ✓ (DB tables, telemetry API, DashboardController helpers)
- `LangGraphConsoleStubController` — shipped ✓ (route structure, 7 console sections)

## Related features

- Predecessor: `forseti-langgraph-console-observe` (Phase 5 — Observe section, uses settings from Admin)
- Infrastructure: `forseti-langgraph-ui` (shared roadmap)
- Future: Phase 6 `forseti-langgraph-console-release` (will add `administer_release_cycle` permission here)
