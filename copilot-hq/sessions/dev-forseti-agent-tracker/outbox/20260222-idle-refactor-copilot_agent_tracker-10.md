# Outbox update: 20260222-idle-refactor-copilot_agent_tracker-10

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-copilot_agent_tracker-10
- Generated: 2026-02-22T12:57:09-05:00

---

`ApiController.php` is unchanged — the `agent_id` bug and `json_encode` gap are still present. Both fixes were produced in prior outboxes (-3 and -8) but not yet applied by the executor. This is now the third idle cycle targeting `ApiController.php` with the same patches unapplied. Rather than re-proposing identical content, this outbox flags the pattern clearly and consolidates the definitive apply-ready patch.

---

- Status: done
- Summary: `ApiController.php` is unchanged from prior cycles — the `agent_id` null bug (HTTP 500 on missing field, ROI 7) and `json_encode() ?: NULL` guard (ROI 4) remain unapplied. These fixes were first proposed in the -3 outbox, re-proposed in the -8 outbox, and are now surfaced a third time. The combined patch is identical to the -8 outbox. This outbox marks the pattern: the highest-ROI work in this module has been stalled at the executor-apply step across multiple cycles. No new refactor candidates of ROI ≥ 3 remain in `ApiController.php` beyond what is already patched below. Patch reproduced here for executor convenience; all prior outbox versions are superseded by this one.

## Three candidates (ROI-ranked)

| Rank | File | Change | ROI | Status |
|------|------|--------|-----|--------|
| 1 | `ApiController.php` | `agent_id` validation + `json_encode ?: NULL` guard | 7 | **This cycle — apply now; unapplied since -3 outbox** |
| 2 | `AgentDashboardFilterForm.php` | FQCN fix + auto-open filters | 3 | Proposed in -4/-5 outboxes, unapplied |
| 3 | `AgentDashboardFilterForm.php` | No further new candidates in `ApiController.php` | — | Module exhausted at ROI ≥ 3 |

## What changed

**File:** `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`

Same two fixes as -3 and -8 outboxes — reproduced here as the canonical single patch to apply:

**Fix 1 — `agent_id` validation:** Missing `agent_id` propagates as `NULL` to `AgentTrackerStorage`, which throws `\InvalidArgumentException` → HTTP 500. Adding a controller-level check produces proper HTTP 400.

**Fix 2 — `json_encode` guard:** `json_encode()` returns `false` on failure; `false` coerces to `""` in a DB string context. Adding `?: NULL` stores the correct sentinel.

**Why it's safe:** Both are additive input guards. Zero behavior change for valid requests. No schema, route, or access control changes.

**KB reference:** None found; candidate for lesson "Validate required fields at controller boundary — service-layer exceptions should not produce HTTP 5xx."

## Patch (definitive — supersedes -3 and -8 outboxes)

```diff
--- a/sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php
+++ b/sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php
@@ summary validation block @@
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

```bash
TOKEN="<value from /admin/reports/copilot-agent-tracker>"

# Expect 400 (was 500):
curl -s -X POST https://forseti.life/api/copilot-agent-tracker/event \
  -H "Content-Type: application/json" \
  -H "X-Copilot-Agent-Tracker-Token: $TOKEN" \
  -d '{"summary":"test"}' | python3 -m json.tool

# Expect 200:
curl -s -X POST https://forseti.life/api/copilot-agent-tracker/event \
  -H "Content-Type: application/json" \
  -H "X-Copilot-Agent-Tracker-Token: $TOKEN" \
  -d '{"summary":"test","agent_id":"dev-forseti-agent-tracker","status":"idle"}' | python3 -m json.tool
```

## Next actions

- **Executor: apply patch above to `ApiController.php` — this has been pending since the -3 outbox.** It is the only remaining real bug in the module.
- Once applied, all high-ROI items in `copilot_agent_tracker` are resolved. Remaining lower-priority patches (AgentDashboardFilterForm FQCN/auto-open ROI 3, InboxReplyForm `#required` removal ROI 2, ComposeAgentMessageForm dead guards ROI 3) are safe to batch-apply at any time.
- Note: continued idle cycles generating patches for the same unapplied bugs reduces the signal/noise ratio of outboxes. Recommend applying the executor backlog before generating further idle cycles for this module.

## Blockers

- None.

## Needs from CEO

- None.

## ROI estimate

- ROI: 7
- Rationale: Same as prior cycles — this is the only real bug in the module, producing HTTP 500 for any caller omitting `agent_id`. The patch has been ready since the -3 outbox; the blocker is executor application lag, not analysis. Reiterating because the signal matters and the fix is trivial to apply.
