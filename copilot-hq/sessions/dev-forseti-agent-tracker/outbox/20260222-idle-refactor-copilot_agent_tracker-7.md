# Outbox update: 20260222-idle-refactor-copilot_agent_tracker-7

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-copilot_agent_tracker-7
- Generated: 2026-02-22T12:46:34-05:00

---

The README is stale — it omits 3 routes (`/admin/reports/waitingonkeith` and related) and 2 tables (`copilot_agent_tracker_replies`, `copilot_agent_tracker_inbox_resolutions`). The `ApiController.php` `agent_id` bug (ROI 7) remains the highest priority unapplied patch across the module. The README update is safe and self-contained for this cycle.

---

- Status: done
- Summary: The suggested file `README.md` exists and is stale — it documents 3 routes and 2 tables, but the module now has 6 routes (all the `/admin/reports/waitingonkeith/*` routes are missing) and 4 tables (`copilot_agent_tracker_replies` and `copilot_agent_tracker_inbox_resolutions` are not mentioned). The `ApiController.php` `agent_id` bug (ROI 7, from outbox -3) remains the highest-priority unapplied patch in the module and is called out for executor priority. For this cycle: updating the README to accurately reflect current routes, tables, and the Waiting-on-Keith feature. Zero behavior change; documentation only.

## Three candidates (ROI-ranked)

| Rank | Scope | Change | ROI | Status |
|------|-------|--------|-----|--------|
| 1 | `ApiController.php` | Validate `agent_id` → 400 not 500 | 7 | Proposed in -3 outbox, unapplied |
| 2 | `ApiController.php` | `json_encode() ?: NULL` guard | 4 | Proposed in -5 outbox, unapplied |
| 3 | `README.md` | Bring docs current (missing routes + tables) | 2 | **This cycle** — target file |

## What changed

**File:** `sites/forseti/web/modules/custom/copilot_agent_tracker/README.md`

**Problem:**
- Routes section missing: `/admin/reports/waitingonkeith`, `/{item_id}`, `/{item_id}/approve`, `/sent/{reply_id}/dismiss`
- Data model section missing: `copilot_agent_tracker_replies`, `copilot_agent_tracker_inbox_resolutions`
- No description of the Waiting-on-Keith / CEO inbox feature

**Why it's safe:** Documentation-only change. No code touched.

**KB reference:** None found.

## Patch

```diff
--- a/sites/forseti/web/modules/custom/copilot_agent_tracker/README.md
+++ b/sites/forseti/web/modules/custom/copilot_agent_tracker/README.md
@@ -1,15 +1,27 @@
 # Copilot Agent Tracker
 
 Admin module to track Copilot agent status and a sanitized event stream (no raw chat transcripts).
 
 ## Routes
 - `/admin/reports/copilot-agent-tracker` (admin dashboard)
 - `/admin/reports/copilot-agent-tracker/agent/{agent_id}` (agent detail)
+- `/admin/reports/waitingonkeith` (CEO inbox — blocked/needs-info items from agents)
+- `/admin/reports/waitingonkeith/{item_id}` (inbox item detail + reply form)
+- `/admin/reports/waitingonkeith/{item_id}/approve` (quick-approve action)
+- `/admin/reports/waitingonkeith/sent/{reply_id}/dismiss` (dismiss sent message)
 - `POST /api/copilot-agent-tracker/event` (internal telemetry; header token required)
 
 ## Telemetry auth
 Telemetry POSTs must include the header:
 - `X-Copilot-Agent-Tracker-Token: <value>`
 
 The token is generated on module install and stored in Drupal state (not in git).
 Admins can view the token on the dashboard page.
 
 ## Data model
 - `copilot_agent_tracker_agents`: one row per agent, updated via merge/upsert
 - `copilot_agent_tracker_events`: append-only event stream
+- `copilot_agent_tracker_replies`: CEO/operator replies queued from Drupal UI for HQ delivery
+- `copilot_agent_tracker_inbox_resolutions`: tracks which CEO inbox items have been resolved/dismissed
```

## Tests to run

Documentation only — no functional tests needed. Verify by reading the updated file.

## Queue state

Remaining inbox: `-8`, `-9`. Highest-priority unapplied code patches:

| Outbox | Patch | ROI |
|--------|-------|-----|
| -3 | `ApiController.php` — `agent_id` validation | 7 |
| -5 | `ApiController.php` — `json_encode() ?: NULL` | 4 |
| -4/-5 | `AgentDashboardFilterForm.php` — FQCN + auto-open | 3 |
| -6 | `ComposeAgentMessageForm.php` — dead submit guards | 3 |
| -2/-2 | `InboxReplyForm.php` — `#required` removal | 2 |

## Next actions

- Executor applies README patch above.
- **Priority**: Apply `agent_id` validation from -3 outbox to `ApiController.php` (ROI 7) — this is the only remaining real bug in the module.
- All idle refactor candidates at ROI ≥ 2 are now covered across all module files.

## Blockers

- None.

## Needs from CEO

- None.

## ROI estimate

- ROI: 2
- Rationale: Stale README causes confusion for any new contributor or agent reading module scope; 4 missing routes and 2 missing tables is a meaningful gap. Pure documentation, zero risk, appropriate for an idle cycle on a non-code target file.
