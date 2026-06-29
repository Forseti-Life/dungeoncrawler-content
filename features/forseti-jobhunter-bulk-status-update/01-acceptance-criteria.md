# Acceptance Criteria: forseti-jobhunter-bulk-status-update

- Feature: forseti-jobhunter-bulk-status-update
- Module: job_hunter
- Author: pm-forseti
- Date: 2026-04-11

## Summary

Add bulk status update to the Application Status Dashboard: per-row checkboxes, "Select all", selected-count indicator, and a CSRF-guarded POST endpoint that updates all selected applications to a chosen status in one operation.

## Acceptance criteria

### AC-1: Checkbox rendering
- Application Status Dashboard (`/jobhunter/applications`) renders a checkbox column for each application row when the user is authenticated.

### AC-2: Select all
- A "Select all" checkbox in the table header selects all visible application checkboxes.
- Clicking it again deselects all.

### AC-3: Selected count indicator
- A status bar shows count of currently selected applications ("3 selected").
- Updates dynamically as checkboxes are toggled (client-side JS).

### AC-4: Bulk update control
- A dropdown and "Apply" button appear when ≥1 application is selected.
- Dropdown contains all valid workflow statuses (same set as single-application status field).
- "Apply" button is disabled when 0 applications are selected.

### AC-5: Successful bulk update
- POST to `job_hunter.applications_bulk_update` (CSRF-guarded) with selected IDs and target status.
- On success: redirect/reload to dashboard; confirmation message "Updated N applications to [status]."
- Selected applications show the new status in the refreshed list.

### AC-6: Ownership validation
- Server-side: all submitted `job_id` values verified against `uid == current_user->id()`.
- IDs belonging to other users are silently skipped (not updated; no error message exposed).

### AC-7: Empty selection rejected
- POST with empty `job_id` array → 400 with user-facing error message.
- "Apply" button disabled client-side when selection is empty.

### AC-8: CSRF guard
- POST without valid CSRF token → 403.
- POST-only route: GET request to bulk-update endpoint → 404/405.

### AC-9: Status whitelist
- Status value in POST must be one of the valid workflow statuses (server-side whitelist).
- Invalid status → 400 with user-facing error; no DB write.
