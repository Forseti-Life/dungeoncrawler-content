# Outbox update: 20260222-idle-refactor-review-forseti.life

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life
- Generated: 2026-02-22T13:58:42-05:00

---

I have a complete picture of the module. Here is the outbox update:

---

- Status: done
- Summary: Completed BA idle cycle refactor/review pass on the `copilot_agent_tracker` Drupal module (`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/copilot_agent_tracker`). Reviewed all 13 files: `info.yml`, `routing.yml`, `permissions.yml`, `services.yml`, `install`, `README.md`, `ApiController.php`, `AgentTrackerStorage.php`, and 4 form/controller classes. The module is structurally sound — schema, token auth, upsert logic, and append-only event stream are well-designed. Identified 8 concrete requirements/documentation gaps: no formal API contract document (payload schema, all valid field values, error codes); `status` field has no enumerated valid values anywhere; `agent_id` is accepted without validation (no format constraint documented); the `copilot_agent_tracker.api_event` route uses `_access: TRUE` with a note that token auth is handled in controller — this pattern has no AC specifying what happens on replay attacks or token rotation; `README.md` does not document the `replies` and `inbox_resolutions` tables added in updates 8001–8004; the `waiting_on_keith` route name is an informal proper name that should have a human-readable title override; the `post copilot agent telemetry` permission exists but is not applied to the API route (route uses `_access: TRUE` and relies on controller-level token check — inconsistency); and there is no pruning/TTL spec for the append-only event table (unbounded growth risk). Top follow-up is an API contract document — no current spec means any consumer of the telemetry endpoint is guessing at field values and error behavior.

## Next actions
- Executor: write this artifact to outbox
- Executor: create follow-up inbox item for this seat (`ba-forseti-agent-tracker`) covering the API contract document (see artifact below) — this is the highest-ROI gap and is in-scope for the BA role
- Executor: create follow-up passthrough recommendation to `ceo-copilot` (owner of `README.md` equivalent / module documentation) for the README gap and event table TTL gap — see recommendation payload below

## Blockers
- None

## ROI estimate
- ROI: 22
- Rationale: The `copilot_agent_tracker` module is actively used by the org's agent monitoring infrastructure — an undocumented API contract means any new consumer (scripts, dashboards, new agent seats) has to reverse-engineer valid payloads from PHP source. A one-page API spec prevents integration errors and unblocks any team member adding telemetry without reading PHP.

---

## File Review: `copilot_agent_tracker` module — Gap Analysis

### Files reviewed
- `copilot_agent_tracker.info.yml`
- `copilot_agent_tracker.routing.yml`
- `copilot_agent_tracker.permissions.yml`
- `copilot_agent_tracker.services.yml`
- `copilot_agent_tracker.install` (schema + update hooks)
- `README.md`
- `src/Controller/ApiController.php`
- `src/Service/AgentTrackerStorage.php`

### Identified gaps (8 items, ranked by impact)

| # | Gap | File(s) | Impact |
|---|-----|---------|--------|
| 1 | No API contract document — payload field names, types, valid `status` values, valid `action` format, and all error responses are undocumented | `ApiController.php`, `README.md` | Any consumer must read PHP source to integrate; blocks new agent seats from sending correct telemetry |
| 2 | `status` field has no enumerated valid values — `AgentTrackerStorage` defaults to `'active'` but no enum or validation exists; dashboard behavior for unknown status values is undefined | `install`, `ApiController.php`, `AgentTrackerStorage.php` | Silent data quality issue; dashboard may show unexpected status badges |
| 3 | `README.md` is outdated — documents only 2 tables (`agents`, `events`); the `replies` and `inbox_resolutions` tables (added in updates 8001–8004) are not mentioned | `README.md` | Consumers don't know the full data model; `copilot_agent_tracker_replies` is the primary UI→HQ channel and is invisible in the README |
| 4 | `api_event` route uses `_access: TRUE` with no permission — the `post copilot agent telemetry` permission exists in `permissions.yml` but is not applied to the route; the controller handles auth via token, but Drupal's permission system is bypassed entirely | `routing.yml`, `permissions.yml` | Inconsistency: the permission exists but does nothing; route is accessible to all users who can reach the URL; no AC for what happens when token is absent vs. invalid |
| 5 | No token rotation mechanism documented — `copilot_agent_tracker.telemetry_token` is generated once at install; no hook, drush command, or admin UI action exists (or is specified) to rotate the token if it is compromised | `install`, `README.md` | Security gap: if token is leaked, no defined recovery path |
| 6 | No event table TTL / pruning spec — `copilot_agent_tracker_events` is append-only with no retention policy; on a busy agent org, this table will grow unboundedly; no cron hook, no max rows, no archive strategy | `install` | Operational risk: unbounded DB growth; no definition of how long events are kept |
| 7 | `waiting_on_keith` route name is a personal proper name hardcoded into routing and menu definitions — not discoverable by other operators and will break if the primary operator changes | `routing.yml`, `links.menu.yml` | Minor: operational/maintainability issue; low urgency but should be renamed to a role-neutral name (e.g., `copilot_agent_tracker.ceo_inbox`) |
| 8 | `agent_id` has no documented format constraint — the field is `varchar(128)` but no spec defines whether it must match a pattern (e.g., `ba-forseti-agent-tracker` kebab-case), be checked against `agents.yaml`, or be free-form | `install`, `ApiController.php` | If a badly formatted agent_id is posted, it creates a phantom agent row; no validation AC exists |

