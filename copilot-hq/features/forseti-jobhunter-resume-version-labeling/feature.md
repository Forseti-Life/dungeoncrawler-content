# Feature Brief

- Work item id: forseti-jobhunter-resume-version-labeling
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 1
- Group: profile-resume
- Group Title: Profile & Resume
- Group Sort: 8
- Status: done
- Release: 20260412-forseti-release-l
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

The `jobhunter_job_seeker_resumes` table supports multiple uploaded resume files (`resume_name`, `is_primary`) but provides no way to label them meaningfully (e.g., "Software Engineer — Python focus," "Senior SRE variant") or to record which labeled version was sent to a specific application. The `jobhunter_applications` table stores the full tailored resume text (`tailored_resume_used`) but has no foreign key link back to the source resume file. This feature adds: (1) a `version_label` and `version_notes` field to `jobhunter_job_seeker_resumes`; (2) a `source_resume_id` FK column on `jobhunter_applications`; and (3) a UI to label resumes and view which applications used each version.

## User story

As a job seeker who maintains multiple resume variants for different roles, I want to label each resume and see which applications used which version so I can analyze which resume performs best and avoid accidentally submitting the wrong variant.

## Non-goals

- Automatic resume selection/recommendation (separate AI feature)
- Version-controlled diff view between resume variants (too complex for v1)
- PDF generation from a specific version (handled by existing tailoring flow)

## Acceptance criteria

### AC-1: Version label and notes fields on resume management view

Given an authenticated user managing their uploaded resumes (e.g., `/jobhunter/profile` or `/jobhunter/resumes`), when they view a resume entry, then a "Version label" text field (max 120 chars) and "Version notes" textarea (optional, max 500 chars) are visible and editable. Changes save via AJAX POST without page reload.

Verify: `drush sql:query "SELECT version_label, version_notes FROM jobhunter_job_seeker_resumes WHERE id=<id>"` → returns saved label and notes.

### AC-2: Source resume linked on application submission

Given the user selects a labeled resume before submitting an application, when the application is submitted, then `jobhunter_applications.source_resume_id` is set to the `jobhunter_job_seeker_resumes.id` of the selected resume.

Verify: `drush sql:query "SELECT source_resume_id FROM jobhunter_applications WHERE uid=<uid> AND job_id=<id>"` → non-null integer matching the selected resume row.

### AC-3: "Used in applications" count visible on resume management view

Given a resume version has been used in 3 applications, when the user views the resume management page, then that resume entry shows "Used in 3 applications" with a link to filter the application list by that resume version.

Verify: `SELECT COUNT(*) FROM jobhunter_applications WHERE source_resume_id=<resume_id>` → 3; page shows "Used in 3 applications."

### AC-4: DB schema changes

Given the module update hook has run:
- `jobhunter_job_seeker_resumes` has new columns: `version_label` (varchar 120, nullable) and `version_notes` (text, nullable)
- `jobhunter_applications` has new column: `source_resume_id` (int, nullable, FK-intent to `jobhunter_job_seeker_resumes.id`)

Verify:
```sql
DESCRIBE jobhunter_job_seeker_resumes;  -- version_label, version_notes present
DESCRIBE jobhunter_applications;         -- source_resume_id present
```

### AC-5: Label uniqueness per user (soft constraint)

Given a user tries to save a duplicate version label (same label string already exists for another of their resumes), when the form is submitted, then a warning message is shown ("You already have a resume labeled X — consider a more specific label"), but the save is NOT blocked (soft constraint only).

Verify: submit two resumes with the same label → both save successfully; warning message appears on second save.

## Security acceptance criteria

- All resume labeling routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save routes (split-route pattern)
- Controller must verify `jobhunter_job_seeker_resumes.job_seeker_id` belongs to `currentUser()->id()` before updating label/notes
- Version label and notes stored as plain text; Twig auto-escaping on display (no `|raw`)
- Resume content (extracted text) must NOT be included in any label-update log entry; log only `uid` and `resume_id`
