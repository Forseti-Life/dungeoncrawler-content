# Acceptance Criteria: forseti-jobhunter-resume-version-tracker

- Feature: forseti-jobhunter-resume-version-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Track which resume version was submitted with each application by adding
`submitted_resume_id` and `submitted_resume_type` to `jobhunter_applications`,
showing the selected resume on the application detail view, and surfacing a
`Used in applications` list on resume detail pages.

## Acceptance criteria

### AC-1: Application detail shows submitted resume

**Given** an authenticated user who linked a resume to an application,
**When** they view the application or saved-job detail,
**Then** a `Resume submitted` section displays the resume name and type
(`base` or `tailored`).

**Verify:** query `jobhunter_applications.submitted_resume_id` and
`submitted_resume_type` for the application row.

---

### AC-2: User can set/update submitted resume for an application

**Given** an authenticated user viewing an application detail,
**When** they choose a resume from their own resume inventory and save,
**Then** `submitted_resume_id` and `submitted_resume_type` update for that
application.

**Verify:** re-query the application row after save.

---

### AC-3: "Where used" list on resume detail page

**Given** a resume has been used in one or more applications,
**When** the resume detail page renders,
**Then** a `Used in applications` section lists jobs where that resume was used.

**Verify:** rendered count matches a DB count filtered by resume id/type and uid.

---

### AC-4: DB migration — new columns on jobhunter_applications

**Given** the update hook has run,
**When** querying the schema,
**Then** `jobhunter_applications` includes nullable
`submitted_resume_id` and `submitted_resume_type` columns.

**Verify:** `drush sql:query "DESCRIBE jobhunter_applications"`

---

### AC-5: Resume selection is scoped to current user

**Given** an update request references another user's resume,
**When** the controller validates ownership,
**Then** the request returns HTTP 403 and the application row is unchanged.

**Verify:** POST with чужой resume id returns 403; DB remains unchanged.

---

## Security acceptance criteria

### SEC-1: Authentication required

All resume-tracking routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on POST save route

Resume-link updates use split-route CSRF protection.

### SEC-3: Double ownership check

Controller validates ownership of both the application row and the referenced
resume row before updating.

### SEC-4: Minimized display surface

The `where used` view shows resume name/type and job metadata only, not full
resume text.

### SEC-5: No sensitive debug logging

Do not log resume content or job titles at debug severity for this feature.
