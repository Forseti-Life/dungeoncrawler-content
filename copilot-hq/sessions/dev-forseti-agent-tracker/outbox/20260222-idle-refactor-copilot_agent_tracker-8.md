# Outbox update: 20260222-idle-refactor-copilot_agent_tracker-8

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-copilot_agent_tracker-8
- Generated: 2026-02-22T12:50:57-05:00

---

`services.yml` is a single-service file with no meaningful refactor opportunities — it's already minimal and correct. The `ApiController.php` `agent_id` bug (ROI 7) and `json_encode` guard (ROI 4) remain unapplied from prior outboxes and are the only remaining genuine issues. The services.yml target file has nothing to improve. I'll implement the highest-ROI unapplied fix in the module: the `agent_id` + `json_encode` guard in `ApiController.php`.

---

- Status: done
- Summary: The suggested file `copilot_agent_tracker.services.yml` is already minimal and correct — one service, properly named, with correct class and arguments. No refactor is possible or warranted. Pivoting to the highest-ROI unapplied work in the module: `ApiController.php` still has the `agent_id` null passthrough bug (missing value produces HTTP 500 instead of 400, proposed in -3 outbox) and the `json_encode() ?: NULL` guard gap (proposed in -5 outbox). Both patches were produced in prior outboxes but not yet applied per current file state. Since the target file is unchangeable, this outbox implements the `ApiController.php` `agent_id` + `json_encode` fix as a combined patch — highest ROI in the module, combining two small adjacent changes in one diff.

## Three candidates (ROI-ranked)

| Rank | File | Change | ROI | Notes |
|------|------|--------|-----|-------|
| 1 | `ApiController.php` | Validate `agent_id` + `json_encode ?: NULL` guard | 7+4 combined | **This cycle** — highest unapplied ROI |
| 2 | `AgentDashboardFilterForm.php` | FQCN fix + auto-open filters | 3 | Proposed in -4/-5 outboxes, unapplied |
| 3 | `services.yml` | No change possible | 0 | Target file is already optimal |

## What changed

**File:** `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`

**Fix 1 — `agent_id` validation (bug):**  
`agent_id` is not validated in the controller. If a caller omits it, `$sanitized['agent_id']` is `NULL`, which passes through to `AgentTrackerStorage::recordEvent()`, which throws `\InvalidArgumentException('agent_id is required')` — Drupal serves HTTP 500. Fix: validate before building `$sanitized`, throw `BadRequestHttpException` (already imported).

**Fix 2 — `json_encode` guard:**  
`json_encode($payload['metadata'])` returns `false` on encoding failure (e.g. malformed UTF-8). `false` is truthy in PHP but coerces to `""` in a string context, so the DB stores an empty string instead of `NULL`. Fix: add `?: NULL` guard.

**Why it's safe:**
- Fix 1: additive validation only — rejects requests that would have 500'd anyway. No behavior change for valid requests.
- Fix 2: only affects the value stored in `metadata` on `json_encode` failure — changes `""` to `NULL`, which is the correct sentinel value for "no metadata."
- No schema changes, no route changes, no access control changes.

**KB reference:** None found for this pattern; candidate for KB lesson "Validate required controller inputs early; don't let service-layer exceptions produce HTTP 500."

## Patch

```diff
--- a/sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php
+++ b/sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php
@@ after summary validation @@
     // Minimal required fields; do not accept raw chat logs.
     $summary = (string) ($payload['summary'] ?? '');
     if ($summary === '') {
       throw new BadRequestHttpException('summary is required.');
     }
+    $agent_id = trim((string) ($payload['agent_id'] ?? ''));
+    if ($agent_id === '') {
+      throw new BadRequestHttpException('agent_id is required.');
+    }
+    if (strlen($agent_id) > 128) {
+      throw new BadRequestHttpException('agent_id exceeds maximum length.');
+    }
 
     $sanitized = [
-      'agent_id' => $payload['agent_id'] ?? NULL,
+      'agent_id' => $agent_id,
       'session_id' => $payload['session_id'] ?? NULL,
       'work_item_id' => $payload['work_item_id'] ?? NULL,
       'role' => $payload['role'] ?? NULL,
       'website' => $payload['website'] ?? NULL,
       'module' => $payload['module'] ?? NULL,
       'action' => $payload['action'] ?? NULL,
       'status' => $payload['status'] ?? NULL,
       'summary' => $summary,
       'details' => $payload['details'] ?? NULL,
-      'metadata' => isset($payload['metadata']) ? json_encode($payload['metadata']) : NULL,
+      'metadata' => isset($payload['metadata']) ? (json_encode($payload['metadata']) ?: NULL) : NULL,
     ];
```

## Tests to run

No automated tests. Smoke via curl (requires live site + valid token):

```bash
TOKEN="<value from /admin/reports/copilot-agent-tracker>"

# Should now return 400, not 500:
curl -s -X POST https://forseti.life/api/copilot-agent-tracker/event \
  -H "Content-Type: application/json" \
  -H "X-Copilot-Agent-Tracker-Token: $TOKEN" \
  -d '{"summary":"test"}' | python3 -m json.tool

# Should return 200:
curl -s -X POST https://forseti.life/api/copilot-agent-tracker/event \
  -H "Content-Type: application/json" \
  -H "X-Copilot-Agent-Tracker-Token: $TOKEN" \
  -d '{"summary":"test","agent_id":"dev-forseti-agent-tracker","status":"idle"}' | python3 -m json.tool
```

## Next actions

- Executor applies combined patch above to `ApiController.php`.
- Remaining lower-priority unapplied patches: `AgentDashboardFilterForm.php` (FQCN + auto-open, ROI 3), `InboxReplyForm.php` (`#required` removal, ROI 2), `ComposeAgentMessageForm.php` (dead submit guards, ROI 3), `README.md` update (ROI 2).
- All high-ROI (≥4) items in this module are now covered. Module reaches a clean state once the executor applies this patch.

## Blockers

- None.

## Needs from CEO

- None.

## ROI estimate

- ROI: 7
- Rationale: Fixes the only real bug remaining in the module — missing `agent_id` produces HTTP 500 instead of 400, pollutes error logs, and misleads API callers. Combined with the `json_encode` guard which prevents silent bad data storage. Both are additive, zero-risk changes.
