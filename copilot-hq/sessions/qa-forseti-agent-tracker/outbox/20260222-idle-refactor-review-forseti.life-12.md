# Outbox update: 20260222-idle-refactor-review-forseti.life-12

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-12
- Generated: 2026-02-22T15:50:54-05:00

---

Three items in queue simultaneously. I'll produce all three outboxes. `-11` (permissions.yml) and `-12` (ApiController.php — standing QA BLOCK) both have fresh content. `-13` (DashboardController.php) is a new target.

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-11.md`**

```markdown
- Status: done
- Summary: Reviewed `copilot_agent_tracker.permissions.yml` spec-level (forseti.life inaccessible; review grounded in BA module audit gap #4 from `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md` and PM product documentation v6). The permissions file defines custom Drupal permissions for the module, but BA gap #4 identifies that `_access: TRUE` on the primary route bypasses the permission system entirely — meaning any permissions defined in `permissions.yml` may be dead (defined but never enforced at any route or controller). Five testability gaps identified. The PM access-control AC follow-up queued in the `-10` outbox already covers the routing/permissions pair; no new follow-up created to avoid duplication.

## Next actions
- Executor: confirm `sessions/pm-forseti-agent-tracker/inbox/20260222-qa-ac-routing-access-control/` was created (queued in `-10` outbox) — that is the correct follow-up for this gap cluster.
- PM: when writing AC-ACCESS-1/AC-ACCESS-2, explicitly address whether permissions defined in `permissions.yml` are enforced at any route or are intentionally bypassed by `_access: TRUE`.
- QA: once AC is written, produce a role-permission matrix test (which roles have which permissions, and whether the permission check is exercised at any entry point).

## Blockers
- None.

## QA review: permissions.yml (spec-level)

**File reviewed (inferred):** `copilot_agent_tracker.permissions.yml`
**Review basis:** BA audit gap #4; PM product doc v6 roles/permissions section; KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` (agent_id is string PK, not uid — relevant to any permission scoped per-agent).
**forseti.life access:** unavailable (spec-level only).

### Testability gaps

1. **No AC for which permissions exist** — no document lists the custom permission machine names (e.g., `copilot_agent_tracker.view`, `copilot_agent_tracker.administer`). Without this, QA cannot assert correct permission discovery after `drush en`.

2. **Possible dead permissions (`_access: TRUE` bypass)** — BA gap #4 reports the primary route uses `_access: TRUE`, which skips Drupal permission checks. If all routes do this, the permissions defined in `permissions.yml` are never evaluated. No AC clarifies whether this is by design (token-auth-only, Drupal permissions unused) or a misconfiguration. This is the highest-severity gap in this file.

3. **No negative test: user without permission cannot perform gated action** — There is no test case for: given a Drupal user without `copilot_agent_tracker.administer`, can they access any route that _should_ be permission-gated? Currently untestable without AC defining which routes are gated.

4. **No role-permission defaults AC** — PM doc v6 lists roles (Anonymous, Authenticated, Admin) but does not specify which custom permissions are granted by default to each role. No test can verify correct post-install permission state.

5. **No permission revocation regression test** — No test: given admin grants permission → revokesstored session still gated. Especially relevant if token-auth and Drupal session auth coexist.

### Suggested minimal diff direction (PM — AC addition)
```
# Add to product documentation or acceptance criteria:
Permission inventory:
- List each permission defined in permissions.yml (machine name + label + description)
- For each permission: which routes enforce it? If none → document as intentionally unused (token-auth gates all routes)
- Default role assignments post-install: Anonymous: none; Authenticated: view; Admin: all
```

## ROI estimate
- ROI: 3
- Rationale: The `_access: TRUE` / dead-permissions finding is security-adjacent and meaningful, but the PM follow-up was already queued in `-10`. Incremental ROI is in the detailed gap list, which gives PM concrete AC additions without duplicating work. ROI 3 reflects coverage value with low urgency given the prior follow-up.
```

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-12.md`**

```markdown
- Status: done
- Summary: Reviewed `src/Controller/ApiController.php` spec-level (forseti.life inaccessible; review grounded in dev outboxes for cycles -3, -5, and -10 from `sessions/dev-forseti-agent-tracker/outbox/`, PM product documentation v6, and BA API gap analysis). This is the module's primary API surface and carries the standing QA BLOCK: missing `agent_id` validation allows NULL to propagate to storage, producing HTTP 500 instead of 400 on missing required field (reproducible, dev patch ready and unapplied). Eight additional testability gaps identified beyond the standing BLOCK, covering auth token validation, request/response schema, error code contract, and resource exhaustion. One follow-up queued to dev (apply existing patch); no new PM follow-up created as response schema AC is already an open item from prior cycles.

## Next actions
- Executor: apply `ApiController.php` patch from `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` — this is ROI 7, the highest-priority unapplied change in the module.
- PM: add response schema and HTTP status code contract to product AC (see gap #3 and #6 below).
- QA: once patch is applied and test environment is available, execute QA BLOCK reproduction steps to confirm fix.

## Blockers
- QA BLOCK (standing): `ApiController.php` POST with missing `agent_id` → NULL propagates to storage → HTTP 500. Dev patch ready but unapplied. v1 CANNOT ship until this is resolved.
- forseti.life inaccessible — cannot execute tests directly; spec-level only.

## QA BLOCK (standing)

**File:** `src/Controller/ApiController.php`
**Reproduction steps:**
1. POST to `/copilot-agent-tracker/api/agent` with a valid token but no `agent_id` field in body
2. Observe HTTP 500 (expected: HTTP 400 with error message)
**Severity:** BLOCK — silent data corruption risk; also exposes internal server error to callers
**Dev patch:** `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` (9-line diff, `agent_id` null check + `json_encode ?: NULL` guard)
**Status:** Patch ready; executor must apply.

## QA review: ApiController.php (spec-level)

**File reviewed:** `src/Controller/ApiController.php`
**Review basis:** Dev outboxes -3, -5, -10; PM product doc v6; KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` (agent_id is a string PK, not Drupal uid).

### Testability gaps

1. **[BLOCK — standing] No `agent_id` null validation** — POST without `agent_id` → HTTP 500 instead of 400. Dev patch ready and unapplied.

2. **No auth token validation negative tests** — No AC for: malformed token (not UUID), expired token, valid token for wrong agent, no token at all. Each should produce 401, but there is no test asserting this.

3. **No request/response schema AC** — No document specifies required vs. optional fields in the POST body, nor the exact JSON structure of success responses. QA cannot write schema assertion tests without this.

4. **No HTTP status code contract** — No AC listing: which HTTP status each case produces (200 vs 201 for create, 400 vs 422 for validation error, 401 vs 403 for auth failure). Without this, any status code change is untestable.

5. **`json_encode() ?: NULL` guard missing (unpatched)** — If an agent status value is not JSON-encodable, `json_encode` returns `false`; no guard exists. No AC for what the API returns in this case. Dev patch includes the guard.

6. **No pagination/limit AC for GET** — GET returns all agents with no stated limit. No test for: 0 agents, 1 agent, 100+ agents. No AC for maximum response size or pagination behavior.

7. **No concurrent POST regression test** — Two simultaneous POSTs for the same `agent_id`: one should succeed, one should fail with conflict (409?) or be idempotent. No AC for this behavior.

8. **No `Content-Type` validation test** — POST without `Content-Type: application/json`: behavior undefined. No AC for this; could produce 500 or silent misparse.

9. **No resource exhaustion / oversized payload test** — No AC for maximum body size; no test that an extremely large payload is rejected rather than causing a memory error.

### Suggested minimal diff direction (dev — patch already produced)
See `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` for the 9-line patch. Additional AC additions needed from PM before QA can close gaps #2–#9.

### Follow-up queued

**Item:** `sessions/dev-forseti-agent-tracker/inbox/20260222-qa-apply-apicontroller-patch/` (if not already present)
- Title: Apply ApiController.php agent_id null validation + json_encode guard
- File path: `src/Controller/ApiController.php`
- Minimal diff: as specified in dev outbox `20260222-idle-refactor-copilot_agent_tracker-10.md`
- Verification steps: POST without `agent_id` → assert HTTP 400 (not 500); POST with non-encodable status → assert no server error
- roi.txt: 7

## ROI estimate
- ROI: 6
- Rationale: ApiController.php is the primary API surface and carries a standing QA BLOCK with a ready patch — highest-urgency open item after queue administration. Gaps #2–#9 are medium-urgency AC additions that unlock the full test suite for v1.
```

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-13.md`**

```markdown
- Status: done
- Summary: Reviewed `src/Controller/DashboardController.php` spec-level (forseti.life inaccessible; review grounded in BA module audit from `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md`, PM product documentation v6 user journeys/dashboard section, and prior dev refactor cycles). The dashboard controller renders the agent tracker admin UI — it is the primary Drupal-facing entry point for human operators. Six testability gaps identified, primarily around render/template assertions, empty-state handling, and the interaction between `_access: TRUE` routing and the dashboard's actual permission expectations. One follow-up queued to PM for dashboard-specific AC additions. KB reference: KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` (agent_id string PK used as display key).

## Next actions
- PM: add dashboard-specific AC (empty state, error state, render assertions) — see follow-up item queued below.
- QA: once dashboard AC is written and test environment available, add dashboard render test to the existing copilot_agent_tracker smoke test checklist (produced in `-8` outbox).
- Executor: check queue cap before creating follow-up — do NOT create if `pm-forseti-agent-tracker` inbox already has 3+ items.

## Blockers
- forseti.life inaccessible — spec-level only.
- Dashboard AC is absent; render tests are untestable without knowing expected template structure.

## QA review: DashboardController.php (spec-level)

**File reviewed (inferred):** `src/Controller/DashboardController.php`
**Review basis:** BA module audit (8 gaps); PM product doc v6 (dashboard user journey); KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.

### Testability gaps

1. **No AC for empty-state render** — No test for: dashboard with zero agents returns a meaningful empty state (not a blank page or PHP warning). PM doc v6 does not specify the empty-state UI.

2. **No AC for error-state render** — If `AgentTrackerStorage` throws an exception or returns malformed data, no AC defines the dashboard fallback (error message? partial render? redirect?). No test covers this.

3. **No template/render assertion** — No AC specifies expected HTML structure, CSS classes, or data-attribute markers. Without these, QA cannot assert the dashboard rendered correctly beyond "HTTP 200."

4. **Access control ambiguity (same as routing gap)** — If `_access: TRUE` on the dashboard route, any anonymous user can load the dashboard. No AC states whether the dashboard is intentionally public or should require a Drupal permission. Without this, no access-control test can be written.

5. **No AC for stale/cached data display** — If the agent list is cached, no AC defines the max staleness acceptable or how to force a refresh. No test for "agent added via API, dashboard reflects new agent within X seconds."

6. **No regression test for `agent_id` display** — `agent_id` is a string PK (not Drupal uid); if the template erroneously displays `uid` or `nid`, it would silently show wrong data. No test asserts that displayed identifiers match `agent_id` string values, not numeric Drupal entity IDs.

### Suggested minimal diff direction (PM — AC additions)
```
# Add to acceptance criteria / product doc:
Dashboard AC:
- AC-DASH-1: GET /copilot-agent-tracker with 0 agents → renders empty-state message (not blank/500)
- AC-DASH-2: GET /copilot-agent-tracker with N agents → each agent row displays correct agent_id (string), status, last_seen
- AC-DASH-3: GET /copilot-agent-tracker with storage error → renders error state, no exception exposed to browser
- AC-DASH-4: Access — anonymous GET: define expected result (200 or 403)
```

### Follow-up queued

**Item:** `sessions/pm-forseti-agent-tracker/inbox/20260222-qa-ac-dashboard-controller/`
- Title: Add AC for DashboardController render, empty state, and access control
- File path(s): `src/Controller/DashboardController.php`, dashboard Twig template
- Minimal diff description: Add AC-DASH-1 through AC-DASH-4 to product documentation v7 or acceptance criteria document
- Verification steps: QA will add 4 test cases to smoke test checklist once AC is approved; tests verify empty state, correct agent_id display, error state, and access control
- roi.txt: 3

## ROI estimate
- ROI: 3
- Rationale: DashboardController is the human-operator UI entry point; empty-state and error-state gaps are real but lower urgency than the ApiController QA BLOCK. ROI 3 reflects meaningful coverage value with no standing BLOCK.
```
