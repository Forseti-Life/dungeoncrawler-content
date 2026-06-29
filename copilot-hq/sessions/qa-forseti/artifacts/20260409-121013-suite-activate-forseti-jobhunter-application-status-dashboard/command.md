# Suite Activation: forseti-jobhunter-application-status-dashboard

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
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-application-status-dashboard"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-application-status-dashboard/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-application-status-dashboard-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-application-status-dashboard",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-application-status-dashboard"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-application-status-dashboard-<route-slug>",
     "feature_id": "forseti-jobhunter-application-status-dashboard",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-application-status-dashboard",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-application-status-dashboard

- Feature: forseti-jobhunter-application-status-dashboard
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify pipeline stage display, status/company filters, bulk archive CSRF, empty state, pagination, and access control at `/jobhunter/my-jobs`.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs`
- Expected: `403`

### TC-2: Authenticated page load — all stages displayed
- Steps: Log in as a user with jobs in multiple workflow stages; GET `/jobhunter/my-jobs`.
- Expected: Each stage with jobs shows a stage header and count badge. Jobs appear under correct stage.

### TC-3: Status filter
- Steps: GET `/jobhunter/my-jobs?filter_status=applied`
- Expected: Only jobs with `workflow_status = applied` shown. Jobs in other stages not visible.

### TC-4: Invalid status filter
- Steps: GET `/jobhunter/my-jobs?filter_status=<script>alert(1)</script>`
- Expected: Empty result set (no jobs), no PHP error, no XSS rendering.

### TC-5: Company filter
- Steps: GET `/jobhunter/my-jobs?filter_company=Acme+Corp`
- Expected: Only jobs from "Acme Corp" shown.

### TC-6: Combined filter (status + company)
- Steps: GET `/jobhunter/my-jobs?filter_status=interview&filter_company=Acme+Corp`
- Expected: Only interview-stage jobs from Acme Corp shown.

### TC-7: Empty state
- Steps: Apply a filter that matches no jobs.
- Expected: User-facing empty state message shown. No blank list, no PHP notice.

### TC-8: Bulk archive — valid CSRF
- Steps: Select 2+ jobs via checkboxes; submit "Archive selected" with valid CSRF token.
- Expected: Selected jobs move to `archived` status; page reloads with confirmation message.

### TC-9: Bulk archive — missing CSRF
- Steps: POST to bulk archive route without CSRF token (via `curl --data "job_ids[]=1"`).
- Expected: 403 response.

### TC-10: Pagination
- Steps: User has > 20 jobs; GET `/jobhunter/my-jobs`.
- Expected: Pagination controls (prev/next) rendered; `/jobhunter/my-jobs?page=2` loads the next 20.

### TC-11: Cross-user access
- Steps: Authenticated as User A, attempt to view or archive User B's jobs (by manipulating job IDs in bulk archive POST).
- Expected: 403 or silent discard of IDs not owned by User A.

## Regression notes
- Existing My Jobs route (`job_hunter.my_jobs`) must continue to route correctly after split from `JobApplicationController` (release-d controller split).
- Run: `./vendor/bin/drush router:debug | grep my_jobs` to verify routes are registered.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-application-status-dashboard

- Feature: forseti-jobhunter-application-status-dashboard
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

User-facing "My Jobs" pipeline view at `/jobhunter/my-jobs`. Adds status pipeline display, status/company filters, bulk archive, empty state, and pagination. No new data model — uses existing `workflow_status` field.

## Acceptance criteria

### AC-1: Pipeline stage display
- Each `workflow_status` value (profile_pending, applied, interview, offer, rejected, archived) shows a stage header with a count badge.
- Jobs are grouped under their respective stage in the rendered page.

### AC-2: Filter by status
- GET `/jobhunter/my-jobs?filter_status=applied` returns only jobs in `applied` status.
- Filter parameter is sanitized server-side against the known status enum. Unknown values produce an empty result (not a PHP error).

### AC-3: Filter by company
- Company dropdown populated from the current user's job records only.
- Works in combination with `filter_status` (AND logic).

### AC-4: Bulk archive
- Checkbox appears on each job card.
- "Archive selected" button submits a POST with selected job IDs and a CSRF token.
- On success: selected jobs move to `archived` status and the page reloads with confirmation message.
- On missing/invalid CSRF token: 403 response.
- Verify: `grep -A5 "job_hunter.my_jobs_bulk_archive" job_hunter.routing.yml` shows `methods: [POST]` and `_csrf_token: 'TRUE'`.

### AC-5: Empty state
- When no jobs match the active filter: a human-readable message is displayed (not a blank list or PHP notice).

### AC-6: Pagination
- 20 jobs per page.
- "Previous" and "Next" links rendered when total_pages > 1.
- `current_page` and `total_pages` variables passed to template.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`.
- Anonymous GET → 403.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs` → 403 without auth.
- Controller must scope all job queries to `uid = current_user->id()`. Cross-user data access must be impossible.

### CSRF expectations
- Bulk archive action uses the split-route pattern: GET entry (no CSRF) and POST-only entry with `_csrf_token: 'TRUE'`.
- Twig `path()` call for the form action auto-appends `?token=`.

### Input validation requirements
- `filter_status` validated against enum server-side; invalid values treated as empty filter.
- `filter_company` sanitized (strip HTML/script) before use in query.
- Bulk archive: job ID list validated as integers; non-integers discarded silently.

### PII/logging constraints
- No job record content (company name, job title, notes) written to watchdog.
- Bulk archive action may log job IDs (integers only) at debug level.

## Verification commands
```bash
# Confirm route CSRF guard
grep -A8 "job_hunter.my_jobs_bulk_archive" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# Anonymous access check
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs
# Expected: 403

# Drush route debug
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush router:debug | grep my_jobs
```
