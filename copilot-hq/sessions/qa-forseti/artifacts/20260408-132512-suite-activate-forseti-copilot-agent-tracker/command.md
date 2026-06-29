# Suite Activation: forseti-copilot-agent-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T13:25:12+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-copilot-agent-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-copilot-agent-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-copilot-agent-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-copilot-agent-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-copilot-agent-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-copilot-agent-tracker-<route-slug>",
     "feature_id": "forseti-copilot-agent-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-copilot-agent-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-copilot-agent-tracker

**Feature:** `forseti-copilot-agent-tracker`
**Module:** `copilot_agent_tracker`
**QA owner:** `qa-forseti-agent-tracker`
**PM owner:** `pm-forseti-agent-tracker`
**Version:** 1.0 — 2026-04-08
**AC source:** `features/forseti-copilot-agent-tracker/01-acceptance-criteria.md`

---

## Environment notes
- Production server IS the test environment: `https://forseti.life`
- `ALLOW_PROD_QA=1` required for `scripts/site-audit-run.sh`
- Token: `drush state:get copilot_agent_tracker.telemetry_token` (read at test runtime)
- All curl tests: substitute `TOKEN` with actual token value; substitute `BASE_URL` with `https://forseti.life`

---

## Happy path tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-HP-01 | AC-HP-01 | Admin dashboard loads with agent list | At least one agent event previously posted | Log in as admin; GET `/admin/reports/copilot-agent-tracker` | Dashboard renders; table with columns: agent id, last status, last action, last-seen | PASS: 200, table visible, no PHP warnings. FAIL: non-200 or PHP error |
| TP-HP-02 | AC-HP-02 | Agent detail page loads | Agent with at least one event in DB | GET `/admin/reports/copilot-agent-tracker/agent/{known_agent_id}` | Detail page renders; event stream visible; no raw chat content | PASS: 200, events listed. FAIL: non-200, PHP error, or chat transcript visible |
| TP-HP-03 | AC-HP-03 | Telemetry POST creates new agent | No prior record for `agent_id: test-groom-001` | `curl -s -w "\n%{http_code}" -X POST BASE_URL/api/copilot-agent-tracker/event -H "X-Copilot-Agent-Tracker-Token: TOKEN" -H "Content-Type: application/json" -d '{"agent_id":"test-groom-001","status":"in_progress","current_action":"grooming test"}'` | 200 or 201; `SELECT * FROM copilot_agent_tracker_agents WHERE agent_id = 'test-groom-001'` returns 1 row | PASS: 200/201 + row in DB. FAIL: other status or no DB row |
| TP-HP-04 | AC-HP-04 | Upsert — no duplicate agent row | `test-groom-001` already in DB from TP-HP-03 | Re-run same POST with same `agent_id` | DB still has exactly 1 row for `test-groom-001`; no duplicate | PASS: 1 row only. FAIL: 2+ rows |
| TP-HP-05 | AC-HP-05 | Dashboard reflects updated event | TP-HP-03 completed | GET `/admin/reports/copilot-agent-tracker` | `test-groom-001` row shows `current_action: "grooming test"` and recent last-seen | PASS: updated values visible. FAIL: stale or missing data |

---

## Telemetry API — authentication tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-AUTH-01 | AC-AUTH-01 | No token → 403 | None | `curl -s -o /dev/null -w "%{http_code}" -X POST BASE_URL/api/copilot-agent-tracker/event` | 403 | PASS: 403. FAIL: any other code |
| TP-AUTH-02 | AC-AUTH-02 | Wrong token → 403 | None | Same as TP-AUTH-01 with `-H "X-Copilot-Agent-Tracker-Token: definitely-wrong"` | 403 | PASS: 403. FAIL: any other code |
| TP-AUTH-03 | AC-AUTH-03 | Empty token → 403 | None | Same with `-H "X-Copilot-Agent-Tracker-Token: "` | 403 | PASS: 403. FAIL: any other code |
| TP-AUTH-04 | AC-AUTH-04 | Correct token + empty body → 400 | None | `curl ... -H "X-Copilot-Agent-Tracker-Token: TOKEN" -H "Content-Type: application/json" -d ""` | 400; body has error message; no 500 | PASS: 400 + error message. FAIL: 500 or 200 |
| TP-AUTH-05 | AC-AUTH-05 | Correct token + invalid JSON → 400 | None | `curl ... -H "X-Copilot-Agent-Tracker-Token: TOKEN" -H "Content-Type: application/json" -d '{"bad": }'` | 400; no 500 | PASS: 400. FAIL: 500 |

