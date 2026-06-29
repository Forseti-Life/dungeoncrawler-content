# BA Edge-Case Supplement: copilot_agent_tracker

**Seat:** ba-forseti-agent-tracker
**Generated:** 2026-02-28
**Release context:** 2026-02-28 forseti.life release cycle
**Feature:** `forseti-copilot-agent-tracker` (P1, in_progress)
**Module:** `web/modules/custom/copilot_agent_tracker`

---

## Scope
- Admin dashboard: `/admin/reports/copilot-agent-tracker` and `/admin/reports/copilot-agent-tracker/agent/{agent_id}`
- Telemetry API: `POST /api/copilot-agent-tracker/event` (header-token auth)
- Sanitized event stream (no raw chat transcripts)

## Non-goals
- Storing raw Copilot chat logs or credentials
- Public (unauthenticated) access to any dashboard or event data
- Cross-site tracking or multi-site aggregation

---

## Happy path (end-to-end)

1. Authenticated user with `administer copilot agent tracker` permission navigates to `/admin/reports/copilot-agent-tracker`.
2. Dashboard loads with a list of agents (id, last status, last action, last-seen timestamp).
3. User clicks an agent to view `/admin/reports/copilot-agent-tracker/agent/{agent_id}`.
4. Agent detail page shows sanitized event stream (no raw chat content).
5. A Copilot agent POSTs to `/api/copilot-agent-tracker/event` with `X-Copilot-Agent-Tracker-Token` header and valid JSON payload.
6. Server returns `200 OK`; event is appended to `copilot_agent_tracker_events` and agent row upserted in `copilot_agent_tracker_agents`.
7. Dashboard reflects the new event on next page load.

---

## Edge cases

### Telemetry API — auth

| Edge case | Expected behavior | Verification |
|---|---|---|
| POST with no `X-Copilot-Agent-Tracker-Token` header | 403 Access Denied, not 404 | `curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost/api/copilot-agent-tracker/event` → `403` |
| POST with wrong token value | 403 Access Denied | Same curl with `--header "X-Copilot-Agent-Tracker-Token: wrong"` → `403` |
| POST with empty token header | 403 Access Denied | `--header "X-Copilot-Agent-Tracker-Token: "` → `403` |
| POST with correct token but empty body | 400 Bad Request, not 500 | Correct header + empty body → `400` |
| POST with correct token but invalid JSON | 400 Bad Request | Correct header + `{"bad": }` → `400` |
| POST with correct token and valid payload (new agent_id) | 201 or 200; new row in agents table | Check DB: `SELECT * FROM copilot_agent_tracker_agents WHERE agent_id = 'test-agent'` |
| POST with correct token and valid payload (existing agent_id) | Upsert; no duplicate row | After two posts for same agent_id, agents table still has 1 row |

### Telemetry API — payload validation

| Edge case | Expected behavior | Verification |
|---|---|---|
| `agent_id` missing from payload | 400 with descriptive error | Response body contains field name |
| `agent_id` contains raw chat transcript (PII risk) | System stores only sanitized fields; transcript fields stripped or rejected | Check stored event: no `chat_log` field |
| Payload exceeds reasonable size limit (e.g., >64KB) | 400 or 413; no DB overflow | POST oversized body → non-500 response |
| `status` field is an unexpected enum value | Stored as-is OR rejected with 422; no crash | Dashboard renders unknown status gracefully |

### Admin dashboard — access control

| Edge case | Expected behavior | Verification |
|---|---|---|
| Anonymous user accesses `/admin/reports/copilot-agent-tracker` | Redirect to login (not raw 403 or 500) | `curl -sI http://localhost/admin/reports/copilot-agent-tracker` → `302` to `/user/login` |
| Authenticated user without `administer copilot agent tracker` permission | 403 Access Denied | Log in as limited user; GET dashboard → `403` |
| Agent detail page for unknown `agent_id` | 404 Not Found, not 500 | `/admin/reports/copilot-agent-tracker/agent/nonexistent` → `404` |
| `agent_id` contains path traversal characters (e.g., `../admin`) | 404 or 400; no path disclosure | URL-encode traversal attempt → no 200 or 500 |

### Admin dashboard — display

| Edge case | Expected behavior | Verification |
|---|---|---|
| No agents in DB (fresh install) | Empty state message, not blank page or PHP warning | After fresh module install, dashboard shows "No agents recorded" or equivalent |
| Agent with very long `current_action` string | Truncated in listing, full text on detail page | Seed a 2000-char `current_action`; listing renders without layout break |
| Agent last seen > 30 days ago (stale) | No crash; stale agents are still listed | Seed old timestamp; dashboard renders without error |
| Event stream has 1000+ events for one agent | Detail page paginates or limits display; no timeout | Seed 1000 events; detail page loads under 3s |

### Module install / state

| Edge case | Expected behavior | Verification |
|---|---|---|
| Module enabled with no telemetry token set in state | Token auto-generated on install; dashboard shows token to admins | `drush state:get copilot_agent_tracker.telemetry_token` returns non-empty string |
| Module uninstalled then reinstalled | Schema recreated cleanly; no orphan tables | `drush pm:uninstall copilot_agent_tracker && drush pm:enable copilot_agent_tracker` → no DB errors |

---

## Open questions + recommended defaults

| Question | Recommendation | Rationale |
|---|---|---|
| What fields are required in the telemetry payload? | Require: `agent_id`, `status`, `current_action`; all others optional | Minimum needed to populate the dashboard usefully |
| Should stale agents (>30d no event) be auto-pruned? | No auto-prune by default; add admin setting in v2 | Avoids data loss; safe default |
| Is there a max event retention period? | No limit in v1; add DB size monitoring runbook item | Simplest v1 approach; revisit at 100k events |
| Should the token be rotatable via admin UI? | Yes — add rotation button in v2; for now, drush state:set is acceptable | Reduces admin friction for key rotation |

---

## Notes for QA
- Confirm module install script (`copilot_agent_tracker.install`) creates both tables cleanly.
- Token value is in Drupal state (not config export); will not appear in `git diff` of config files.
- All dashboard routes require `administer copilot agent tracker` permission — confirmed in `copilot_agent_tracker.permissions.yml` and `copilot_agent_tracker.routing.yml`.
