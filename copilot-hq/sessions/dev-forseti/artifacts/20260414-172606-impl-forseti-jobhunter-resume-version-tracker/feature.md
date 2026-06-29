# Feature Brief

- Work item id: forseti-jobhunter-resume-version-tracker
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-e
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

The `jobhunter_job_seeker_resumes` table already supports multiple resume files per user (with `resume_name` and `is_primary` flags). The `jobhunter_tailored_resumes` table stores AI-generated tailored resumes. However, there is no record of which resume version (base or tailored) was submitted to which application. This feature links a resume version to each application row in `jobhunter_applications` via a new `submitted_resume_id` column and `submitted_resume_type` (base/tailored), and surfaces this information on the application detail view. Users can also see a "where used" list on their resume detail page showing all jobs they submitted that version to.

## User story

As a job seeker who maintains multiple resume versions, I want to know which resume I submitted to each job so that I can reference the right version when preparing for interviews and track which version performs best.

## Non-goals

- Resume versioning system (creating/editing resumes — existing functionality)
- Automatic resume selection logic (separate AI feature)
- Resume performance analytics (follow-on to application-analytics feature)

## Acceptance criteria

### AC-1: Application detail shows which resume was submitted

Given an authenticated user who submitted an application with a specific resume, when they view the application detail (or the saved-job detail), then a "Resume submitted" section displays the resume name and type (base/tailored) that was linked to that application.

Verify: `drush sql:query "SELECT submitted_resume_id, submitted_resume_type FROM jobhunter_applications WHERE uid=<uid> AND job_id=<id>"` → returns non-null values for applications where a resume was linked.

### AC-2: User can set/update the submitted resume for an application

Given an authenticated user viewing an application detail, when they select a resume from their resume list (base resumes from `jobhunter_job_seeker_resumes` or tailored from `jobhunter_tailored_resumes`) and save, then `jobhunter_applications.submitted_resume_id` and `submitted_resume_type` are updated.

Verify: select a different resume; confirm DB column updates without page reload; `SELECT submitted_resume_id FROM jobhunter_applications WHERE id=<app_id>` → new resume id.

### AC-3: "Where used" list on resume detail page

Given an authenticated user viewing a resume (base or tailored), when the page renders, then a "Used in applications" section lists all jobs where this resume was submitted, with: job title, company, and application date.

Verify: page at `/jobhunter/resume/{resume_id}` (or equivalent) contains `resume-used-in` section; count matches `SELECT COUNT(*) FROM jobhunter_applications WHERE submitted_resume_id=<id> AND submitted_resume_type=<type> AND uid=<uid>`.

### AC-4: DB migration — submitted_resume_id and submitted_resume_type columns on jobhunter_applications

Given the module update hook has run, when querying the schema, then `jobhunter_applications` has columns: `submitted_resume_id` (int, nullable) and `submitted_resume_type` (varchar 16: base/tailored, nullable). Existing rows have NULL values for these columns (no migration of historical data required).

Verify: `drush sql:query "DESCRIBE jobhunter_applications"` → `submitted_resume_id` and `submitted_resume_type` columns present.

### AC-5: Resume selection is scoped to current user

Given a resume update request for application_id 100, when the controller processes the request, then it verifies that both the application and the referenced resume belong to `currentUser()->id()` before updating. A valid-session request referencing another user's resume returns HTTP 403.

Verify: POST with a `submitted_resume_id` owned by a different uid → HTTP 403; DB record unchanged.

## Security acceptance criteria

- All resume-tracking routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save route (split-route pattern)
- Controller verifies both `jobhunter_applications.uid` and the resume record's `job_seeker_id`/`uid` match `currentUser()->id()` before any update
- No resume content (full text) rendered in the "where used" list — show resume name and type only to minimize PII surface
- No PII (resume content, job titles) logged to watchdog at debug severity