---

## Telemetry API — payload validation tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-PAY-01 | AC-PAY-01 | Missing `agent_id` → 400 | None | POST with valid token; payload: `{"status":"done","current_action":"no id here"}` | 400; response body cites `agent_id` field | PASS: 400 + field name in response. FAIL: 500 or 200 |
| TP-PAY-02 | AC-PAY-02 | PII stripped — no `chat_log` in DB | None | POST with valid token; payload includes `"chat_log":"very sensitive content","agent_id":"test-pii-001","status":"done","current_action":"pii test"` | 200/201; `SELECT * FROM copilot_agent_tracker_events WHERE agent_id = 'test-pii-001'` — no `chat_log` column or value in stored row | PASS: no `chat_log` in DB. FAIL: `chat_log` stored |
| TP-PAY-03 | AC-PAY-03 | Oversized payload → 400 or 413 | None | POST with valid token; body = 65 KB of `{"agent_id":"bigtest","status":"done","current_action":"` + `A` × 65000 + `"}` | 400 or 413; no 500; no DB overflow | PASS: 400 or 413. FAIL: 500 or 200 |
| TP-PAY-04 | AC-PAY-04 | Unknown status enum | None | POST with valid token; `{"agent_id":"test-enum-001","status":"totally_unknown","current_action":"enum test"}` | 200 or 422; if 200, dashboard renders status without crash | PASS: 200 or 422 + no crash on dashboard. FAIL: 500 |
| TP-PAY-05 | AC-PAY-05 | `agent_id` > 64 chars → 400 | None | POST with valid token; `agent_id` = 65-char string | 400 | PASS: 400. FAIL: 200 or 500 |
| TP-PAY-06 | AC-PAY-06 | `current_action` > 512 chars → 400 | None | POST with valid token; `current_action` = 513-char string | 400 | PASS: 400. FAIL: 200 or 500 |

---

## Admin dashboard — access control tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-ACL-01 | AC-ACL-01 | Anonymous redirect | Logged out | `curl -sI BASE_URL/admin/reports/copilot-agent-tracker` | 302 redirect with `Location:` pointing to `/user/login` | PASS: 302 + Location header. FAIL: 403 raw page or 500 |
| TP-ACL-02 | AC-ACL-02 | No permission → 403 | Logged in as user without `administer copilot agent tracker` | GET dashboard as limited user | 403 Access Denied | PASS: 403. FAIL: 200 or 302 to home |
| TP-ACL-03 | AC-ACL-03 | Unknown agent_id → 404 | Logged in as admin | GET `/admin/reports/copilot-agent-tracker/agent/nonexistent-zzz` | 404 Not Found; no 500 | PASS: 404. FAIL: 500 or 200 |
| TP-ACL-04 | AC-ACL-04 | Path traversal in agent_id | Logged in as admin | GET `/admin/reports/copilot-agent-tracker/agent/..%2Fadmin` | 404 or 400; no 200; no path disclosure | PASS: 404 or 400. FAIL: 200 or 500 |

---

## Admin dashboard — display tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-DISP-01 | AC-DISP-01 | Empty state message | Fresh module install or truncated tables | GET dashboard as admin | "No agents recorded" (or equivalent non-blank message); no PHP warning | PASS: message visible, no warning. FAIL: blank page or PHP error |
| TP-DISP-02 | AC-DISP-02 | Long `current_action` — listing truncated | Seed agent with 2000-char `current_action` via direct DB insert or POST | GET dashboard listing | Text truncated in list; no layout break; GET detail page shows full text | PASS: truncated in list, full in detail. FAIL: layout broken or 500 |
| TP-DISP-03 | AC-DISP-03 | Stale agent listed | Seed agent with `last_seen` timestamp > 30 days ago | GET dashboard | Agent appears in list; no crash; no PHP warning | PASS: listed normally. FAIL: 500 or omitted silently |
| TP-DISP-04 | AC-DISP-04 | High event volume — performance | Seed 1000+ events for one agent (use drush or direct DB insert) | GET `/admin/reports/copilot-agent-tracker/agent/{high-volume-agent}` | Page loads; measure time < 3s; pagination or limit visible | PASS: < 3s, no timeout. FAIL: > 3s or timeout |