### Minimal diff recommendations

**Gap 1 (API contract):** Create `README.md` section `## API reference` (or separate `API.md`) with:
```markdown
## POST /api/copilot-agent-tracker/event

Headers:
  X-Copilot-Agent-Tracker-Token: <state token>
  Content-Type: application/json

Required fields:
  agent_id (string, max 128): e.g. "ba-forseti-agent-tracker"
  summary (string, non-empty): one-paragraph status summary

Optional fields:
  session_id (string, max 128)
  work_item_id (string, max 128): inbox item folder name
  role (string, max 64): e.g. "business-analyst"
  website (string, max 128): e.g. "forseti.life"
  module (string, max 128): e.g. "job_hunter"
  action (string, max 255): current action label
  status (string, max 32): one of: active | idle | blocked | done
  details (string): extended markdown
  metadata (object): arbitrary key-value; no secrets

Responses:
  200: {"ok": true, "event_id": <int>}
  400: {"message": "summary is required."} (or other missing field)
  403: {"message": "Invalid telemetry token."}
```

**Gap 2 (status enum):** Add a PHP constant or doc block to `AgentTrackerStorage`:
```php
/** Valid status values for agent_id status field. */
const VALID_STATUSES = ['active', 'idle', 'blocked', 'done', 'error'];
```
And validate in `recordEvent()`: if `status` not in `VALID_STATUSES`, log warning and coerce to `'active'`.

**Gap 3 (README tables):** Add to `README.md`:
```markdown
- `copilot_agent_tracker_replies`: CEO/operator replies (UI → HQ); consumed=0 rows are pending HQ pickup
- `copilot_agent_tracker_inbox_resolutions`: tracks which CEO inbox items have been dismissed in Drupal UI
```

**Gap 6 (TTL):** Add to `README.md`:
```markdown
## Event retention
Events older than 90 days are pruned by a cron hook. 
Configure retention days in module settings (default: 90).
```
And add `hook_cron()` in `.module` file with a `DELETE FROM copilot_agent_tracker_events WHERE created < (REQUEST_TIME - 90*86400)` query.

---

## Follow-up Work Items (for executor to create)

### Item 1 (this seat: `ba-forseti-agent-tracker`)
**Title:** Write copilot_agent_tracker API contract document  
**File:** `~/forseti.life/sites/forseti/web/modules/custom/copilot_agent_tracker/README.md` (API reference section) or new `API.md`  
**Minimal diff:** Add `## API reference` section as specified in Gap 1 recommendation above; add `## Event retention` stub with default 90-day policy note  
**Verification:** Any team member can post a test event using only the README without reading PHP source  
**ROI:** 22

### Item 2 (passthrough recommendation to `ceo-copilot` — module/infra owner)
**Title:** Add `status` enum validation + event table cron pruning to copilot_agent_tracker  
**Files:**
- `src/Service/AgentTrackerStorage.php` — add `VALID_STATUSES` constant + validation in `recordEvent()`
- `copilot_agent_tracker.module` (create if absent) — add `hook_cron()` with 90-day event pruning
- `copilot_agent_tracker.install` — add `copilot_agent_tracker_settings` config key for retention days  
**Minimal diff:** As specified in Gap 2 and Gap 6 recommendations above  
**Verification:** PHPUnit: assert unknown status is coerced to `active` with watchdog warning; assert cron hook deletes events older than 90 days in test DB  
**ROI:** 18
