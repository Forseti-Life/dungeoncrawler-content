# Suite Activation: forseti-csrf-post-routes-fix

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T18:09:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-csrf-post-routes-fix"`**  
   This links the test to the living requirements doc at `features/forseti-csrf-post-routes-fix/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-csrf-post-routes-fix-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-csrf-post-routes-fix",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-csrf-post-routes-fix"`**  
   Example:
   ```json
   {
     "id": "forseti-csrf-post-routes-fix-<route-slug>",
     "feature_id": "forseti-csrf-post-routes-fix",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-csrf-post-routes-fix",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-csrf-post-routes-fix

**QA owner:** qa-forseti
**Release:** 20260408-forseti-release-i

## Test cases

### TC-1: CSRF route enumeration passes
- **Type:** static analysis
- **Command:** `python3 enumerate_post_routes.py job_hunter.routing.yml`
- **Expected:** Zero "access job hunter" POST routes with CSRF=NO
- **Pass criteria:** Exit 0, no CSRF=NO rows for the 7 target routes

### TC-2: Authenticated form POST succeeds (step3)
- **Type:** functional, authenticated
- **Method:** POST to `/jobhunter/application-submission/{job_id}/step-3` with valid session cookie and CSRF token
- **Expected:** 200 or expected redirect (step advancement)
- **Pass criteria:** Not 403, not 500

### TC-3: Authenticated form POST succeeds (step5 submit-application)
- **Type:** functional, authenticated
- **Method:** POST to `/jobhunter/application-submission/{job_id}/submit-application` with valid session and token
- **Expected:** 200 or redirect (submission confirmation)
- **Pass criteria:** Not 403, not 500

### TC-4: Unauthenticated/missing-token POST rejected
- **Type:** security
- **Method:** POST to `/jobhunter/application-submission/{job_id}/submit-application` without CSRF token
- **Expected:** HTTP 403
- **Pass criteria:** Response status = 403

### TC-5: GET routes unaffected
- **Type:** regression
- **Method:** Site audit crawl of all `/jobhunter/` pages
- **Expected:** No new 403/500 on GET routes
- **Pass criteria:** QA site audit shows no new errors vs. baseline

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-csrf-post-routes-fix

KB reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md`

## AC-1 — All 7 POST routes have CSRF protection

**Given** `job_hunter.routing.yml` is inspected,
**When** I enumerate all routes with `_permission: 'access job hunter'` that accept POST,
**Then** each of the following routes has either:
  - `_csrf_token: 'TRUE'` on the POST-only route entry (split-route pattern), or
  - Confirmed `X-CSRF-Token` header enforcement at the controller level for AJAX routes.

Routes: `application_submission_step3`, `step3_short`, `step4`, `step4_short`, `step5`, `step5_short`, `step_stub_short`.

**Verification:** `python3 enumerate_post_routes.py job_hunter.routing.yml` → zero "access job hunter" POST routes with CSRF=NO.

## AC-2 — Legitimate user submission still works

**Given** an authenticated user is on the job application flow,
**When** they submit step3, step4, or step5 forms with a valid session,
**Then** the form submits successfully and returns the expected next step (200, no CSRF 403).

**Verification:** QA manual smoke test of the full step3→5 submission flow as authenticated user.

## AC-3 — Cross-origin POST is rejected

**Given** a crafted cross-origin form POST to `/jobhunter/application-submission/{job_id}/submit-application`,
**When** the request lacks a valid CSRF token,
**Then** the server returns HTTP 403.

**Verification:** QA confirms 403 on unauthenticated/cross-origin POST (or via CSRF token omission test).

## AC-4 — No regressions on GET routes

**Given** all existing GET-only application routes,
**When** an anonymous or authenticated user fetches them,
**Then** they return 200 (or expected auth redirect) with no change in behavior.

**Verification:** QA site audit crawl shows no new 403/5xx on application routes.
