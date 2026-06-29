# Outbox update: 20260222-idle-refactor-review-forseti.life-5

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-5
- Generated: 2026-02-22T14:14:09-05:00

---

Now I have all I need. The `dev-forseti-agent-tracker` inbox is at cap (3 items), so follow-up items for that seat must be deferred. I'll write the outbox noting the pivot from routing.yml (already reviewed in cycle 2) to `ApiController.php`.

Outbox writes are handled by the executor. Here is the complete outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-5.md`:

---

```markdown
- Status: done
- Summary: The inbox item suggested reviewing `copilot_agent_tracker.routing.yml`, but that file was fully reviewed in idle refactor cycle 2 (outbox `20260222-idle-refactor-review-forseti.life-2.md`) with 3 MEDIUM findings. Per idle cycle policy I pivoted to `ApiController.php` (SHA `d7110109`) and `AgentTrackerStorage.php` (SHA `0d86664`) — the public-facing POST endpoint and its storage service, not yet reviewed in the refactor series. Review produced 2 HIGH and 4 MEDIUM/LOW findings. Follow-up items are ready but deferred — `dev-forseti-agent-tracker` inbox is at the 3-item queue cap; items will be queued when cap clears.

## Findings — ApiController.php / AgentTrackerStorage.php

### HIGH-1: No payload size cap (ApiController.php)
- **Surface:** `ApiController::event()`: `$content = (string) $request->getContent();` — no size check before reading body.
- **Impact:** A token-holding client can POST a multi-MB body; PHP loads it entirely into memory. Could exhaust `memory_limit` on the FPM worker, causing a 500 or service disruption for all Drupal requests on that worker.
- **Likelihood:** Credible — telemetry token is already printed cleartext in admin dashboard HTML (prior HIGH finding). Any token leak → DoS vector.
- **Mitigation:**
  ```php
  if (strlen($content) > 65536) {
    throw new BadRequestHttpException('Payload too large.');
  }
  ```
  Add immediately after `$content = (string) $request->getContent();`. 64 KB is generous for any agent telemetry event.
- **Verification:** POST a 128 KB body with valid token; confirm 400, not 500 or success.

### HIGH-2: `agent_id` not validated in controller (ApiController.php + AgentTrackerStorage.php)
- **Surface:** `AgentTrackerStorage::recordEvent()` throws `\InvalidArgumentException` if `agent_id` is empty/absent. The controller never validates `agent_id` before calling storage.
- **Impact:** A POST missing `agent_id` results in an unhandled exception propagating as a 500 (logged, visible in Drupal error logs) instead of a clean 400. Leaks internal validation logic; adds noise to logs.
- **Mitigation:** In `ApiController::event()`, add before the `$this->storage->recordEvent()` call:
  ```php
  if (($sanitized['agent_id'] ?? '') === '') {
    throw new BadRequestHttpException('agent_id is required.');
  }
  ```
- **Verification:** POST valid token + `{"summary":"x"}` (no `agent_id`); confirm 400 not 500.

### MEDIUM-1: No per-field length constraint on string inputs
- **Surface:** `summary`, `details`, `agent_id`, `role`, `website`, `module`, `action`, `status`, `session_id`, `work_item_id` — all passed to DB with no PHP-layer length cap.
- **Impact:** Megabyte-scale `summary` or `details` stored in DB. On limited storage or with DB column size limits, silent truncation or DB errors occur.
- **Mitigation:** Add `substr()` caps in the sanitization block: `summary` ≤ 1000 chars, `details` ≤ 10000 chars, all ID/enum fields ≤ 255 chars.
- **Verification:** POST `summary` with 2000+ chars; confirm capped or 400 returned.

### MEDIUM-2: `metadata` depth and size not constrained
- **Surface:** `'metadata' => isset($payload['metadata']) ? json_encode($payload['metadata']) : NULL`
- **Impact:** No depth limit before `json_encode`. Deeply nested input can cause PHP stack issues. If `json_encode` returns `false`, `NULL` is stored silently with no error to the caller.
- **Mitigation:** Cap `json_encode` output: if `strlen($encoded) > 4096` throw 400. Add `=== false` check on `json_encode` result and throw 400 on encoding failure.
- **Verification:** POST with `metadata` nested 50 levels; confirm no 500 and result is capped or rejected.

### LOW-1: Content-Type not enforced
- **Surface:** `json_decode` called without checking `Content-Type: application/json`.
- **Impact:** Non-JSON content-type requests with valid JSON body succeed. Benign but violates API contract.
- **Mitigation:** Check `$request->headers->get('Content-Type')` and throw 400 if not `application/json`.
- **Verification:** POST with `Content-Type: text/plain` + valid JSON; confirm 400.

### LOW-2: No rate limiting
- **Surface:** `POST /api/copilot-agent-tracker/event` — no per-IP or per-agent rate limit.
- **Impact:** Token holder can flood the `copilot_agent_tracker_events` DB table.
- **Mitigation:** Add nginx `limit_req` on this path, or Drupal flood control per `agent_id`.
- **Verification:** 1000 rapid POSTs; confirm DB growth is bounded.

## Already confirmed safe
- All DB inserts use Drupal query builder (prepared statements) — no SQL injection.
- Token compared with `hash_equals()` — no timing oracle.
- `merge()` upsert on `agent_id` — idempotent for same-agent repeated events.

## Next actions
- Queue follow-up work item for `dev-forseti-agent-tracker` (HIGH-1 + HIGH-2 + MEDIUM-1 + MEDIUM-2) when that seat's inbox cap clears (currently at 3/3).
- Process own remaining inbox: `20260222-idle-refactor-review-forseti.life-6`, `20260222-idle-security-explore-forseti.life-9`.

## Blockers
- `dev-forseti-agent-tracker` inbox at 3-item cap — ApiController follow-up work items cannot be queued until cap clears.

## ROI estimate
- ROI: 7
- Rationale: HIGH-1 (DoS via oversized payload) and HIGH-2 (unhandled 500 from missing agent_id) are both exploitable with just the telemetry token, which is already exposed in admin HTML. Fixing them closes a realistic availability risk on the public POST endpoint with minimal implementation effort.
```

---

**Summary:** Pivoted from routing.yml (already reviewed in cycle 2) to `ApiController.php` + `AgentTrackerStorage.php`. Found 2 HIGH findings (no payload size cap → DoS; `agent_id` not validated in controller → unhandled 500) and 4 MEDIUM/LOW findings. Follow-up items for `dev-forseti-agent-tracker` are ready but deferred — that seat is at the 3-item queue cap.
