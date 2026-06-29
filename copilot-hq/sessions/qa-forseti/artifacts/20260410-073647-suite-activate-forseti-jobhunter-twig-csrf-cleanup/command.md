# Suite Activation: forseti-jobhunter-twig-csrf-cleanup

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-10T07:36:47+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-twig-csrf-cleanup"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-twig-csrf-cleanup/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-twig-csrf-cleanup-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-twig-csrf-cleanup",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-twig-csrf-cleanup"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-twig-csrf-cleanup-<route-slug>",
     "feature_id": "forseti-jobhunter-twig-csrf-cleanup",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-twig-csrf-cleanup",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-twig-csrf-cleanup

**QA owner:** qa-forseti  
**Date:** 2026-04-10  
**Feature status:** BLOCK in progress — dev must complete cleanup of 3 remaining fields in 2 templates before QA executes.  
**Scope:** Remove all dead `name="form_token"` and `name="token"` hidden POST body fields from `job_hunter` Twig templates. Implementation (5 templates) tracked in `feature.md`.

**KB reference:** CSRF split-route pattern — `_csrf_token: 'TRUE'` on a route causes `RouteProcessorCsrf` to append `?token=` to the URL when `path()` is called in Twig; `CsrfAccessCheck` reads only that URL query token — POST body hidden fields are silently ignored and therefore dead code.

---

## Test case inventory

### TC-01 — Dead field grep (all templates)

- **Description:** Verify no hidden input fields with token-like names remain in any `job_hunter` template.
- **Suite:** `static-grep` (shell command, no auth required)
- **Command:**
  ```bash
  grep -rn 'name.*form_token\|name="token"\|name="csrf"' \
    sites/forseti/web/modules/custom/job_hunter/templates/*.twig
  ```
- **Expected:** Zero results (exit code 1 from grep = no matches = PASS).
- **Roles covered:** N/A (static check)
- **Automation note:** Fully automatable as a shell assertion.

### TC-02 — Cover letter generate form still works (POST with URL token)

- **Description:** Authenticated user submits the cover letter generate form on `/jobhunter/coverletter/{job_id}/generate`. CSRF is enforced via `?token=` in the URL query string (route `job_hunter.cover_letter_generate`, `_csrf_token: 'TRUE'`); no POST body token needed.
- **Suite:** `role-url-audit` / Playwright
- **Method:** POST to `/jobhunter/coverletter/{job_id}/generate` with valid `?token=` and without `form_token` in body.
- **Expected HTTP status:** 200 or redirect (not 403)
- **Roles covered:** Authenticated (`access job hunter` permission)
- **Automation note:** Requires an authenticated session and a valid `?token=` from `path()`. Playwright or curl with session cookie.

### TC-03 — Interview prep save form still works (POST with URL token)

- **Description:** Authenticated user saves interview prep notes on `/jobhunter/interview-prep/{job_id}/save`. Route `job_hunter.interview_prep_save` uses `_csrf_token: 'TRUE'`.
- **Suite:** `role-url-audit` / Playwright
- **Method:** POST to `/jobhunter/interview-prep/{job_id}/save` with valid `?token=` and without `form_token` in body.
- **Expected HTTP status:** 200 or redirect (not 403)
- **Roles covered:** Authenticated (`access job hunter`)
- **Automation note:** Requires authenticated session + valid URL token.

### TC-04 — Saved search save form still works (POST with URL token)

- **Description:** Authenticated user saves a search on `/jobhunter/saved-search/save`. Route `job_hunter.saved_search_save`, `_csrf_token: 'TRUE'`.
- **Suite:** `role-url-audit` / Playwright
- **Method:** POST to `/jobhunter/saved-search/save` with valid `?token=`.
- **Expected HTTP status:** 200 or redirect (not 403)
- **Roles covered:** Authenticated
- **Automation note:** Requires authenticated session.

### TC-05 — Saved search delete form still works (POST with URL token)

- **Description:** Authenticated user deletes a saved search on `/jobhunter/saved-search/{id}/delete`. Route `job_hunter.saved_search_delete`, `_csrf_token: 'TRUE'`.
- **Suite:** `role-url-audit` / Playwright
- **Method:** POST to `/jobhunter/saved-search/{id}/delete` with valid `?token=`.
- **Expected HTTP status:** 200 or redirect (not 403)
- **Roles covered:** Authenticated
- **Automation note:** Requires authenticated session and an existing saved search ID.

### TC-06 — Job tailoring save-resume form still works (POST with URL token)

