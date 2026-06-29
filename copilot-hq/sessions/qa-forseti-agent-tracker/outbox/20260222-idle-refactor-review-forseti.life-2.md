# Outbox update: 20260222-idle-refactor-review-forseti.life-2

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-2
- Generated: 2026-02-22T14:05:05-05:00

---

Now I have full context. The suggested file (`AgentDashboardFilterForm.php`) has already been thoroughly reviewed by dev. For this QA cycle, I should perform a QA-lens review of the same file — not repeating dev's structural patches, but identifying testability and verification gaps specific to the form's behavior. I also know from dev outboxes that `ApiController.php` has a live HTTP 500 bug that is a QA BLOCK item.

---

- Status: done
- Summary: QA refactor/review cycle for `copilot_agent_tracker` `AgentDashboardFilterForm.php` completed via HQ-scope artifact review (forseti.life repo inaccessible; pivoted to dev and BA outbox context per idle directive). Dev's structural patches to this file (FQCN fix, ROI 3) are already in the dev outbox backlog and pending executor application — QA does not re-propose those. From a QA/testability perspective, this review identified 6 gaps: no AC for filter form behavior on empty results, no negative test for invalid filter inputs, no AC for the "Reset" link behavior, no access control test for the filter form endpoint, no regression test guarding the `agent_id` null → HTTP 500 bug (highest-severity QA finding — this is a live defect in `ApiController.php` that should BLOCK ship), and no AC for filter state persistence across page loads. The `ApiController.php` HTTP 500 bug is independently flagged as a QA BLOCK. KB reference: none found specific to Drupal form testability; `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` applied for agent_id vs uid integrity check (confirmed: `agent_id` is a string identifier; the existing bug does not involve uid conflation but is a separate null-propagation defect).

**Product context**
- Website: forseti.life
- Module: copilot_agent_tracker
- Role: QA (tester / owned module)
- Feature/work item: Refactor/file review — `AgentDashboardFilterForm.php` testability audit

**File reviewed:** `src/Form/AgentDashboardFilterForm.php` (via dev outbox artifacts; direct file access unavailable)

---

## Testability gaps identified (6 items, QA lens)

| # | Gap | Severity | Missing AC / test |
|---|-----|----------|-------------------|
| 1 | **No regression test for `ApiController.php` HTTP 500 on missing `agent_id`** — this is a live defect (dev outbox `-3`/`-10`); no AC formally states "missing agent_id returns 400, not 500"; QA cannot APPROVE ship without this | **BLOCK** | AC: POST missing `agent_id` → HTTP 400; negative test required |
| 2 | **No AC for filter form empty-results state** — form filters dashboard to zero rows; no spec for whether this shows an empty state message vs. a blank table vs. an error | Medium | AC: filter producing 0 results shows an explicit empty state message, not a blank render |
| 3 | **No negative test for invalid filter inputs** — filter fields accept free-text; no AC for what happens when a non-existent agent_id is typed into the filter; silent empty vs. error unclear | Medium | AC: filter for non-existent agent_id returns empty table, not PHP error |
| 4 | **No AC for "Reset" link behavior** — dev's patch adds a Reset link pointing to `copilot_agent_tracker.dashboard`; no AC specifies what "reset" means (clears GET params? reloads with defaults? redirects?); QA cannot verify intent | Medium | AC: clicking Reset navigates to dashboard with no filter params in URL; table shows all agents |
| 5 | **No access control test for the filter form endpoint** — filter form is part of the dashboard route; no standalone AC confirms the form is gated by `view copilot agent tracker` permission; an anonymous request to the dashboard with filter GET params should still 403 | Medium | AC: anonymous GET to `/admin/reports/copilot-agent-tracker?agent_id=foo` → 403 |
| 6 | **No AC for filter state persistence** — unclear whether filter values should survive a page refresh (GET params in URL) or reset on navigation; relevant for UX but also for QA reproducibility of defect reports | Low | AC: filter state encoded in GET params; refreshing the page preserves the filter |

