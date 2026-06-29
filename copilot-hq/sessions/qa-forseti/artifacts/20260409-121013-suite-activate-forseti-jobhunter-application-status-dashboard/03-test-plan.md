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
