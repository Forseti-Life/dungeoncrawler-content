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
