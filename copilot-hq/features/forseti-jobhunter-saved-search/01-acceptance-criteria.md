# Acceptance Criteria: forseti-jobhunter-saved-search

- Feature: forseti-jobhunter-saved-search
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Save and re-run Google Jobs search parameters. New `jobhunter_saved_searches` table. Saved searches panel embedded in the existing search page. Max 10 per user.

## Acceptance criteria

### AC-1: Save search button
- "Save this search" button is present on `/jobhunter/googlejobssearch` when a non-empty query has been submitted.
- POST to `job_hunter.saved_search_save` (POST-only, CSRF-guarded) saves `keywords` + `location` (if present).
- Keywords capped at 256 chars, location at 128 chars server-side; HTML tags stripped.
- On success: confirmation message; saved search appears in the panel.
- Missing/invalid CSRF → 403.

### AC-2: Saved searches panel
- Panel rendered on the search page (below search form or in a sidebar section).
- Lists up to 10 saved searches with: keyword string as name, "Re-run" link, "Delete" button.

### AC-3: Re-run link
- Re-run link redirects to `/jobhunter/googlejobssearch?q={keywords}&location={location}`.
- Pre-populates the search form with saved params.

### AC-4: Delete
- "Delete" button POSTs to `job_hunter.saved_search_delete` (POST-only, CSRF-guarded).
- `saved_search_id` path/body param must be an integer owned by the current user.
- On success: search removed from panel.
- Missing/invalid CSRF → 403.

### AC-5: Max 10 limit
- When user has 10 saved searches: "Save this search" button is hidden or disabled with the message "Maximum saved searches reached. Delete one to save a new search."
- Verify server-side: if a POST arrives when user already has 10, return 400 with that message.

### AC-6: DB schema
- `jobhunter_saved_searches` table created via `hook_update_N`.
- Schema: `id` (serial PK), `uid` (int), `keywords` (varchar 256), `location` (varchar 128), `created` (int), `updated` (int).
- Index on `uid`.

### AC-7: CSRF guards
- `grep -A5 "job_hunter.saved_search_save" job_hunter.routing.yml` → `_csrf_token: 'TRUE'`.
- `grep -A5 "job_hunter.saved_search_delete" job_hunter.routing.yml` → `_csrf_token: 'TRUE'`.

## Security acceptance criteria

### Authentication/permission surface
- All saved search routes require `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`.
- All DB queries scoped to `uid == current_user->id()`.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/googlejobssearch` → 403 (anonymous).

### CSRF expectations
- Save and Delete: POST-only, `_csrf_token: 'TRUE'` (split-route pattern).
- Re-run is a GET link; no CSRF.

### Input validation requirements
- `keywords`: max 256 chars, strip HTML. `location`: max 128 chars, strip HTML.
- `saved_search_id` on delete: integer; non-integer → 400 or discard.
- Server enforces 10-search limit before inserting; POST with 10 existing → 400.

### PII/logging constraints
- Saved search keyword strings must NOT be logged to watchdog at any level.
- Delete: may log `saved_search_id` (integer) at debug level.

## Verification commands
```bash
# CSRF guards
grep -A8 "job_hunter.saved_search_save" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
grep -A8 "job_hunter.saved_search_delete" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# DB table after hook_update_N runs
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush php:eval "var_dump(\Drupal::database()->schema()->tableExists('jobhunter_saved_searches'));"

# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/googlejobssearch
# Expected: 403
```