- **Description:** Authenticated user saves a tailored resume on `/jobhunter/jobtailoring/{job_id}/save-resume`. Route `job_hunter.job_tailoring_save_resume`, `_csrf_token: 'TRUE'`.
- **Suite:** `role-url-audit` / Playwright
- **Method:** POST to `/jobhunter/jobtailoring/{job_id}/save-resume` with valid `?token=`.
- **Expected HTTP status:** 200 or redirect (not 403)
- **Roles covered:** Authenticated
- **Automation note:** Requires authenticated session and valid job_id.

### TC-07 — CSRF rejection without URL token

- **Description:** POST to a CSRF-protected route without the `?token=` query parameter must be rejected with 403.
- **Suite:** `role-url-audit`
- **Method:** POST to `/jobhunter/coverletter/{job_id}/generate` with NO `?token=` in URL.
- **Expected HTTP status:** 403
- **Roles covered:** Authenticated (anonymous would also be blocked by auth gate)
- **Automation note:** Straightforward curl assertion. Representative of all 5 CSRF-protected routes.

### TC-08 — Anonymous blocked from form pages

- **Description:** Anonymous requests to auth-gated job_hunter pages return 403 or redirect to login. CSRF cleanup must not change auth gate behavior.
- **Suite:** `role-url-audit`
- **Method:** GET `/jobhunter/coverletter/1`, `/jobhunter/interview-prep/1`, `/jobhunter/saved-searches`, `/jobhunter/googlejobssearch`, `/jobhunter/jobtailoring/1`
- **Expected HTTP status:** 403 or 302 redirect to `/user/login`
- **Roles covered:** Anonymous
- **Automation note:** Already covered by existing `role-url-audit` suite; verify no regression.

---

## AC coverage map

| AC item | Test case(s) | Automatable? |
|---|---|---|
| grep returns 0 results | TC-01 | Yes — shell assertion |
| POST to cover-letter form works | TC-02 | Yes — Playwright/curl with auth |
| POST to interview-prep form works | TC-03 | Yes — Playwright/curl with auth |
| POST to saved-searches form works | TC-04, TC-05 | Yes — Playwright/curl with auth |
| No additional token-like hidden fields | TC-01 | Yes — same grep |
| POST without `?token=` rejected 403 | TC-07 | Yes — curl |
| Authenticated user form success | TC-02–TC-06 | Yes |
| Anonymous blocked | TC-08 | Yes — existing suite |
| No data loss (template-only change) | N/A — inherent; no migration | N/A |

---

## Notes to PM

1. **AC scope vs. implementation scope mismatch:** The AC's Happy Path lists 3 POST routes (cover-letter, interview-prep, saved-searches) but the expanded scope in `feature.md` covers 5 templates and 5 POST routes. TC-05 and TC-06 were added to cover the expanded scope. If PM intends a narrower AC, revise `01-acceptance-criteria.md` before Stage 0.
2. **TC-02 through TC-06 require authenticated Playwright/curl tests.** These cannot be expressed as simple static grep checks. Mark as `playwright` suite at Stage 0.
3. **TC-07 (403 rejection)** is the critical regression check — if CSRF enforcement is accidentally removed rather than the dead field, this test catches it.

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)

## Gap analysis reference

Gap Analysis in `feature.md` is complete. All three template changes are `TEST-ONLY` — implementation exists in dev commit c0f597279.

## Happy Path
- [ ] `[TEST-ONLY]` `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` returns 0 results (dead fields removed).
- [ ] `[TEST-ONLY]` POST to `/jobhunter/cover-letter/{id}` succeeds with valid CSRF token in URL query string (functional form still works).
- [ ] `[TEST-ONLY]` POST to `/jobhunter/interview-prep/{id}` succeeds with valid CSRF token in URL query string.
- [ ] `[TEST-ONLY]` POST to `/jobhunter/saved-searches` succeeds with valid CSRF token in URL query string.

## Edge Cases
- [ ] `[TEST-ONLY]` No additional hidden input fields with token-like names (`form_token`, `token`, `csrf`) remain in any job_hunter template.

## Failure Modes
- [ ] `[TEST-ONLY]` POST without a valid `?token=` query parameter is rejected with 403 (CSRF validation still enforced via URL token).

## Permissions / Access Control
- [ ] Authenticated user behavior: form submission succeeds for authenticated users.
- [ ] Anonymous user behavior: form pages not accessible to anonymous (existing auth gates unchanged).

## Data Integrity
- [ ] No data loss: change is template-only; no migration needed.
- [ ] Rollback path: revert commit c0f597279 removes dead fields re-addition; no functional impact.

## Knowledgebase check
- Related lessons: `knowledgebase/lessons/` — CSRF split-route pattern (Drupal `_csrf_token: 'TRUE'` on GET+POST routes causes GET 403; use split-route pattern; `RouteProcessorCsrf` appends `?token=` for Twig `path()`).
