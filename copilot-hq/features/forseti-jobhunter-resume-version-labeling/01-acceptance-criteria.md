# Acceptance Criteria: forseti-jobhunter-resume-version-labeling

- Feature: forseti-jobhunter-resume-version-labeling
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Enhancement to the existing resume management flow. Adds `version_label` (varchar 128, nullable) and `version_notes` (text, nullable) to `jobhunter_job_seeker_resumes`. Adds `source_resume_id` (int, nullable, soft-FK to `jobhunter_job_seeker_resumes.id`) to `jobhunter_applications` so users can track which stored resume version was submitted. Resume list shows labels and notes; application detail page shows the linked resume label.

## Acceptance criteria

### AC-1: Resume list displays version labels

**Given** a user has two resumes — one with `version_label='Software Engineer v3'` and one with no label,
**When** they visit `/jobhunter/resume/list` (or the existing resume management page),
**Then** the labeled resume shows "Software Engineer v3" next to its name; the unlabeled one shows no label or a default placeholder.

**Verify:** `SELECT version_label FROM jobhunter_job_seeker_resumes WHERE uid=<uid> AND resume_name='my_resume'` → `Software Engineer v3`

---

### AC-2: Version label and notes saved via resume edit form

**Given** an authenticated user editing an existing resume,
**When** they enter `version_label='Backend Focus v2'` and `version_notes='Emphasizes Go/Python; minimal frontend'` and submit,
**Then** the resume row is updated with those values.

**Verify:**
```sql
SELECT version_label, version_notes FROM jobhunter_job_seeker_resumes
WHERE uid=<uid> AND id=<resume_id>;
-- Expected: Backend Focus v2 | Emphasizes Go/Python; minimal frontend
```

---

### AC-3: Application detail page shows linked resume label

**Given** an application with `source_resume_id=7` pointing to a resume with `version_label='Tech Lead v1'`,
**When** the user views the application detail page,
**Then** a "Resume used" section appears showing "Tech Lead v1" and a link to the resume detail.

**Verify:** Application detail HTML contains `Tech Lead v1` when `source_resume_id=7`.

---

### AC-4: DB schema — columns added to existing tables

**Given** the module update hook has run,
**When** querying the schema,
**Then** `jobhunter_job_seeker_resumes` contains columns `version_label` (varchar 128, nullable) and `version_notes` (text, nullable); `jobhunter_applications` contains column `source_resume_id` (int, nullable).

**Verify:**
```bash
drush sql:query "SHOW COLUMNS FROM jobhunter_job_seeker_resumes LIKE 'version%'"
drush sql:query "SHOW COLUMNS FROM jobhunter_applications LIKE 'source_resume_id'"
```

---

### AC-5: Linking an application to a resume (save path)

**Given** user has resume id=5 and is creating/editing an application,
**When** they select resume id=5 from the "Resume used" dropdown and submit,
**Then** `source_resume_id=5` is stored on the application row; `tailored_resume_used` is unchanged.

**Verify:** `SELECT source_resume_id FROM jobhunter_applications WHERE id=<app_id> AND uid=<uid>` → 5

---

## Security acceptance criteria

### SEC-1: Authentication required
All resume edit and application detail routes already require auth; new fields must not bypass existing auth gates.

### SEC-2: CSRF protection on POST
Resume edit form POST follows existing CSRF pattern (split-route: POST-only `_csrf_token: 'TRUE'`).

### SEC-3: Ownership check on source_resume_id
When saving `source_resume_id`, the server verifies the referenced resume belongs to the current user: `SELECT id FROM jobhunter_job_seeker_resumes WHERE id=? AND uid=currentUser()->id()`. Reject with HTTP 403 if ownership fails.

### SEC-4: Input sanitization
`version_label` and `version_notes` stored as plain text (no HTML). Display uses Twig auto-escaping only (no `|raw`).

### SEC-5: No resume content in logs
`version_notes` and extracted resume text must NOT appear in watchdog logs. Log only `uid` and `resume_id`.
