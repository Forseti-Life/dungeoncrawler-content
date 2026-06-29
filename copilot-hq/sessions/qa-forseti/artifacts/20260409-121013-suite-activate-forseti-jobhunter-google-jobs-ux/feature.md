# Feature Brief

- Work item id: forseti-jobhunter-google-jobs-ux
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260409-forseti-release-f
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (Job Hunter UX polish track 1.2)

## Summary

The Google Jobs search flow (`/jobhunter/googlejobssearch`) exists with templates but is missing several UX polish items: pagination controls, result count display, empty-state message when no results are returned, and a graceful error state when the Google Jobs API is unavailable or returns an error. No new routes are needed; changes are to the controller render array and the existing templates.

## Goal

- Users can page through Google Jobs search results without confusion.
- Users see how many results matched their query.
- Users get a clear message when the API is unavailable rather than a blank page.

## Acceptance criteria

- AC-1: Pagination — search results page renders "Page X of Y" navigation (prev/next links) when results exceed one page. Page parameter is passed via GET (`?page=N`).
- AC-2: Result count — total number of matching results is displayed above the results list (e.g. "42 jobs found for 'software engineer'").
- AC-3: Empty state — when the API returns zero results, a clear message is shown ("No jobs found for your search. Try different keywords or broaden your location."). No blank list or PHP notice.
- AC-4: API error state — when `GoogleJobsSearchController` catches an API exception, the page renders a user-facing error message ("Job search is temporarily unavailable. Please try again in a few minutes.") and logs the error to watchdog at `WATCHDOG_ERROR` level. No PHP fatal or white screen.
- AC-5: Job detail page (`google-jobs-job-detail.html.twig`) renders without error for a valid job ID.

## Non-goals

- New search filter UI (location/radius/salary — deferred).
- Caching of API responses.
- Saved-search functionality.

## Security acceptance criteria

- Authentication/permission surface: Search route requires `_permission: 'access job hunter'`. No anonymous access to search or job detail pages.
- CSRF expectations: No form POST on this page (read-only search). No CSRF token needed.
- Input validation requirements: Query string (`q` param) must be stripped of HTML/script before passing to API and before rendering in template. Max length enforced server-side (256 chars).
- PII/logging constraints: Search queries must NOT be logged to watchdog (privacy). API error messages must not include raw API keys or tokens in the log message.

## Implementation notes (to be authored by dev-forseti)

- Controller: `GoogleJobsSearchController::searchPage` — add pagination and result count to render array.
- API exception handling: wrap API call in try/catch; return render array with error message rather than letting exception bubble.
- Templates: `google-jobs-search.html.twig`, `google-jobs-job-detail.html.twig`, `job-search-results.html.twig`.

## Test plan (to be authored by qa-forseti)

- TC-1: Search with results → result count shown, pagination renders if > 1 page.
- TC-2: Search with no results → empty state message shown.
- TC-3: Anonymous access to search route → 403.
- TC-4: Simulate API error (mock/disconnect) → user-facing error shown, no white screen.
- TC-5: Job detail page renders for a valid job ID → 200.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (CEO dispatch, release-f grooming).
