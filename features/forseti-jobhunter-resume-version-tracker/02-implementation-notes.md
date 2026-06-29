# Implementation Notes: forseti-jobhunter-resume-version-tracker

- Feature: forseti-jobhunter-resume-version-tracker
- Author: dev-forseti
- Date: 2026-04-12
- Status: complete
- Commit: `5e6c16eed`

## KB references

- None found for this specific feature. SEC-3 double-ownership pattern reused from contact-referral-tracker (cb9c5e51b).

## Changes made

### job_hunter.install — hook 9057

Added `job_hunter_update_9057()`:
- Adds `submitted_resume_id` (int unsigned nullable) to `jobhunter_applications`
- Adds `submitted_resume_type` (varchar 16 nullable) to `jobhunter_applications`
- Migrates existing `source_resume_id` rows: sets `submitted_resume_id = source_resume_id`, `submitted_resume_type = 'base'` where `source_resume_id IS NOT NULL`
- Idempotent (fieldExists guards before addField)

### CompanyController.php — viewJob() resume section (AC-1, AC-2, AC-5)

Rewrote the resume section:
- Reads `submitted_resume_id` + `submitted_resume_type` from the application row; falls back to `source_resume_id` for migration compatibility
- Shows "Resume submitted" header with type badge (base/tailored)
- Grouped `<optgroup>` dropdown: "Base Resumes" from `jobhunter_job_seeker_resumes`, "Tailored Resumes" from `jobhunter_tailored_resumes`
- JS sends `{submitted_resume_id, submitted_resume_type}` to `resumeSourceSave` endpoint
- Section title updated: "Resume Used" → "Resume Submitted"

### CompanyController.php — resumeVersionForm() (AC-3)

Added "Used in applications" section after the edit form:
- Queries `jobhunter_applications` filtered by `submitted_resume_id`, `submitted_resume_type = 'base'`, `uid`
- Inner query joins `jobhunter_job_requirements` + `jobhunter_companies` for job title and company name
- Renders table: Job Title | Company | Status | Submitted
- SEC-4: displays name/type/job metadata only — no resume content exposed

### CompanyController.php — resumeSourceSave() (AC-2, AC-5, SEC-3)

Fully rewritten:
- Accepts new `{submitted_resume_id, submitted_resume_type}` JSON body
- Also accepts legacy `{source_resume_id}` (treated as base; backward compat)
- Validates `submitted_resume_type` against allowlist `['base', 'tailored']`
- Base resume ownership: checks `jobhunter_job_seeker_resumes.job_seeker_id` via user's job_seeker record
- Tailored resume ownership: checks `jobhunter_tailored_resumes.uid` directly
- Application ownership: verifies `jobhunter_applications` row exists for (uid, job_id) before update
- Writes `submitted_resume_id`, `submitted_resume_type`, and syncs `source_resume_id` (null for tailored, resume id for base)
- SEC-5: logs only uid/job_id/resume_id/type — no content

## Security checklist

- [x] Authentication required: existing routes have `_user_is_logged_in: 'TRUE'`
- [x] CSRF: `job_hunter.resume_source_save` is POST-only with `_csrf_token: 'TRUE'` (existing split-route)
- [x] SEC-3 double ownership: application row + resume row both validated before update
- [x] SEC-4: "where used" renders only job title, company name, status, date
- [x] SEC-5: no resume content or job title logged at debug/info severity

## Verification commands

```bash
# AC-4: schema check
cd /var/www/html/forseti && vendor/bin/drush sql:query "DESCRIBE jobhunter_applications" | grep submitted

# AC-1/AC-2: post-save query
vendor/bin/drush sql:query "SELECT submitted_resume_id, submitted_resume_type FROM jobhunter_applications WHERE uid=<uid> LIMIT 5"

# AC-3: where-used count check
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_applications WHERE submitted_resume_id=<id> AND submitted_resume_type='base' AND uid=<uid>"
```

## Rollback plan

Revert commit `5e6c16eed`. The columns remain in the DB but the UI will stop reading them. To fully roll back DB:
```sql
ALTER TABLE jobhunter_applications DROP COLUMN submitted_resume_id, DROP COLUMN submitted_resume_type;
```
