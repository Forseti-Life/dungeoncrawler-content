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
