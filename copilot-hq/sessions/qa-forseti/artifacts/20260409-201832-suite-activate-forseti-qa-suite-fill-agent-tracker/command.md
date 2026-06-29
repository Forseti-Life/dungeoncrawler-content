# Suite Activation: forseti-qa-suite-fill-agent-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T20:18:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-qa-suite-fill-agent-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-qa-suite-fill-agent-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-qa-suite-fill-agent-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-qa-suite-fill-agent-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-qa-suite-fill-agent-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-qa-suite-fill-agent-tracker-<route-slug>",
     "feature_id": "forseti-qa-suite-fill-agent-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-qa-suite-fill-agent-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-qa-suite-fill-agent-tracker

- Feature: forseti-qa-suite-fill-agent-tracker
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify that the 4 new suite entries exist in `qa-suites/products/forseti-agent-tracker/suite.json`, that `qa-suite-validate.py` passes, and that each suite command exits 0 when run against a live Drupal instance.

## Test cases

### TC-1: Manifest schema valid
- Steps: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0 with "OK: validated N suite manifest(s)"

### TC-2: All 4 suite IDs present in manifest
- Steps:
  ```
  python3 -c "
  import json
  d=json.load(open('qa-suites/products/forseti-agent-tracker/suite.json'))
  ids=[s['id'] for s in d['suites']]
  required=['forseti-copilot-agent-tracker-route-acl','forseti-copilot-agent-tracker-api','forseti-copilot-agent-tracker-happy-path','forseti-copilot-agent-tracker-security']
  missing=[x for x in required if x not in ids]
  assert not missing, f'Missing suites: {missing}'
  print('PASS')
  "
  ```
- Expected: prints "PASS"; exits 0

### TC-3: All 4 new suites have required_for_release: true
- Steps: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti-agent-tracker/suite.json')); ids=['forseti-copilot-agent-tracker-route-acl','forseti-copilot-agent-tracker-api','forseti-copilot-agent-tracker-happy-path','forseti-copilot-agent-tracker-security']; suites={s['id']:s for s in d['suites']}; assert all(suites[i]['required_for_release'] for i in ids), 'not required_for_release'; print('PASS')"`
- Expected: "PASS"; exits 0

### TC-4: route-acl suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL` set; Drupal running
- Steps: Run the command in the `forseti-copilot-agent-tracker-route-acl` suite entry
- Expected: exits 0

### TC-5: api suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL` and `TELEMETRY_TOKEN` set; Drupal running
- Steps: Run the command in the `forseti-copilot-agent-tracker-api` suite entry
- Expected: exits 0

### TC-6: happy-path suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL`, `TELEMETRY_TOKEN`, and `FORSETI_COOKIE_ADMIN` set
- Steps: Run the command in the `forseti-copilot-agent-tracker-happy-path` suite entry
- Expected: exits 0

### TC-7: security suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL`, `TELEMETRY_TOKEN`, `FORSETI_COOKIE_ADMIN`, and `FORSETI_COOKIE_AUTHENTICATED` set
- Steps: Run the command in the `forseti-copilot-agent-tracker-security` suite entry
- Expected: exits 0; no PII leak detected

### TC-8: Security AC traceability
- Steps: Confirm AC-SEC-01 through AC-SEC-04 in `01-acceptance-criteria.md` each cite one of the 4 required site.instructions.md subsections
- Expected: Authentication/permission surface → AC-SEC-01; CSRF expectations → AC-SEC-02; Input validation requirements → AC-SEC-03; PII/logging constraints → AC-SEC-04

## Regression notes
- `tracker-copilot-agent-tracker` (existing 24-test suite) must continue to pass
- `python3 scripts/qa-suite-validate.py` must still exit 0 after adding the 4 new entries

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-qa-suite-fill-agent-tracker

- Feature: forseti-qa-suite-fill-agent-tracker
- Module: qa_suites
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Populate 4 suites in `qa-suites/products/forseti-agent-tracker/suite.json` covering route ACL, API contract, happy-path, and security for the `copilot_agent_tracker` Drupal module. Each suite must be a `suite.json` entry with a deterministic `command` (exit 0 = PASS, exit 1+ = FAIL) and at least 2 distinct verification points. The security suite must trace directly to the security requirements in `org-chart/sites/forseti.life/site.instructions.md`.

## Context

The `tracker-copilot-agent-tracker` suite in the existing `suite.json` runs 24 test cases via `run-copilot-agent-tracker-tests.py`. The 4 new suites below are **focused sub-suites** that:
- Have `required_for_release: true`
- Can be run independently (no external state beyond a reachable Drupal instance and `FORSETI_BASE_URL` / `TELEMETRY_TOKEN` / `FORSETI_COOKIE_ADMIN` env vars)
- Are added as new entries in `qa-suites/products/forseti-agent-tracker/suite.json`

