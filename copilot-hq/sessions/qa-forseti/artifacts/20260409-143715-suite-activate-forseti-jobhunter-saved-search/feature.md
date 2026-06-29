# Feature Brief

- Work item id: forseti-jobhunter-saved-search
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260409-forseti-release-g
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO direction 2026-04-09 (job recommendations / saved search track), BA grooming 2026-04-09

## Summary

The Google Jobs search (`/jobhunter/googlejobssearch`) requires users to re-enter search terms every session. This feature adds a "Save this search" button on the search results page that persists the current query parameters (keywords, location if applicable) in a new `jobhunter_saved_searches` table. Users can then re-run saved searches from a "Saved Searches" panel on the search page or from My Jobs. This is a lightweight CRUD feature — no background job processing required.

## Goal

- Users can save a search and re-run it in one click on a future visit.
- Users can manage (rename, delete) their saved searches.
- Maximum 10 saved searches per user (to keep scope bounded).

## Acceptance criteria

- AC-1: "Save this search" button on `/jobhunter/googlejobssearch` when a search has been run (query is non-empty). POSTs current query params (keywords + location if present) to a CSRF-guarded endpoint; saved search appears in the user's saved search list.
- AC-2: Saved searches panel — rendered on the search page below the search form (or in a sidebar); lists up to 10 saved searches by name with a "Re-run" link and a "Delete" button.
- AC-3: Re-run link — clicking re-populates the search form with saved params and submits the search (GET with params).
- AC-4: Delete — POST to CSRF-guarded delete endpoint; search removed from list.
- AC-5: Limit enforcement — when a user already has 10 saved searches, "Save this search" is hidden or disabled with a message ("Maximum saved searches reached. Delete one to save a new search.").
- AC-6: Search name — saved with the keyword string as the default name; user can optionally rename (inline edit or a rename form) in a follow-on release (not in scope here — name defaults to keyword string).

## Non-goals

- Proactive job recommendations or push notifications based on saved searches (deferred).
- Scheduled re-run / email alerts (deferred).
- Search name editing in this release.

## Security acceptance criteria

- Authentication/permission surface: All saved search routes require `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`. Controller scopes all queries to `uid == current_user->id()`. Anonymous access → 403.
- CSRF expectations: Save and Delete are POST-only with `_csrf_token: 'TRUE'` (split-route pattern). Re-run is a GET link — no CSRF.
- Input validation requirements: Query keyword string capped at 256 characters server-side before saving. Location string capped at 128 characters. HTML/script tags stripped from both. `saved_search_id` path param on delete must be an integer.
- PII/logging constraints: Saved search keyword strings must NOT be logged to watchdog at any level. Delete may log `saved_search_id` (integer) at debug level only.

## Implementation notes (to be authored by dev-forseti)

- New DB table: `jobhunter_saved_searches` (`id`, `uid`, `keywords`, `location`, `created`, `updated`). Add via `hook_update_N`.
- New routes: `job_hunter.saved_search_save` (POST + CSRF), `job_hunter.saved_search_delete` (POST + CSRF), no new GET route required (panel embedded in existing search page render array).
- Extend `GoogleJobsSearchController::searchPage` render array to include the saved searches panel.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous access to search page → 403.
- TC-2: Save search with valid CSRF → search appears in panel.
- TC-3: Save search with invalid CSRF → 403.
- TC-4: Re-run link → search page reloads with saved params.
- TC-5: Delete search with valid CSRF → search removed from panel.
- TC-6: Delete with invalid CSRF → 403.
- TC-7: 10 saved searches → "Save" button hidden/disabled with message.
- TC-8: Keyword > 256 chars → server rejects or truncates; no PHP error.
- TC-9: XSS in keyword — saved keyword rendered HTML-escaped in panel.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (pm-forseti dispatch, release-g grooming).