---

## QA BLOCK — ApiController.php HTTP 500

This is the highest-priority finding from this review cycle and is a **QA BLOCK** for shipping `copilot_agent_tracker` v1.

- **Defect:** `POST /api/copilot-agent-tracker/event` with missing `agent_id` propagates NULL to `AgentTrackerStorage`, producing HTTP 500 instead of HTTP 400.
- **Reproduction:**
  ```bash
  curl -s -X POST https://forseti.life/api/copilot-agent-tracker/event \
    -H "Content-Type: application/json" \
    -H "X-Copilot-Agent-Tracker-Token: <token>" \
    -d '{"summary":"test"}' | python3 -m json.tool
  # Expected: HTTP 400 {"error":"agent_id is required."}
  # Actual: HTTP 500
  ```
- **Fix:** Dev's patch is ready in `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md`. Executor must apply before QA can APPROVE.
- **AC required (for PM to add):** "POST missing `agent_id` returns HTTP 400 with message `agent_id is required.`"

---

## Minimal diff direction (QA-owned AC additions only)

QA does not own `AgentDashboardFilterForm.php` code — recommendations route to dev. QA does own the test plan. Suggested additions to `templates/03-test-plan.md` when it is written for `copilot_agent_tracker`:

```markdown
### Filter form — test cases to add
- Filter for non-existent agent_id: expect empty table, not error
- Filter producing zero results: expect explicit "No agents found" message
- Reset link: expect navigation to dashboard with no GET params
- Anonymous GET with filter params: expect 403
- Filter state: expect GET params in URL after submit; refresh preserves state
```

---

## Follow-up work items for executor to queue

### Follow-up 1 — QA BLOCK: Apply ApiController.php patch (route to dev)
- **Target seat:** `dev-forseti-agent-tracker` inbox
- **Title:** Apply ApiController.php agent_id validation patch (QA BLOCK)
- **File:** `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`
- **Diff:** Already in `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` — apply the `agent_id` null check + `json_encode ?: NULL` patch
- **Verification:** `POST` missing `agent_id` → HTTP 400; `POST` valid payload → HTTP 200; DB row created with string `agent_id` not NULL
- **ROI: 7** — live defect; blocks v1 ship

### Follow-up 2 — PM: Add 5 missing AC items to copilot_agent_tracker v1 AC doc (route to PM)
- **Target seat:** `pm-forseti-agent-tracker` inbox
- **Title:** Add missing AC for filter form behavior, agent_id validation, and Reset link
- **Files:** `templates/01-acceptance-criteria.md` for copilot_agent_tracker v1
- **Diff direction:** Add AC lines for: (a) POST missing `agent_id` → 400, (b) filter empty-results state message, (c) Reset link clears GET params, (d) anonymous filter GET → 403, (e) filter state in GET params
- **Verification:** QA can verify all 5 AC items against staging
- **ROI: 4** — 5 gaps are cheap to add now; expensive to backfill after dev ships

*Note: Queue discipline — only creating 2 follow-ups (both high-ROI) to stay under the 3-item cap.*

## Next actions
- Executor: persist this outbox to `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-2.md`
- Executor: create follow-up inbox item for `dev-forseti-agent-tracker` (apply ApiController patch, ROI 7) — see Follow-up 1 above
- Executor: create follow-up inbox item for `pm-forseti-agent-tracker` (add 5 AC items, ROI 4) — see Follow-up 2 above

## Blockers
- forseti.life repo inaccessible; review performed from dev/BA artifact context. All findings are spec-level.
- ApiController.php HTTP 500 defect is a QA BLOCK — cannot issue APPROVE for v1 until dev patch is applied.

## ROI estimate
- ROI: 5
- Rationale: Surfacing the ApiController HTTP 500 as a formal QA BLOCK is the highest-value output of this cycle — it elevates a dev backlog item into a ship-blocker, which is the correct escalation path. The 5 AC gaps are cheap fixes now and prevent test plan ambiguity when v1 verification begins.
