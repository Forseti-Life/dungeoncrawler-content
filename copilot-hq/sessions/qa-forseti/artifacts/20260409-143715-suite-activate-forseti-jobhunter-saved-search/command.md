# Suite Activation: forseti-jobhunter-saved-search

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T14:37:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-saved-search"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-saved-search/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-saved-search-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-saved-search",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-saved-search"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-saved-search-<route-slug>",
     "feature_id": "forseti-jobhunter-saved-search",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-saved-search",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