---

## Module install / state tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-INST-01 | AC-INST-01 | Token auto-generated on install | Module freshly enabled (or state cleared) | `drush state:get copilot_agent_tracker.telemetry_token` | Returns non-empty string | PASS: non-empty token. FAIL: empty or key not found |
| TP-INST-02 | AC-INST-02 | Reinstall idempotent | Module installed with existing data | `drush pm:uninstall copilot_agent_tracker && drush pm:enable copilot_agent_tracker` | No DB errors; no orphan tables; both `copilot_agent_tracker_agents` and `copilot_agent_tracker_events` tables recreated | PASS: clean install, no errors. FAIL: DB error or orphan tables |

---

## Security tests

| Test ID | AC ref | Description | Setup | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|---|
| TP-SEC-01 | AC-SEC-01 | All dashboard routes gated | None | `curl -sI BASE_URL/admin/reports/copilot-agent-tracker` (no session); `curl -sI BASE_URL/admin/reports/copilot-agent-tracker/agent/test` (no session) | Both return 302 to login (unauthenticated); limited user returns 403 | PASS: 302/403 as appropriate. FAIL: any 200 |
| TP-SEC-02 | AC-SEC-02 | CSRF — state-changing POST rejected without token | None | POST to any state-changing route without Drupal `?token=` CSRF parameter | 403 or 400; request rejected | PASS: 403/400. FAIL: 200 |
| TP-SEC-03 | AC-SEC-03 | No raw PII in DB after POST | None | POST payload with disallowed fields (e.g., `chat_log`); inspect DB | Disallowed fields absent from stored row | PASS: no disallowed fields in DB. FAIL: any disallowed field stored |
| TP-SEC-04 | AC-SEC-04 | No PII in watchdog | Normal telemetry cycle | POST several valid events; `drush watchdog:show --count=20` | No chat transcript, credentials, or user-identifying payload content in log entries | PASS: no PII in watchdog. FAIL: any sensitive content visible |

---

## AC ↔ Test mapping

| AC-ID | Test ID(s) |
|---|---|
| AC-HP-01 | TP-HP-01 |
| AC-HP-02 | TP-HP-02 |
| AC-HP-03 | TP-HP-03 |
| AC-HP-04 | TP-HP-04 |
| AC-HP-05 | TP-HP-05 |
| AC-AUTH-01 | TP-AUTH-01 |
| AC-AUTH-02 | TP-AUTH-02 |
| AC-AUTH-03 | TP-AUTH-03 |
| AC-AUTH-04 | TP-AUTH-04 |
| AC-AUTH-05 | TP-AUTH-05 |
| AC-PAY-01 | TP-PAY-01 |
| AC-PAY-02 | TP-PAY-02 |
| AC-PAY-03 | TP-PAY-03 |
| AC-PAY-04 | TP-PAY-04 |
| AC-PAY-05 | TP-PAY-05 |
| AC-PAY-06 | TP-PAY-06 |
| AC-ACL-01 | TP-ACL-01 |
| AC-ACL-02 | TP-ACL-02 |
| AC-ACL-03 | TP-ACL-03 |
| AC-ACL-04 | TP-ACL-04 |
| AC-DISP-01 | TP-DISP-01 |
| AC-DISP-02 | TP-DISP-02 |
| AC-DISP-03 | TP-DISP-03 |
| AC-DISP-04 | TP-DISP-04 |
| AC-INST-01 | TP-INST-01 |
| AC-INST-02 | TP-INST-02 |
| AC-SEC-01 | TP-SEC-01 |
| AC-SEC-02 | TP-SEC-02 |
| AC-SEC-03 | TP-SEC-03 |
| AC-SEC-04 | TP-SEC-04 |

---

## KB reference
- KB: none found for copilot_agent_tracker QA patterns specifically. CSRF split-route pattern from `knowledgebase/` memory: GET+POST split required (GET-only route has no `_csrf_token`; POST-only has `_csrf_token: 'TRUE'`).

## Notes
- TP-DISP-04 (high volume) requires seeding 1000 events; use direct DB insert or a helper script if drush not sufficient. Agreed performance threshold: < 3s page load.
- TP-PAY-04 pass criteria allows either 200 or 422 — confirm with dev-forseti-agent-tracker which behavior is implemented before Gate 2.
- Token is in Drupal state (not config); will not be in git diff. Read from `drush state:get` at test runtime.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-copilot-agent-tracker

