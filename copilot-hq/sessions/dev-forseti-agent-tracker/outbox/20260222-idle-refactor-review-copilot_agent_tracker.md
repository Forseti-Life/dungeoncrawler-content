# Outbox update: 20260222-idle-refactor-review-copilot_agent_tracker

- Agent: dev-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-forseti-agent-tracker/inbox/20260222-idle-refactor-review-copilot_agent_tracker
- Generated: 2026-02-22T14:02:05-05:00

---

README is still the stale version (4 missing routes, 2 missing tables — patch proposed in -7 outbox, unapplied). `ApiController.php` bug still unapplied. Inbox depth is 3 — at cap, no new follow-up items should be queued. This review cycle is most useful confirming findings and pointing to existing patches.

---

- Status: done
- Summary: Reviewed `README.md` (suggested file). It remains stale — the patch proposed in the -7 outbox has not been applied. Four routes (`/admin/reports/waitingonkeith` and three sub-routes) and two tables (`copilot_agent_tracker_replies`, `copilot_agent_tracker_inbox_resolutions`) are still missing from the docs. No new findings beyond what was already captured in the -7 outbox. Inbox is at cap (3 items); no new follow-up items are queued per org-wide idle directive. The highest-leverage action remains applying the `ApiController.php` ROI 7 patch from the -10 outbox — once that and the other backlog patches are applied, the module will be clean.

## File reviewed

`~/forseti.life/sites/forseti/web/modules/custom/copilot_agent_tracker/README.md`

## Findings

| # | Finding | Severity | Prior outbox |
|---|---------|----------|--------------|
| 1 | Routes section missing `/admin/reports/waitingonkeith`, `/{item_id}`, `/{item_id}/approve`, `/sent/{reply_id}/dismiss` | Medium — stale docs cause confusion | -7 |
| 2 | Data model section missing `copilot_agent_tracker_replies` and `copilot_agent_tracker_inbox_resolutions` | Medium | -7 |
| 3 | No description of Waiting-on-Keith / CEO inbox feature (the module's primary new feature) | Low | -7 |
| 4 | Telemetry auth section doesn't mention that `agent_id` is required (callers omitting it get HTTP 500, not a documented 400) | Medium | -10 (code fix); README could note it |

## Proposed follow-up items

Inbox is at cap (3 items) — no new items queued. Existing coverage:

| Work | File | ROI | Covered by |
|------|------|-----|------------|
| Apply README patch | `README.md` | 2 | -7 outbox (patch ready) |
| Apply `ApiController.php` fix | `ApiController.php` | 7 | -10 outbox (patch ready) |
| Apply `AgentDashboardFilterForm.php` fix | `AgentDashboardFilterForm.php` | 3 | -4/-5 outboxes (patches ready) |

One new finding not previously captured: the README `Telemetry auth` section should note `agent_id` is a required field (once the ApiController validation fix is applied, it will return a proper 400). This is a 1-line README addition that can be combined with the -7 patch when the executor applies it.

## Additional README line (combine with -7 patch)

```diff
 ## Telemetry auth
 Telemetry POSTs must include the header:
 - `X-Copilot-Agent-Tracker-Token: <value>`
+
+Required payload fields: `agent_id` (string, max 128 chars), `summary` (string).
```

## Next actions

- Executor applies README patch from -7 outbox + the 1-line addition above (combine into single commit).
- Executor applies `ApiController.php` patch from -10 outbox (ROI 7) — highest priority across all open work.
- No new inbox items queued (at cap).

## Blockers

- None.

## Needs from CEO

- None.

## ROI estimate

- ROI: 2
- Rationale: Review confirms the -7 patch is still needed and adds one minor new finding (document `agent_id` as required in README). No code changes; value is audit confirmation and the small README addition.
