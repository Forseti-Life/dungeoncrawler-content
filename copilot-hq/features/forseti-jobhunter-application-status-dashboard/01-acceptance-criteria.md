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