**Feature:** `forseti-copilot-agent-tracker`
**Module:** `copilot_agent_tracker`
**PM owner:** `pm-forseti-agent-tracker`
**Version:** 1.0 — 2026-04-08
**Source:** BA edge-case supplement (`sessions/ba-forseti-agent-tracker/artifacts/forseti-release-coverage/copilot-agent-tracker-edge-cases.md`)

---

## Happy path

| AC-ID | Scenario | Given | When | Then | HTTP status | Permission role |
|---|---|---|---|---|---|---|
| AC-HP-01 | Admin lists agents | User has `administer copilot agent tracker` permission | GET `/admin/reports/copilot-agent-tracker` | Dashboard renders with agent list (id, last status, last action, last-seen timestamp) | 200 | `administer copilot agent tracker` |
| AC-HP-02 | Admin views agent detail | User has `administer copilot agent tracker` permission | GET `/admin/reports/copilot-agent-tracker/agent/{agent_id}` for existing agent | Detail page renders sanitized event stream; no raw chat content | 200 | `administer copilot agent tracker` |
| AC-HP-03 | Telemetry POST — new agent | Valid `X-Copilot-Agent-Tracker-Token` header; valid JSON payload including `agent_id`, `status`, `current_action` | POST `/api/copilot-agent-tracker/event` | Event appended to `copilot_agent_tracker_events`; new row upserted in `copilot_agent_tracker_agents`; response is success | 200 or 201 | Token-auth (server-side header) |
| AC-HP-04 | Telemetry POST — existing agent upsert | Valid token; same `agent_id` as prior POST | POST `/api/copilot-agent-tracker/event` | `copilot_agent_tracker_agents` still has exactly 1 row for that `agent_id`; event appended | 200 | Token-auth |
| AC-HP-05 | Dashboard reflects new event | Prior POST succeeded | GET `/admin/reports/copilot-agent-tracker` | Updated `last_action`/`last_seen` visible for the agent | 200 | `administer copilot agent tracker` |

---

## Telemetry API — authentication

| AC-ID | Scenario | Given | When | Then | HTTP status | Permission role |
|---|---|---|---|---|---|---|
| AC-AUTH-01 | No token header | No `X-Copilot-Agent-Tracker-Token` header | POST `/api/copilot-agent-tracker/event` | Request denied; body indicates auth failure | 403 | — |
| AC-AUTH-02 | Wrong token value | `X-Copilot-Agent-Tracker-Token: wrong-value` | POST | Request denied | 403 | — |
| AC-AUTH-03 | Empty token header | `X-Copilot-Agent-Tracker-Token:` (empty string) | POST | Request denied (empty token not accepted) | 403 | — |
| AC-AUTH-04 | Correct token + empty body | Valid token; empty request body | POST | Request rejected with descriptive error; no 500 | 400 | Token-auth |
| AC-AUTH-05 | Correct token + invalid JSON | Valid token; malformed JSON body (e.g., `{"bad": }`) | POST | Request rejected; no 500 | 400 | Token-auth |

---

## Telemetry API — payload validation

| AC-ID | Scenario | Given | When | Then | HTTP status | Permission role |
|---|---|---|---|---|---|---|
| AC-PAY-01 | `agent_id` missing | Valid token; payload without `agent_id` field | POST | Rejected with error citing missing field; no 500 | 400 | Token-auth |
| AC-PAY-02 | PII stripping | Valid token; payload includes `chat_log` or other transcript field | POST | System stores only allowed fields; `chat_log` not present in DB; no PII stored | 200 | Token-auth |
| AC-PAY-03 | Oversized payload | Valid token; body > 64 KB | POST | Rejected; no DB overflow; no 500 | 400 or 413 | Token-auth |
| AC-PAY-04 | Unknown `status` enum value | Valid token; `status` set to unrecognized string | POST | Stored as-is OR rejected with 422; dashboard renders unknown status without crash | 200 or 422 | Token-auth |
| AC-PAY-05 | `agent_id` length limit | Valid token; `agent_id` > 64 chars | POST | Rejected with validation error | 400 | Token-auth |
| AC-PAY-06 | `current_action` length limit | Valid token; `current_action` > 512 chars | POST | Rejected with validation error | 400 | Token-auth |

---

