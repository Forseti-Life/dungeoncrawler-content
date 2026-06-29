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