Tools: `python` (already listed in manifest).

---

## Suite 1: forseti-copilot-agent-tracker-route-acl

**Purpose:** Verify that all agent-tracker admin routes are inaccessible to anonymous and unpermissioned users, and accessible to admin users with `administer copilot agent tracker`.

### AC-ACL-01: Anonymous → 403 or 302 on dashboard route
- Suite: `forseti-copilot-agent-tracker-route-acl`
- Scenario: Unauthenticated request to dashboard
- Command must: GET `${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker` with no session cookie
- Expected: HTTP 403 or 302 (redirect to login); exit 0 = PASS; any other status = exit 1
- Verification: `curl -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker"` returns 403 or 302

### AC-ACL-02: Anonymous → 403 or 302 on agent detail route
- Suite: `forseti-copilot-agent-tracker-route-acl`
- Scenario: Unauthenticated request to agent detail page
- Command must: GET `${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker/agent/test-agent` with no session cookie
- Expected: HTTP 403 or 302; exit 0 = PASS
- Verification: `curl -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker/agent/test-agent"` returns 403 or 302

### AC-ACL-03: Authenticated user without permission → 403
- Suite: `forseti-copilot-agent-tracker-route-acl`
- Scenario: Logged-in user who does not hold `administer copilot agent tracker`
- Command must: GET dashboard with an authenticated (non-admin) session cookie
- Expected: HTTP 403; exit 0 = PASS; 200 = exit 1 (access escalation)
- Verification: Requires `FORSETI_COOKIE_AUTHENTICATED` env var; confirm 403 response

### AC-ACL-04: Admin → 200 on dashboard
- Suite: `forseti-copilot-agent-tracker-route-acl`
- Scenario: User with `administer copilot agent tracker` (admin)
- Command must: GET `${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker` with `FORSETI_COOKIE_ADMIN`
- Expected: HTTP 200; exit 0 = PASS
- Verification: Confirm 200; confirm response body contains "copilot" or "agent" (no blank page)

---

## Suite 2: forseti-copilot-agent-tracker-api

**Purpose:** Verify the telemetry POST API contract — required fields, authentication, error response format, and valid payload acceptance.

### AC-API-01: POST without token → 403
- Suite: `forseti-copilot-agent-tracker-api`
- Scenario: Token authentication required; missing header
- Command must: POST `${FORSETI_BASE_URL}/api/copilot-agent-tracker/event` with no `X-Copilot-Agent-Tracker-Token` header
- Expected: HTTP 403; exit 0 = PASS; any 2xx = exit 1 (auth bypass)
- Verification: `curl -s -o /dev/null -w "%{http_code}" -X POST ... returns 403`

### AC-API-02: POST with invalid token → 403
- Suite: `forseti-copilot-agent-tracker-api`
- Scenario: Wrong token value
- Command must: POST with `X-Copilot-Agent-Tracker-Token: invalid-token-value`
- Expected: HTTP 403; exit 0 = PASS
- Verification: Confirm 403; confirm body does not reveal the correct token value

### AC-API-03: POST valid token + valid payload → 200, JSON response with status field
- Suite: `forseti-copilot-agent-tracker-api`
- Scenario: Valid token, minimal valid payload (`agent_id`, `status`, `current_action`)
- Command must: POST valid payload; parse response JSON; confirm `status` field present
- Expected: HTTP 200; response body is valid JSON; contains `"status"` key; exit 0 = PASS
- Verification: `python3 -c "import json,sys; d=json.load(sys.stdin); assert 'status' in d"` on response body

### AC-API-04: POST valid token + missing required field → 400
- Suite: `forseti-copilot-agent-tracker-api`
- Scenario: Payload omits `agent_id`
- Command must: POST `{"status": "active", "current_action": "test"}` with valid token
- Expected: HTTP 400; response body contains human-readable error message; exit 0 = PASS
- Verification: Confirm 400; confirm body is non-empty and not a 500 stack trace

### AC-API-05: POST valid token + oversized payload → 400 or 413
- Suite: `forseti-copilot-agent-tracker-api`
- Scenario: Payload body > 64 KB
- Command must: POST with `TELEMETRY_TOKEN` and a body generated to exceed 64 KB
- Expected: HTTP 400 or 413; no 500; no DB write; exit 0 = PASS
- Verification: Confirm status in [400, 413]; confirm no 500

