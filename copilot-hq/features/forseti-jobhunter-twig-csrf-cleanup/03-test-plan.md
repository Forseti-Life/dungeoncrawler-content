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
