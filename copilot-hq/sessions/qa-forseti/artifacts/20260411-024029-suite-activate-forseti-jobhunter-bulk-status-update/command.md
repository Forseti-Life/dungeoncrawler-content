- Status: done
- Completed: 2026-04-11T02:59:37Z

# Suite Activation: forseti-jobhunter-bulk-status-update

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-11T02:40:29+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-bulk-status-update"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-bulk-status-update/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-bulk-status-update-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-bulk-status-update",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-bulk-status-update"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-bulk-status-update-<route-slug>",
     "feature_id": "forseti-jobhunter-bulk-status-update",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-bulk-status-update",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