---

## Suite 3: forseti-copilot-agent-tracker-happy-path

**Purpose:** Verify the primary end-to-end read/write flow: telemetry POST creates/upserts an agent record, and the admin dashboard reflects it.

### AC-HP-01: POST event → record created in agents table
- Suite: `forseti-copilot-agent-tracker-happy-path`
- Scenario: New agent_id POSTed for first time
- Command must: POST valid payload with unique `agent_id`; then query DB via drush: `drush php:eval "echo \Drupal::database()->select('copilot_agent_tracker_agents','a')->condition('agent_id','<id>')->countQuery()->execute()->fetchField();"`
- Expected: DB row count = 1 after POST; exit 0 = PASS
- Verification: DB count == 1 after a successful 200 POST

### AC-HP-02: Repeated POST same agent_id → upsert (still 1 row, updated last_seen)
- Suite: `forseti-copilot-agent-tracker-happy-path`
- Scenario: Same agent_id POSTed twice
- Command must: POST twice with same `agent_id` but different `current_action`; confirm row count stays 1; confirm `last_action` updated
- Expected: Exactly 1 row in `copilot_agent_tracker_agents`; `last_action` reflects second POST value; exit 0 = PASS
- Verification: Count == 1; drush php:eval to inspect `last_action` value

### AC-HP-03: Dashboard reflects posted agent
- Suite: `forseti-copilot-agent-tracker-happy-path`
- Scenario: After a successful POST, admin GET dashboard contains the agent
- Command must: POST event; GET `${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker` with admin cookie; assert agent_id or last_action text appears in response body
- Expected: Response body contains the posted `agent_id`; HTTP 200; exit 0 = PASS
- Verification: `grep -q "<agent_id>" response_body` or equivalent Python assertion

### AC-HP-04: Event appended to events table
- Suite: `forseti-copilot-agent-tracker-happy-path`
- Scenario: POST event → events table receives new row
- Command must: Count rows in `copilot_agent_tracker_events` before and after POST; confirm +1
- Expected: Event count increases by exactly 1; exit 0 = PASS
- Verification: drush php:eval count before/after; assert delta == 1

---

## Suite 4: forseti-copilot-agent-tracker-security

**Purpose:** Verify the security requirements defined in `org-chart/sites/forseti.life/site.instructions.md` — Authentication/permission surface, CSRF expectations, Input validation requirements, PII/logging constraints.

**Traceability:** Each AC below maps to a required subsection from `site.instructions.md § Security requirements (forseti-copilot-agent-tracker)`.

### AC-SEC-01: Authentication/permission surface — admin routes require permission (not just login)
- Traces to: site.instructions.md → Authentication/permission surface
- Suite: `forseti-copilot-agent-tracker-security`
- Scenario: Authenticated user without `administer copilot agent tracker` gets 403
- Command must: GET dashboard with a session cookie for a non-admin authenticated user
- Expected: HTTP 403; exit 0 = PASS; HTTP 200 = exit 1 (unauthorized access)
- Verification: Requires `FORSETI_COOKIE_AUTHENTICATED` env var; confirm 403

### AC-SEC-02: CSRF expectations — state-changing POST rejected without CSRF token
- Traces to: site.instructions.md → CSRF expectations
- Suite: `forseti-copilot-agent-tracker-security`
- Scenario: Forged POST to a CSRF-protected state-changing endpoint (e.g., any approve/update action if present) with valid admin cookie but no CSRF token
- Command must: POST to `${FORSETI_BASE_URL}/admin/reports/copilot-agent-tracker` (or any non-telemetry admin POST endpoint) with admin cookie but missing/wrong CSRF token
- Expected: HTTP 403; not 200; exit 0 = PASS
- Verification: Confirm 403 on CSRF-less admin POST; note: the telemetry `/api/copilot-agent-tracker/event` endpoint uses token-auth (not Drupal CSRF), so this AC targets Drupal admin-side state-changing routes only
- Implementation note: If no admin-side POST routes exist (read-only dashboard), document that explicitly; AC passes vacuously with a note

### AC-SEC-03: Input validation requirements — oversized or malformed agent_id rejected
- Traces to: site.instructions.md → Input validation requirements
- Suite: `forseti-copilot-agent-tracker-security`
- Scenario: `agent_id` > 64 chars or containing SQL metacharacters (`'`, `--`, `<script>`)
- Command must: POST with `agent_id` = 80-char string; confirm 400 rejection; separately POST with `agent_id` containing `<script>alert(1)</script>`, confirm it does not appear unescaped in dashboard HTML
- Expected: Oversized `agent_id` → HTTP 400; XSS payload → either 400 rejection OR stored but rendered HTML-escaped (no live script tag in dashboard response); exit 0 = PASS for both cases
- Verification: 80-char POST returns 400; if XSS payload accepted, GET dashboard and confirm response does not contain literal `<script>alert(1)</script>` unescaped

