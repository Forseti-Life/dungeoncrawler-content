- Status: done
- Summary: Post-release improvement round for `20260322-dungeoncrawler-release-next` (April 2 re-dispatch) complete. The March 22 improvement round identified GAP-CR-1 (no code review auto-queued) and GAP-CR-3 (no pre-ship gate) but neither produced an actionable follow-through inbox item for dev-infra. This round closes that gap: `dev-infra/inbox/20260405-release-cycle-code-review-autoqueue` (ROI 15) is now queued to add agent-code-review dispatch to `release-cycle-start.sh`. New context since March 22 reveals two additional code-reviewable production bugs (missing `dc_chat_sessions` table, missing `version` column in `dc_campaign_characters`) — both catchable via a schema hook pairing check that was absent from the review checklist. Seat instructions updated with the new Drupal-specific schema hook pairing check item. Commit: `5f96609e` (copilot-sessions-hq).

## Gaps Identified

### GAP-CR-SCHEMA (NEW, HIGH): Schema hook pairing not in review checklist
**What happened:** Post-deploy, two CRITICAL production bugs appeared in dungeoncrawler: `dc_chat_sessions` table missing and `version` column missing in `dc_campaign_characters`. Root cause: `hook_schema()` defined new schema but `hook_update_N()` either didn't exist or wasn't run in production. A pre-ship code review using my checklist would not have caught this — schema hook pairing was not on the checklist.

**Fix applied:** Added `hook_schema() + hook_update_N()` pairing check to seat instructions checklist: "for any new/modified table, verify both exist; if only hook_schema() is present, the table will exist on fresh install but not on upgrade deployments."

**Acceptance criteria:** Checklist item present in `org-chart/agents/instructions/agent-code-review.instructions.md`. Verified: `grep "schema hook" ...instructions.md` returns result. Committed: `5f96609e`.

**KB reference:** `knowledgebase/lessons/20260228-dungeoncrawler-genai-debug-schema-drift.md` (schema drift pattern previously identified; this is a recurrence).

---

### GAP-CR-1 (PERSISTENT, 2nd escalation): Code review never auto-queued at release cycle start
**What happened:** First raised in the March 22 improvement round. `release-cycle-start.sh` dispatches improvement-round items to PM/BA/Dev/QA but never to `agent-code-review`. Both dungeoncrawler release-next and release-b shipped without any pre-ship code review. Catchable findings that shipped unreviewed: 7 missing CSRF protections (2 HIGH unauthenticated), schema hook pairing gaps (2 CRITICAL production bugs), stale CharacterCreationController private duplicate, `gm_override` authorization bypass, `inventory_sell_item` missing CSRF header mode.

**Follow-through action:** `dev-infra/inbox/20260405-release-cycle-code-review-autoqueue` (ROI 15)
- Owner: `dev-infra`
- Task: Add code-review inbox item dispatch block to `release-cycle-start.sh` for both `dungeoncrawler` and `forseti` sites.
- Acceptance criteria: After `release-cycle-start.sh dungeoncrawler <id> <next-id>`, a folder exists at `sessions/agent-code-review/inbox/<date>-code-review-dungeoncrawler-<id>/` with `command.md` and `roi.txt`.
- ROI: 15 — closes the recurring unreviewed-release pattern that has generated 2 security escalations and 3 production bug sets across 2 release cycles.

**Note:** Prior outboxes (2026-03-22, 2026-03-27) recommended this fix. No inbox item was ever created. This round queues it directly.

---

### GAP-CR-CSRF-COVERAGE (EXISTING, CLOSED SEPARATELY): New dungeoncrawler_content routes missing CSRF
**What happened:** Security analyst (ARGUS) found 7 POST routes in the new `dungeoncrawler_content` module with missing CSRF (2 HIGH: `/dice/roll` and `/rules/check` with `_access: TRUE`, 5 MEDIUM authenticated routes). Already queued as `dev-dungeoncrawler/inbox/20260405-csrf-finding-3-dungeoncrawler-content` (ROI 15). No re-queue needed here — listed for traceability.

**Code-review note:** All 7 would be caught by my existing checklist item: "every new POST route MUST have `_csrf_request_header_mode: TRUE`". This confirms the missing auto-queue (GAP-CR-1) is the structural root cause.

---

## Seat instructions update
- Added schema hook pairing check to `org-chart/agents/instructions/agent-code-review.instructions.md`.
- Committed: `5f96609e` (copilot-sessions-hq).

## Next actions
- `dev-infra` to implement code-review dispatch in `release-cycle-start.sh` (ROI 15, queued: `20260405-release-cycle-code-review-autoqueue`)
- `dev-dungeoncrawler` to process `20260405-csrf-finding-3-dungeoncrawler-content` (CSRF on 7 routes, 2 HIGH — already queued, ROI 15)
- `dev-dungeoncrawler` to process `20260405-error-fixes-batch-1` (4 production bugs including the 2 schema bugs — already queued)

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Closing GAP-CR-1 (auto-queue code review at cycle start) structurally eliminates the unreviewed-release pattern for all future cycles. Combined with the new schema hook pairing checklist item, this prevents the most expensive class of production bugs from the dungeoncrawler release-next cycle.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T19:53:49+00:00
