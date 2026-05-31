# Feature Brief

- Work item id: forseti-jobhunter-follow-up-reminders
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 5
- Group: interviews-offers
- Group Title: Interviews, Offers & Career
- Group Sort: 4
- Status: shipped
- Release: 20260412-forseti-release-h
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO feature brief request 2026-04-12

## Summary

Job seekers frequently lose track of applications that need a follow-up (e.g., "I applied 2 weeks ago and haven't heard back"). This feature adds per-saved-job follow-up reminders: the user sets a follow-up date on a saved job (either from the detail view or from `application_notes`), and `/jobhunter/my-jobs` visually flags overdue follow-ups inline — a banner or badge showing the job cards where the follow-up date has passed and no status change has occurred. The flag is a passive UI indicator; no email or push notification is required (in-app only). Data is stored as a `follow_up_date` column added to `jobhunter_saved_jobs` or as a new `jobhunter_follow_ups` table keyed by `(uid, saved_job_id)` — implementation decision is Dev-owned.

## User story

As a job seeker tracking 20+ applications, I want to mark jobs that need a follow-up by a specific date so that my job list automatically highlights overdue follow-ups when I check in, helping me stay on top of each opportunity without manually scanning my notes.

## Non-goals

- Email or push notification reminders (separate notification feature)
- Recurring reminders or snooze functionality
- Reminder sharing or delegation
- Calendar export (separate feature)

## Acceptance criteria

### AC-1: Follow-up date field on saved-job detail view

Given an authenticated user viewing a saved job, when they open the detail panel, then a "Follow-up by" date picker field is visible. Setting and saving a date persists it via CSRF-protected AJAX POST.

Verify: `drush sql:query "SELECT follow_up_date FROM jobhunter_follow_ups WHERE uid=<uid> AND saved_job_id=<id>"` (or equivalent column on `jobhunter_saved_jobs`) → returns the saved date.

### AC-2: Overdue follow-up badge appears on /jobhunter/my-jobs

Given a saved job has a `follow_up_date` that is before today's date AND the job status is still "applied" (no status progression), when the user loads `/jobhunter/my-jobs`, then that job card displays a visible "Follow up overdue" badge or inline banner.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'follow-up-overdue'`

### AC-3: Badge clears when follow-up date is in the future or status has progressed

Given a saved job has a follow-up date set to tomorrow, OR the job's status has been updated to "phone screen" or beyond, when the user loads `/jobhunter/my-jobs`, then no overdue badge is shown for that card.

Verify: set follow-up date = tomorrow; reload page → no `follow-up-overdue` class on that card. Update status to "phone screen"; reload → no badge.

### AC-4: Follow-up date can be cleared/removed

Given a saved job has a follow-up date set, when the user clears the date field and saves, then no badge is shown and the follow-up date value in the DB is NULL.

Verify: `drush sql:query "SELECT follow_up_date FROM jobhunter_follow_ups WHERE uid=<uid> AND saved_job_id=<id>"` → NULL.

### AC-5: Follow-up data is scoped to current user

Given user A has a follow-up reminder for saved_job_id 30 and user B has no follow-up for that job, when user B loads their job list, then user B sees no overdue badge for job 30.

Verify: `drush sql:query "SELECT COUNT(*) FROM jobhunter_follow_ups WHERE uid=<uid_B>"` → 0; user B's job list shows no overdue badges.

## Security acceptance criteria

- All follow-up routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save route (split-route pattern; no CSRF on GET)
- Controller must verify the `saved_job_id` belongs to `currentUser()->id()` before setting follow-up date
- Follow-up date field stored as a plain date value — no user-authored text, no XSS surface
- No PII logged to watchdog from follow-up operations; log only `uid` and `saved_job_id`
