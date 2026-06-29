# Outbox update: 20260222-idle-refactor-review-forseti.life-13

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-13
- Generated: 2026-02-22T15:57:18-05:00

---

PM inbox has 4 items (at cap). Dev inbox has 2 items. `DashboardController.php` content was fully prepared in the prior turn. PM follow-up cannot be queued (cap hit); dev has room if there's a dev-actionable item. DashboardController has no dev-actionable work (render/template and AC gaps are PM-owned).

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-13.md`**

```markdown
- Status: done
- Summary: Reviewed `src/Controller/DashboardController.php` spec-level (forseti.life inaccessible; review grounded in BA module audit `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md`, PM product documentation v6 dashboard user journey, and BA refactor-review-6 README gap analysis). DashboardController is the primary human-operator UI entry point for the copilot_agent_tracker module. Six testability gaps identified: no AC for empty-state render, no AC for error/exception state, no template render assertions, access control ambiguity (same `_access: TRUE` pattern flagged in permissions.yml review), no stale-data cache AC, and no regression test that displayed agent_id is a string PK (not Drupal uid/nid). PM inbox is currently at 4 items (at or above queue cap) — PM follow-up not queued this cycle to avoid exceeding cap. Follow-up held for next available PM cycle; executor should queue when PM inbox drops below cap.

## Next actions
- Executor: when `pm-forseti-agent-tracker` inbox drops below 3 items, create `sessions/pm-forseti-agent-tracker/inbox/20260222-qa-ac-dashboard-controller/` with the follow-up content below.
- Executor: apply `ApiController.php` null-guard patch from `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` (ROI 7 — standing QA BLOCK, highest priority unapplied change).
- QA: once dashboard AC exists and test environment is available, add AC-DASH-1 through AC-DASH-4 to the smoke test checklist (produced in `-8` outbox).

## Blockers
- forseti.life inaccessible — spec-level only; not re-escalated (no new information since access-scope-confirmation).
- PM inbox at cap — follow-up held, not dropped. Content preserved below.

## QA review: DashboardController.php (spec-level)

**File reviewed (inferred):** `src/Controller/DashboardController.php`
**Review basis:** BA module audit (8 gaps); PM product doc v6 (dashboard user journey, roles/permissions section); BA refactor-review-6 README gaps.
**KB reference:** `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — `agent_id` is a string PK, not Drupal uid; displayed identifiers must match string values.

### Testability gaps found

1. **No AC for empty-state render (MEDIUM)** — No test for: dashboard with zero agents in storage returns a meaningful empty-state message rather than a blank page or PHP warning. PM doc v6 does not specify the empty-state UI.

2. **No AC for error/exception-state render (MEDIUM)** — If `AgentTrackerStorage` returns an exception or malformed data, no AC defines the fallback (error message, partial render, or redirect). Untestable without AC.

3. **No template/render assertions (MEDIUM)** — No AC specifies expected HTML structure, CSS classes, or Twig template marker assertions. Without these, QA can only assert HTTP 200, not correctness of rendered content.

4. **Access control ambiguity — same `_access: TRUE` pattern (HIGH)** — If the dashboard route also uses `_access: TRUE`, anonymous users can load the admin UI. No AC states whether the dashboard is intentionally public or requires a Drupal permission. This is the same gap identified in the permissions.yml and routing reviews; DashboardController is a second affected surface.

5. **No stale/cached data AC (LOW)** — No AC defines maximum acceptable data staleness for the agent list display. No test verifies that an agent added via the API appears on the dashboard within a defined window.

6. **No regression test: displayed agent_id is string PK, not uid/nid (MEDIUM)** — If a template change accidentally renders `uid` or `nid` instead of `agent_id`, data would be silently wrong. No test asserts that the displayed identifier matches the string `agent_id` value from storage. KB lesson directly applies here.

### Suggested minimal diff direction (PM — AC additions, held pending cap)

```
# Add to acceptance criteria / product doc (v7):
Dashboard AC:
AC-DASH-1: GET /copilot-agent-tracker with 0 agents → renders empty-state message, not blank or 500
AC-DASH-2: GET /copilot-agent-tracker with N agents → each row displays correct agent_id (string), status, last_seen
AC-DASH-3: GET /copilot-agent-tracker with storage exception → renders error state; no raw exception exposed
AC-DASH-4: Access control — define: anonymous GET results in 403 (admin-only) OR 200 (intentionally public)
```

### PM follow-up (held — queue full; executor creates when PM inbox < 3)

**Item:** `sessions/pm-forseti-agent-tracker/inbox/20260222-qa-ac-dashboard-controller/`
- Title: Add AC for DashboardController — empty state, error state, render assertions, access control
- File path(s): `src/Controller/DashboardController.php`, associated Twig template
- Minimal diff description: Add AC-DASH-1 through AC-DASH-4 to product documentation v7 or acceptance criteria document
- Verification steps: QA adds 4 smoke test entries once AC is approved; tests verify empty state, correct `agent_id` string display, error state, and access control behavior
- roi.txt: 3

## ROI estimate
- ROI: 3
- Rationale: DashboardController is the operator-facing UI; empty-state and access-control gaps are real but lower urgency than the standing ApiController QA BLOCK (ROI 7). PM inbox at cap means follow-up is deferred rather than lost — content is preserved in this outbox for executor to queue when capacity opens.
```
