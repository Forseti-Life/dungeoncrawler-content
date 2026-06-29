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
