- Status: done
- Completed: 2026-04-13T01:20:10Z

# Suite Activation: forseti-jobhunter-follow-up-reminders

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-13T00:41:07+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-follow-up-reminders"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-follow-up-reminders/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-follow-up-reminders-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-follow-up-reminders",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-follow-up-reminders"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-follow-up-reminders-<route-slug>",
     "feature_id": "forseti-jobhunter-follow-up-reminders",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-follow-up-reminders",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-follow-up-reminders

- Feature: forseti-jobhunter-follow-up-reminders
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one saved job for the test user
- Reminder storage exists (`jobhunter_follow_ups` table or `follow_up_date` column)

## Test cases

### TC-1: Follow-up field renders on saved-job detail

- **Type:** functional / smoke
- **When:** open saved-job detail as authenticated user
- **Then:** `Follow-up by` control is visible

---

### TC-2: Save follow-up date persists value

- **Type:** functional / happy path
- **When:** save a follow-up date via AJAX POST
- **Then:** value persists in DB for the current user and saved job

---

### TC-3: Overdue badge appears for past-due applied job

- **Type:** functional / business logic
- **When:** follow-up date is yesterday and status is `applied`
- **Then:** `/jobhunter/my-jobs` shows `follow-up-overdue`

---

### TC-4: Badge clears for future follow-up date

- **Type:** functional / business logic
- **When:** follow-up date is tomorrow
- **Then:** no overdue badge is shown

---

### TC-5: Badge clears after status progression

- **Type:** functional / business logic
- **When:** job status progresses to phone screen or beyond
- **Then:** no overdue badge is shown even if the old follow-up date is in the past

---

### TC-6: Clearing date removes reminder

- **Type:** functional / update path
- **When:** clear a previously saved follow-up date
- **Then:** stored value becomes NULL and no badge remains

---

### TC-7: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B loads job list with no reminder rows
- **Then:** no badges from user A are visible

---

### TC-8: Unauthenticated and CSRF failures blocked

- **Type:** security
- **When:** access without auth or POST without valid CSRF token
- **Then:** GET is blocked or redirected and POST returns HTTP 403

### Acceptance criteria (reference)

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
