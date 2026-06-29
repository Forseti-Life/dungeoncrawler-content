# Suite Activation: forseti-jobhunter-google-jobs-ux

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T12:10:13+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-google-jobs-ux"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-google-jobs-ux/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-google-jobs-ux-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-google-jobs-ux",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-google-jobs-ux"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-google-jobs-ux-<route-slug>",
     "feature_id": "forseti-jobhunter-google-jobs-ux",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-google-jobs-ux",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-google-jobs-ux

- Feature: forseti-jobhunter-google-jobs-ux
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify pagination, result count, empty state, API error state, and access control for `/jobhunter/googlejobssearch` and the job detail page.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/googlejobssearch`
- Expected: `403`

### TC-2: Search with results — result count displayed
- Steps: Log in with job hunter access; search for "software engineer"; submit form.
- Expected: Result count label displayed ("N jobs found"). Count matches number of results visible.

### TC-3: Pagination renders for multi-page results
- Steps: Run a search that returns > 1 page of results.
- Expected: Pagination controls (prev/next and/or page numbers) rendered. `?page=2` loads next page.

### TC-4: Empty state — no results
- Steps: Search for a term unlikely to match (e.g., "xyzzy123noresponse").
- Expected: User-facing "no results" message shown. No blank list, no PHP notice or uncaught exception.

### TC-5: API error state
- Steps: (QA to simulate by temporarily misconfiguring API key or using a mock) — trigger an API exception.
- Expected: User-facing error message rendered on page. No PHP fatal or white screen. Error logged to watchdog (without query content or API key).

### TC-6: XSS — query param sanitization
- Steps: GET `/jobhunter/googlejobssearch?q=<script>alert(1)</script>`
- Expected: Query value rendered HTML-escaped in template; no script executed.

### TC-7: Oversized query param
- Steps: GET `/jobhunter/googlejobssearch?q=<257-character string>`
- Expected: Server truncates or rejects the query; no PHP error.

### TC-8: Job detail page renders
- Steps: From search results, click through to a job detail page.
- Expected: 200 response; job detail template renders without PHP errors.

### TC-9: Page param validation
- Steps: GET `/jobhunter/googlejobssearch?page=abc`
- Expected: Defaults to page 1; no PHP error.

## Regression notes
- Google Jobs routes (`job_hunter.google_jobs_search`, `job_hunter.google_jobs_job_detail`) must remain registered.
- Run: `./vendor/bin/drush router:debug | grep google_jobs`

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-google-jobs-ux

- Feature: forseti-jobhunter-google-jobs-ux
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

UX polish for Google Jobs search at `/jobhunter/googlejobssearch`. Adds pagination controls, result count display, empty-state messaging, and API error state. No new routes needed; changes are in `GoogleJobsSearchController` and existing templates.

## Acceptance criteria

### AC-1: Pagination
- When the API returns more results than fit on one page, "Previous" and "Next" links render correctly.
- Current page and total page count displayed (e.g., "Page 2 of 5").
- Page parameter passed as GET `?page=N`; controller respects this.

### AC-2: Result count display
- Total matching result count displayed above the results list.
- Format: "{N} jobs found" (or equivalent translated string).
- When API returns a total count, that value is used; otherwise count of current page results shown.

### AC-3: Empty state
- When API returns zero results: a user-facing message is shown explaining no results were found and suggesting alternative search terms.
- No blank list, no PHP notice, no uncaught exception.

### AC-4: API error state
- When `GoogleJobsSearchController` catches an API exception/error:
  - A user-facing error message renders on the page.
  - The exception is logged to watchdog at `WATCHDOG_ERROR` (or `\Drupal\Core\Logger\RfcLogLevel::ERROR`).
  - Log message must NOT include API keys or raw credentials.
  - No PHP fatal error or white screen.

### AC-5: Job detail page renders
- `GET /jobhunter/googlejobsdetail/{job_id}` (or equivalent detail route) returns 200 for a valid job ID with no PHP errors.

## Security acceptance criteria

### Authentication/permission surface
- Search and detail routes require `_permission: 'access job hunter'`.
- Anonymous access → 403.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/googlejobssearch` → 403 without auth.

### CSRF expectations
- Search is a GET-only page; no form POST. No CSRF token required on this feature.

### Input validation requirements
- Query string `q` param: max 256 characters enforced server-side. HTML tags stripped before API call and before rendering in template.
- Page param `?page=N`: must be validated as a positive integer; non-integer defaults to page 1.

### PII/logging constraints
- Search queries must NOT be logged to watchdog (privacy requirement).
- API error log must include only: error code, exception type, timestamp. No query text, no API key fragments.

## Verification commands
```bash
# Anonymous access check
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/googlejobssearch
# Expected: 403

# PHP lint on controller
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/GoogleJobsSearchController.php

# Check templates exist
ls /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/google-jobs-search.html.twig
ls /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/job-search-results.html.twig
```
