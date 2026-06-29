# Test Plan: forseti-jobhunter-bulk-status-update

- Feature: forseti-jobhunter-bulk-status-update
- Module: job_hunter
- Author: pm-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-11

## Scope

Verify that the Application Status Dashboard renders bulk selection controls, that the bulk update endpoint correctly updates selected applications for the current user, enforces CSRF and ownership validation, and rejects invalid inputs.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/applications`
- Expected: `403` (regression: existing behavior unchanged).

### TC-2: Dashboard — checkboxes rendered
- Steps: Log in as user with applications; visit `/jobhunter/applications`.
- Expected: Each application row has a checkbox; "Select all" checkbox in header visible.

### TC-3: Bulk update — valid selection and status
- Steps: Select 2 applications; choose a valid status from dropdown; click Apply (valid CSRF).
- Expected: POST succeeds (200/redirect); confirmation message shows "Updated 2 applications to [status]"; both apps show new status on page reload.

### TC-4: CSRF absent → 403
- Steps: POST to `job_hunter.applications_bulk_update` without CSRF token.
- Expected: 403.

### TC-5: Empty selection POST → 400
- Steps: POST with empty `job_id` array.
- Expected: 400 with user-facing error message; no DB write.

### TC-6: Cross-user ownership enforcement
- Steps: POST with `job_id` values belonging to a different user account.
- Expected: Those records are NOT updated; no error message revealing the other user's data.

### TC-7: Invalid status value → rejected
- Steps: POST with `status=invalid_value`.
- Expected: 400 or user-facing error; no DB write; page stable.

### TC-8: Select all — toggle behavior
- Steps: Click "Select all"; verify all rows checked. Click again; verify all unchecked.
- Expected: All checkboxes toggle correctly; count indicator updates.
