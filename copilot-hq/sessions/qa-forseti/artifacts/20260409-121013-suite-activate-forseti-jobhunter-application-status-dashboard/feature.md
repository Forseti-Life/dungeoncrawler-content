# Feature Brief

- Work item id: forseti-jobhunter-application-status-dashboard
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260409-forseti-release-f
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (Job Hunter UX polish track 1.1)

## Summary

The `/jobhunter/my-jobs` route and `my-jobs.html.twig` template already exist and render a list of saved jobs. This feature makes the status pipeline display functional: visually distinguish each workflow status stage (Profile Pending → Applied → Interview → Offer/Reject/Archive), add filter-by-status and filter-by-company controls, and add a bulk-archive action for rejected/withdrawn jobs. No new data model is needed — the `workflow_status` field already exists on job records.

## Goal

- Users can see their saved jobs organized by pipeline stage at a glance.
- Users can filter to a specific status or company.
- Users can bulk-archive completed/rejected applications to keep the active list clean.

## Acceptance criteria

- AC-1: Status pipeline view renders correctly at `/jobhunter/my-jobs` — each status stage (profile_pending, applied, interview, offer, rejected, archived) shows a count badge and its jobs.
- AC-2: Filter by status — selecting a status from the filter dropdown reloads the page (GET param) showing only matching jobs.
- AC-3: Filter by company — company filter dropdown populated from current user's jobs; works in conjunction with status filter.
- AC-4: Bulk archive — checkbox list + "Archive selected" button sends a POST with CSRF token; selected jobs move to `archived` status.
- AC-5: Empty state — if no jobs match the active filter, a human-readable empty-state message is shown (not a blank list).
- AC-6: Pagination — existing pagination variables (`current_page`, `total_pages`) are rendered; 20 jobs per page default.

## Non-goals

- New workflow status values beyond existing enum.
- Email notifications for status changes.
- Sorting by column headers (deferred).

## Security acceptance criteria

- Authentication/permission surface: Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`. Anonymous access must return 403. No user can view another user's jobs.
- CSRF expectations: Bulk archive POST endpoint must use the split-route pattern: separate GET entry (no CSRF) and POST-only entry with `_csrf_token: 'TRUE'`. Twig form action must use `path()` which auto-appends `?token=`.
- Input validation requirements: Status filter value must be validated against known enum values server-side; company filter input must be escaped before use in DB query.
- PII/logging constraints: No job record PII (company name, job title) written to watchdog. Bulk archive action may log job IDs only at debug level.

## Implementation notes (to be authored by dev-forseti)

- Existing controller: `ApplicationSubmissionController::myJobs` — extend this method.
- Template: `my-jobs.html.twig` — pipeline stage grouping logic should be in the controller, not the template.
- Bulk archive route: add new `job_hunter.my_jobs_bulk_archive` route with POST-only + CSRF guard.

## Test plan (to be authored by qa-forseti)

- TC-1: Status filter — GET `/jobhunter/my-jobs?filter_status=applied` returns only applied jobs.
- TC-2: Company filter — combined filter works (status + company).
- TC-3: Bulk archive POST — valid CSRF token → jobs archived; missing token → 403.
- TC-4: Anonymous access → 403 at `/jobhunter/my-jobs`.
- TC-5: Empty state message visible when no jobs match filter.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (CEO dispatch, release-f grooming).
