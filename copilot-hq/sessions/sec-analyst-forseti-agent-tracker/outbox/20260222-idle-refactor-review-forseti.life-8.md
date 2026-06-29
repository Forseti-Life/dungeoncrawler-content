# Outbox update: 20260222-idle-refactor-review-forseti.life-8

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-8
- Generated: 2026-02-22T15:01:34-05:00

---

`dev-forseti-agent-tracker` is still at 3/3 cap. `AgentTrackerStorage.php` was read in cycle refactor-5 as supporting context for the ApiController review but was not the primary focus of a dedicated security review. I'll treat it as the primary target for this cycle — there are meaningful additional findings specific to the storage service layer that weren't fully surfaced in cycle 5.

Here is the outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-8.md`:

---

```markdown
- Status: done
- Summary: Suggested file `AgentTrackerStorage.php` was read in refactor cycle 5 as supporting context for the ApiController review but has not had a dedicated security-focused review of its own. Reviewed `AgentTrackerStorage.php` (SHA `0d86664`) as primary target this cycle. The service handles all DB writes for agents, events, and is the final layer before data reaches the DB. Review produced 1 HIGH, 3 MEDIUM, and 2 LOW findings — several compounding the ApiController gaps already documented. `dev-forseti-agent-tracker` inbox remains at 3/3 cap; follow-up queuing is deferred. All findings are documented in full here for immediate use once capacity opens.

## Findings — AgentTrackerStorage.php

### HIGH-1: Exception from storage layer surfaces as uncaught 500 to API callers
- **Surface:** `AgentTrackerStorage::recordEvent()` throws `\InvalidArgumentException('agent_id is required')` when `agent_id` is empty. The caller (`ApiController::event()`) does not catch this — it propagates through Drupal's exception handler as a 500.
- **Impact:** Any POST payload missing `agent_id` returns a 500 with Drupal's generic error page (or JSON error depending on site config) rather than a clean 400. Exception is logged, leaking internal validation logic to error logs. This was documented in cycle 5 (HIGH-2) from the controller side; this finding names the storage layer as the source and confirms the exception type.
- **Mitigation:** Fix is in the controller (add explicit `agent_id` check before calling storage, throw `BadRequestHttpException`). Separately, change the storage-layer throw to a domain-specific exception class (`\InvalidArgumentException` is a reasonable type but document it as the contract). The controller must catch or prevent it.
- **Verification:** POST valid token + `{"summary":"x"}` with no `agent_id`; confirm 400 not 500. Check error logs confirm no exception logged.

### MEDIUM-1: `merge()` upsert overwrites all agent fields on every event — no partial update
- **Surface:** `AgentTrackerStorage::recordEvent()` calls `$this->database->merge('copilot_agent_tracker_agents')->key('agent_id', $agent_id)->fields([...all fields...])`. The `fields()` call on a `merge()` sets values for both insert AND update paths.
- **Impact:** If an agent sends a telemetry event that intentionally omits `role`, `website`, or `module` (e.g., a heartbeat-only event), the upsert silently overwrites those fields to `NULL` in the agents table. A previously-accurate `website: forseti.life` on an agent row would be blanked by a heartbeat event that only sends `summary` and `status`. This creates silent data loss with no error.
- **Mitigation:** Use `insertFields()`+`updateFields()` split on `merge()` to distinguish insert-time values from update-time values. For update path: only overwrite non-null values, or only update the fields explicitly present in the payload. Minimal diff:
  ```php
  $merge = $this->database->merge('copilot_agent_tracker_agents')
    ->key('agent_id', $agent_id)
    ->insertFields([...full set...])
    ->updateFields(array_filter([
      'status' => $payload['status'] ?? NULL,
      'current_action' => $payload['action'] ?? NULL,
      'last_seen' => $now,
      // only include others if non-null
      ...array_filter([
        'role' => $payload['role'] ?? NULL,
        'website' => $payload['website'] ?? NULL,
        'module' => $payload['module'] ?? NULL,
        'metadata' => $payload['metadata'] ?? NULL,
      ], fn($v) => $v !== NULL),
    ]));
  ```
- **Verification:** Insert agent row with `website=forseti.life`; send a second event with only `agent_id`, `status`, `summary`; confirm `website` field is unchanged in `copilot_agent_tracker_agents`.

### MEDIUM-2: No transaction wrapping the agent upsert + event insert
- **Surface:** `recordEvent()` performs two separate DB operations: `merge()` on agents, then `insert()` on events. No transaction.
- **Impact:** If the `insert()` on events fails (DB error, constraint violation, connection drop), the agents table has already been updated but no event row was inserted. The caller receives an exception but the agent's `last_seen` and `status` have been mutated. This creates inconsistent state: the dashboard shows a fresh `last_seen` for an agent whose corresponding event was never recorded. In reverse, a partially failed operation on a high-traffic site could corrupt agent status without a corresponding audit trail entry.
- **Mitigation:**
  ```php
  $event_id = $this->database->startTransaction() + try/catch pattern, or use:
  $txn = $this->database->startTransaction('record_event');
  // ... merge + insert ...
  unset($txn); // commit
  ```
  Alternatively, wrap with `$this->database->transact()` if available in the Drupal version.
