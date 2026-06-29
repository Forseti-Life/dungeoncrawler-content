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
