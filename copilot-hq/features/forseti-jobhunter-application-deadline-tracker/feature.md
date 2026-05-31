# Feature Brief

- Work item id: forseti-jobhunter-application-deadline-tracker
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 2
- Group: application
- Group Title: Application Management
- Group Sort: 5
- Status: done
- Release: 20260411-forseti-release-b
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: PM grooming 2026-04-11 (next-cycle roadmap — job application lifecycle management)

## Summary

Job applications have time-sensitive windows: application deadlines, follow-up dates, and interview scheduling windows. Currently there is no way to track these dates in the Job Hunter module. This feature adds a `deadline_date` and `follow_up_reminder_date` per job record, surfaces them in the application status dashboard with color-coded urgency (overdue = red, due within 3 days = amber, clear = none), and provides a sorted "Upcoming Deadlines" view. No email notifications in this scope — display only.

## Goal

- Users can set application deadline and follow-up reminder dates per saved job.
- The application status dashboard displays urgency indicators for time-sensitive applications.
- Users can view a dedicated "Upcoming Deadlines" list sorted by date ascending.

## Acceptance criteria

- AC-1: On the job detail page (`/jobhunter/job/{job_id}`), authenticated users with `access job hunter` can edit `deadline_date` and `follow_up_date` via a POST form. Anonymous access → 403.
- AC-2: `deadline_date` and `follow_up_date` are stored in the `jobhunter_saved_jobs` table as nullable `DATE` columns (or a new `jobhunter_job_dates` table if schema change is preferred — Dev decides, QA tests both paths).
- AC-3: The application status dashboard (`/jobhunter/status`) shows a date column with color-coded urgency: overdue → red, within 3 days → amber, set but not urgent → default text, not set → blank.
- AC-4: A new route `/jobhunter/deadlines` renders all jobs with a `deadline_date` set, sorted by `deadline_date` ascending, showing job title, company, deadline, and urgency indicator. Accessible to authenticated users only; anonymous → 403.
- AC-5: Clearing a date (submitting blank) stores NULL. Existing records without dates are unaffected (no migration needed if additive schema change).
- AC-6: Date forms are POST-only, CSRF-guarded (split-route pattern). GET route for the detail page has no CSRF.
- AC-7: `{job_id}` ownership enforced: users may only modify dates for their own jobs (`uid == current_user->id()`). Cross-user mutation → 403.

## Non-goals

- Email/push notifications for upcoming deadlines.
- Calendar integration.
- Bulk-set deadlines across multiple jobs.

## Security acceptance criteria

- Authentication/permission surface: All routes require `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`. Controller enforces `uid == current_user`.
- CSRF expectations: Date save is POST-only, split-route, with `_csrf_token: 'TRUE'`. GET view routes have no CSRF.
- Input validation requirements: `{job_id}` must be an integer. Date fields must be validated as valid date or NULL; invalid format → form error, not DB write.
- PII/logging constraints: Date values must not be written to watchdog. No job title/company data in log messages.

## Implementation notes

- Prefer adding nullable `deadline_date DATE NULL` and `follow_up_date DATE NULL` columns to `jobhunter_saved_jobs` via `hook_update_N` if feasible. If a separate table is cleaner, Dev decides and documents.
- Urgency calculation: PHP `\DateTime::diff()` comparison at render time — no cron, no cache.
- Pattern: follow `forseti-jobhunter-application-notes` for the POST form + ownership guard.