### AC-SEC-04: PII/logging constraints — no chat transcript or credentials in watchdog
- Traces to: site.instructions.md → PII/logging constraints
- Suite: `forseti-copilot-agent-tracker-security`
- Scenario: Normal telemetry POST cycle; inspect Drupal watchdog for leaked payload fields
- Command must: POST a valid event payload; run `vendor/bin/drush watchdog:show --count=20 --severity=debug --format=json`; confirm no `chat_log`, `transcript`, `token`, or credential-like field values appear in watchdog entries for `copilot_agent_tracker` type
- Expected: Watchdog entries for `copilot_agent_tracker` channel contain no raw payload; no PII fields; exit 0 = PASS
- Verification: `drush watchdog:show --count=20 --type=copilot_agent_tracker --format=json | python3 -c "import json,sys; rows=json.load(sys.stdin); assert not any('chat_log' in str(r) or 'transcript' in str(r) for r in rows), 'PII LEAK detected'"`

---

## Definition of done

- [ ] All 4 suites added to `qa-suites/products/forseti-agent-tracker/suite.json` as new entries with `required_for_release: true`
- [ ] Each suite entry has a `command` that is a deterministic shell command: exit 0 = PASS, exit 1+ = FAIL
- [ ] At least 2 ACs verified per suite (≥8 total; this file specifies 4+ per suite)
- [ ] Security suite ACs (AC-SEC-01 through AC-SEC-04) trace to `site.instructions.md` subsections: Authentication/permission surface, CSRF expectations, Input validation requirements, PII/logging constraints
- [ ] No stub placeholders in this file
- [ ] `python3 scripts/qa-suite-validate.py` exits 0 after suite.json update

## Verification

- Per-suite command verification: run each suite `command` in `suite.json`; confirm exit 0 = PASS
- Schema validation: `python3 scripts/qa-suite-validate.py` exits 0 (validates all suite.json manifests)
- Security traceability: `grep -A2 "Authentication/permission surface\|CSRF expectations\|Input validation\|PII/logging" org-chart/sites/forseti.life/site.instructions.md` — each subsection must map to one AC-SEC-* entry above
- Manifest entry check: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti-agent-tracker/suite.json')); ids=[s['id'] for s in d['suites']]; assert all(x in ids for x in ['forseti-copilot-agent-tracker-route-acl','forseti-copilot-agent-tracker-api','forseti-copilot-agent-tracker-happy-path','forseti-copilot-agent-tracker-security']), 'missing suites'"`

## Security acceptance criteria

### Authentication/permission surface
- All `/admin/reports/copilot-agent-tracker/*` routes require Drupal permission `administer copilot agent tracker`; anonymous and unpermissioned users are denied (AC-ACL-01 through AC-ACL-04, AC-SEC-01)

### CSRF expectations
- Drupal admin-side state-changing routes protected by Drupal CSRF; telemetry API uses token-auth (separate header, not Drupal CSRF); both surfaces covered (AC-SEC-02)
- Split-route pattern required: GET-only and POST-only route entries separated; POST routes use `_csrf_token: 'TRUE'` in routing.yml (verify in `copilot_agent_tracker.routing.yml`)

### Input validation requirements
- `agent_id` max 64 chars enforced at controller; oversized and metacharacter-containing values rejected or sanitized before DB write (AC-API-05, AC-SEC-03)
- `current_action` max 512 chars enforced; payload body max 64 KB (AC-API-05)

### PII/logging constraints
- No chat transcript, credentials, or user-identifying payload fields written to Drupal watchdog (AC-SEC-04)
- Allowed stored fields: `agent_id`, `status`, `current_action`, `last_seen` timestamp — no raw payload blob

## Open questions

| OQ | Question | Recommendation |
|---|---|---|
| OQ-1 | Are there any admin-side POST/state-changing routes on the agent tracker dashboard, or is it read-only? | Assume read-only for now; AC-SEC-02 passes vacuously if no admin POST routes exist. Dev to confirm during implementation. |
| OQ-2 | Does AC-ACL-03 require a `FORSETI_COOKIE_AUTHENTICATED` env var provisioning step in the suite command, or can it use drush user:login for a non-admin user? | Recommend drush auto-provision (same pattern as FORSETI_COOKIE_ADMIN); dev to implement. |
