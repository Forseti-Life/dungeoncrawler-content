# Feature Brief

- Work item id: forseti-jobhunter-bulk-status-update
- Website: forseti.life
- Module: job_hunter
- Status: in_progress
- Release: 20260411-coordinated-release
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: Product backlog — user productivity improvement for high-volume job seekers 2026-04-11

## Summary

Users with many job applications often need to update the status of multiple applications at once (e.g., mark several positions as "closed" or "no response" after a job search round). Currently every status change requires opening each application individually. This feature adds a bulk status update capability to the Application Status Dashboard: checkboxes on application rows, a "selected" count indicator, and a "Update Status" dropdown + apply button that POSTs a batch status change for all checked applications.

## Goal

- Users can select multiple applications in the dashboard and update their workflow status in a single action.
- Reduces friction for users managing 20+ active applications.

## Acceptance criteria

- AC-1: The Application Status Dashboard (`/jobhunter/applications`) renders a checkbox on each application row when the user is authenticated.
- AC-2: A "Select all" checkbox in the table header selects/deselects all visible application rows.
- AC-3: A status bar below (or above) the table shows the count of selected items ("3 selected").
- AC-4: A "Bulk update status" control (dropdown + "Apply" button) is visible when ≥1 application is selected. The dropdown contains the same status values as the single-application status field.
- AC-5: Submitting the bulk update POSTs to `job_hunter.applications_bulk_update` (POST-only, CSRF-guarded). On success: page reloads with the selected applications updated to the new status; a confirmation message shows "Updated N applications to [status]."
- AC-6: Server-side validation: all submitted `job_id` values must belong to the current user (`uid`). Any attempt to update another user's applications → rejected silently (the other user's records unchanged; no error exposed to attacker).
- AC-7: Empty selection → "Apply" is disabled (client-side) and POST with empty selection → 400 (server-side).
- AC-8: CSRF absent on POST → 403.

## Non-goals

- Bulk delete (deferred — deletion requires separate confirmation flow).
- Filtering/search before bulk selection.
- Exporting selected applications.

## Security acceptance criteria

- Authentication/permission surface: Bulk update route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`. Anonymous → 403.
- CSRF expectations: `job_hunter.applications_bulk_update` is POST-only with `_csrf_token: 'TRUE'`. GET is not supported for this route.
- Input validation: `job_id` array values must each be integers and must each belong to `uid == current_user->id()`. Silently skip or reject IDs that fail this check. Status value must be one of the valid workflow statuses (server-side whitelist).
- PII/logging constraints: Do not log application IDs or status values to watchdog at info level. Log only errors (unexpected failures, DB errors) at error/warning level.

## Implementation notes (to be authored by dev-forseti)

- New route `job_hunter.applications_bulk_update` (POST, CSRF) added to `job_hunter.routing.yml`.
- Controller method `ApplicationController::bulkUpdateStatus()` (or existing `ApplicationSubmissionController` if it owns the dashboard display).
- Dashboard template (`jobhunter-applications.html.twig`) updated with checkboxes and bulk control bar.
- DB update: `UPDATE {jobhunter_applications} SET status = :status WHERE id IN (:ids[]) AND uid = :uid`.
- Use Drupal's `db_in()` or query builder placeholders for the IN clause.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous → `/jobhunter/applications` returns 403 (existing behavior, regression check).
- TC-2: Dashboard renders checkbox on each application row for authenticated user.
- TC-3: "Select all" checkbox selects all rows; deselect works.
- TC-4: Bulk status update — select 2 apps, change status, POST (valid CSRF) → success message, both apps show new status on reload.
- TC-5: CSRF absent on POST → 403.
- TC-6: Empty selection POST → 400.
- TC-7: Cross-user attack — POST with another user's job_id → that record unchanged; no error exposed.
- TC-8: Invalid status value in POST → rejected (400 or silently ignored + error message).

## Journal

- 2026-04-11: Feature created by pm-forseti for release-g scope activation.