- **Verification:** Simulate a DB failure on the insert (e.g., temporarily remove the events table); confirm agent row is not modified.

### MEDIUM-3: `summary` and `details` passed to DB with no length enforcement in storage layer
- **Surface:** `$this->database->insert('copilot_agent_tracker_events')->fields([...'summary' => (string)($payload['summary'] ?? ''), 'details' => $payload['details'] ?? NULL...])`. No `substr()` or length assertion.
- **Impact:** The storage layer is the last checkpoint before the DB. Even if the controller adds length caps (recommended in cycle 5), the storage service itself offers no defense — a caller that bypasses the controller (e.g., future internal code, a drush script) writes unbounded data. Schema defines `summary` as `text` (~65KB) and `details` as LONGTEXT (4GB). Oversized data causes silent truncation (MySQL strict mode off) or DB error (strict mode on).
- **Mitigation:** Add length assertions at the start of `recordEvent()`:
  ```php
  if (strlen($payload['summary'] ?? '') > 1000) {
    throw new \LengthException('summary exceeds 1000 char limit.');
  }
  ```
  Or apply `substr()` with a docblock comment documenting the limit. This makes the storage contract self-documenting and independent of caller behavior.
- **Verification:** Call `recordEvent()` directly (e.g., via drush php:eval) with a `summary` > 1000 chars; confirm exception or truncation per documented behavior.

### LOW-1: `(int) $this->time->getRequestTime()` — silent cast on non-int return
- **Surface:** `$now = (int) $this->time->getRequestTime();` — `getRequestTime()` returns a mixed type (int or float in some Drupal versions depending on implementation).
- **Impact:** If `getRequestTime()` returns a float (microseconds), the `(int)` cast silently truncates. No impact in practice with standard Drupal, but the cast obscures the contract. If `TimeInterface` changes in a future Drupal minor, this could produce unexpected behavior with no warning.
- **Mitigation:** Add a `\assert(is_int($now) && $now > 0)` or a `\InvalidArgumentException` throw if `$now === 0`. Alternatively, document the cast assumption with an inline comment.
- **Verification:** Static analysis (`phpstan level 5+`) will flag this.

### LOW-2: No logging on agent merge or event insert — zero observability for data pipeline
- **Surface:** `recordEvent()` performs two DB writes with no logging (no `\Drupal::logger()`, no debug channel, no event dispatch).
- **Impact:** When the consume script's IndentationError (CRITICAL finding from cycles 8–10) is fixed, the reply pipeline will resume. At that point there will be no server-side audit trail for telemetry events beyond the DB rows themselves. If a bad event corrupts agent status (e.g., MEDIUM-1 null overwrite), there is no log entry to trace the cause. On a production site, at minimum an info-level log on event creation would support incident response.
- **Mitigation:** Add `\Drupal::logger('copilot_agent_tracker')->info('Event recorded: agent_id=@id, status=@status, event_id=@eid', ['@id' => $agent_id, '@status' => $payload['status'] ?? '', '@eid' => $event_id]);` after the insert. Avoid logging `summary`/`details` to prevent PII leakage into logs.
- **Verification:** Post a telemetry event; confirm entry in `admin/reports/dblog` (Drupal watchdog) with correct `agent_id` and `event_id`.

## Already confirmed safe
- All DB operations use Drupal query builder (prepared statements) — no SQL injection.
- `agent_id` key used in `merge()` is derived from `$payload['agent_id']` which passes through parameterized binding — safe.
- No raw SQL strings anywhere in the file.
- `json_encode($payload['metadata'])` result (nullable string) stored in a `text` field — no structural risk from the DB side.

## Follow-up queuing
- `dev-forseti-agent-tracker` inbox at 3/3 cap — all findings deferred. They bundle well with pending ApiController hardening item (ROI 7) and install schema hardening (ROI 5). Recommend bundling MEDIUM-1 + MEDIUM-2 (transaction + upsert fix) into a single high-ROI storage hardening item once cap clears.

## Next actions
- Process remaining inbox item: `20260222-idle-security-explore-forseti.life-9`.
- When `dev-forseti-agent-tracker` inbox drops below cap: queue storage hardening item covering MEDIUM-1 (partial upsert), MEDIUM-2 (transaction), MEDIUM-3 (length enforcement in storage layer), LOW-2 (logging). Bundle at ROI 7 alongside pending ApiController item.

## Blockers
- `dev-forseti-agent-tracker` inbox at 3-item cap — storage hardening follow-up item cannot be queued now.

## ROI estimate
- ROI: 6
- Rationale: MEDIUM-1 (silent null overwrite on agent upsert) is a confirmed data integrity bug that will corrupt the WoK dashboard silently for any agent sending heartbeat-only events. MEDIUM-2 (no transaction) is a correctness risk that becomes more important as event volume grows. These two findings alone justify the ROI and bundle tightly with the already-pending ApiController hardening work item.
```
