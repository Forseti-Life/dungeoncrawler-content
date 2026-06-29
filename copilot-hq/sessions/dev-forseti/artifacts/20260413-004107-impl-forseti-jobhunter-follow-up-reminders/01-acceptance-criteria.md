# Acceptance Criteria: forseti-jobhunter-follow-up-reminders

- Feature: forseti-jobhunter-follow-up-reminders
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Add per-saved-job follow-up reminders so users can set a follow-up date and see
overdue reminders inline on `/jobhunter/my-jobs`. The feature is in-app only:
no email, push, or recurring reminder behavior is included.

## Acceptance criteria

### AC-1: Follow-up date field on saved-job detail view

**Given** an authenticated user viewing a saved job,
**When** they open the detail panel,
**Then** a `Follow-up by` date picker is visible and can be saved via
CSRF-protected AJAX POST.

**Verify:** query the chosen storage location for the saved date.

---

### AC-2: Overdue badge appears on /jobhunter/my-jobs

**Given** a saved job has a follow-up date before today and status remains
`applied`,
**When** the user loads `/jobhunter/my-jobs`,
**Then** that job card shows a visible overdue follow-up badge or banner.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'follow-up-overdue'`

---

### AC-3: Badge clears when no longer overdue

**Given** the follow-up date moves into the future or the job status progresses
beyond `applied`,
**When** the user reloads `/jobhunter/my-jobs`,
**Then** the overdue badge is no longer shown.

**Verify:** update the date/status and confirm the class/markup disappears.

---

### AC-4: Follow-up date can be cleared

**Given** a saved job already has a follow-up date,
**When** the user clears the value and saves,
**Then** the stored date becomes NULL and no overdue indicator remains.

**Verify:** storage row/column returns NULL.

---

### AC-5: Follow-up data is scoped to current user

**Given** user A has a follow-up reminder and user B does not,
**When** user B loads their job list,
**Then** user B sees no overdue indicator from user A's data.

**Verify:** user-B query returns 0 reminder rows and no badge renders.

---

## Security acceptance criteria

### SEC-1: Authentication required

All follow-up routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on POST save route

Use the split-route CSRF pattern on state-changing routes only.

### SEC-3: Ownership check on saved job

Controller must verify `saved_job_id` belongs to `currentUser()->id()` before
saving or clearing a follow-up date.

### SEC-4: No user-authored text surface

Store only a plain date value for this feature.

### SEC-5: Minimal logging

Do not log PII; log only `uid` and `saved_job_id` for save/clear events.