## Admin dashboard — access control

| AC-ID | Scenario | Given | When | Then | HTTP status | Permission role |
|---|---|---|---|---|---|---|
| AC-ACL-01 | Anonymous user — dashboard | User not logged in | GET `/admin/reports/copilot-agent-tracker` | Redirect to login page (not 403 or 500) | 302 → `/user/login` | Anonymous |
| AC-ACL-02 | Authenticated user without permission | User logged in; does NOT have `administer copilot agent tracker` | GET dashboard | Access denied | 403 | Authenticated (no permission) |
| AC-ACL-03 | Unknown agent_id — detail page | User has permission | GET `/admin/reports/copilot-agent-tracker/agent/nonexistent` | 404 Not Found; no 500 | 404 | `administer copilot agent tracker` |
| AC-ACL-04 | Path traversal in agent_id | User has permission | GET with `agent_id` containing `../admin` or similar | 404 or 400; no path disclosure; no 200 or 500 | 404 or 400 | `administer copilot agent tracker` |

---

## Admin dashboard — display

| AC-ID | Scenario | Given | When | Then | HTTP status | Permission role |
|---|---|---|---|---|---|---|
| AC-DISP-01 | Empty state — no agents | Fresh module install; no events posted | GET dashboard | "No agents recorded" message (or equivalent); no blank page; no PHP warning | 200 | `administer copilot agent tracker` |
| AC-DISP-02 | Long `current_action` truncation | Agent with 2000-char `current_action` seeded | GET dashboard listing | Text truncated in list view; no layout break; full text on detail page | 200 | `administer copilot agent tracker` |
| AC-DISP-03 | Stale agent (>30d) | Agent with last-seen timestamp > 30 days ago | GET dashboard | Agent listed; no crash; no PHP warning | 200 | `administer copilot agent tracker` |
| AC-DISP-04 | High event volume | Agent with 1000+ events | GET agent detail page | Page loads under 3 seconds; results paginated or limited; no timeout | 200 | `administer copilot agent tracker` |

---

## Module install / state

| AC-ID | Scenario | Given | When | Then | HTTP status | Permission role |
|---|---|---|---|---|---|---|
| AC-INST-01 | No token set on install | Module freshly enabled; no prior token in state | After enable | Token auto-generated; `drush state:get copilot_agent_tracker.telemetry_token` returns non-empty string | — | Admin (drush) |
| AC-INST-02 | Reinstall idempotency | Module previously installed with data | `drush pm:uninstall copilot_agent_tracker && drush pm:enable copilot_agent_tracker` | Schema recreated cleanly; no orphan tables; no DB errors | — | Admin (drush) |

---

## Security acceptance criteria (required pre-activation gate)

| AC-ID | Scenario | Given | When | Then | Verification |
|---|---|---|---|---|---|
| AC-SEC-01 | Dashboard routes require permission | Any user | GET any `/admin/reports/copilot-agent-tracker/*` route | Access gated by `administer copilot agent tracker` Drupal permission | `drush php-eval` role check; curl returns 302/403 for unauthenticated |
| AC-SEC-02 | CSRF on state-changing endpoints | No CSRF token | POST to any state-changing route without Drupal CSRF token | 403 or 400 response; request rejected | Attempt CSRF-less POST; confirm rejection |
| AC-SEC-03 | Input sanitized before DB write | Malformed or oversized payload | POST with boundary-violating data | No unsanitized value written to DB; validated or rejected at controller | Inspect DB after attempt |
| AC-SEC-04 | No PII in watchdog | Normal telemetry cycle | POST events normally | Drupal watchdog contains no chat transcript, credentials, or user-identifying payload fields | `drush watchdog:show` after publish cycle |

---

## Notes
- Token is in Drupal state (`state:get copilot_agent_tracker.telemetry_token`), not config export — will not appear in `git diff` of config files.
- All dashboard routes require `administer copilot agent tracker` — confirmed in `copilot_agent_tracker.permissions.yml` and `copilot_agent_tracker.routing.yml`.
- AC-PAY-04 allows either store-as-is or reject-422 for unknown enum values; QA should confirm with PM/Dev which behavior is implemented and verify accordingly.
- AC-AUTH-05 relates to the CSRF split-route pattern (GET-only vs POST-only routes); see `site.instructions.md` security note on CSRF routing.
- Agent: qa-forseti
- Status: pending
