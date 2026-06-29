# Test Plan: forseti-jobhunter-saved-search

- Feature: forseti-jobhunter-saved-search
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify save search button, saved searches panel, re-run link, delete CSRF, 10-search limit, DB schema, input sanitization, and access control.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/googlejobssearch`
- Expected: `403`

### TC-2: Save search — valid CSRF
- Steps: Run a search; click "Save this search" (valid CSRF).
- Expected: Confirmation message; search appears in the panel.

### TC-3: Save search — invalid CSRF → 403
- Steps: POST to `job_hunter.saved_search_save` without CSRF token.
- Expected: 403.

### TC-4: Panel renders saved searches
- Steps: After saving 1+ searches, load the search page.
- Expected: Saved searches panel shows each entry with keyword, re-run link, delete button.

### TC-5: Re-run link
- Steps: Click "Re-run" on a saved search.
- Expected: Search page reloads with saved keywords (and location) in the form; results shown.

### TC-6: Delete — valid CSRF
- Steps: Click "Delete" on a saved search (valid CSRF).
- Expected: Search removed from panel.

### TC-7: Delete — invalid CSRF → 403
- Steps: POST to `job_hunter.saved_search_delete` without CSRF token.
- Expected: 403.

### TC-8: 10-search limit enforced
- Steps: Save 10 searches; attempt to save an 11th.
- Expected: Save button hidden/disabled with message on the frontend; if POST sent anyway, server returns 400.

### TC-9: Keyword > 256 chars
- Steps: POST keywords string longer than 256 chars.
- Expected: Server rejects or truncates; no PHP error.

### TC-10: XSS — keyword sanitization
- Steps: Save a search with keyword `<script>alert(1)</script>`.
- Expected: Keyword rendered HTML-escaped in the panel; no script executed.

### TC-11: DB table created
- Steps: `./vendor/bin/drush php:eval "var_dump(\Drupal::database()->schema()->tableExists('jobhunter_saved_searches'));"`
- Expected: `bool(true)`.

### TC-12: Cross-user delete blocked
- Steps: User A attempts to delete User B's saved search (manipulate saved_search_id in POST).
- Expected: 403 or silent discard (search not deleted).

## Regression notes
- Google Jobs search page must still render correctly with the panel added.
- Existing `job_hunter.google_jobs_search` route must remain registered.
