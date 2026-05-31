# Implementation Notes: forseti-jobhunter-resume-version-labeling

- Feature: forseti-jobhunter-resume-version-labeling
- Author: ba-forseti
- Date: 2026-04-12
- Status: implemented (pre-existing; verified 2026-04-14)

## Approach

Extend the existing resume-management workflow with lightweight metadata fields
and a source-resume link on applications. This feature is the labeling-focused
precursor to the broader `resume-version-tracker` reporting slice.

## Schema changes

Add to `jobhunter_job_seeker_resumes`:
- `version_label` VARCHAR(120) NULL
- `version_notes` TEXT NULL

Add to `jobhunter_applications`:
- `source_resume_id` INT NULL

## UI surfaces

- Resume management page:
  - edit version label and notes inline
  - show `Used in N applications`
- Application submission flow:
  - persist selected source resume into `source_resume_id`

## Logic notes

- Duplicate labels are allowed, but should trigger a warning message rather than
  a hard validation failure.
- Keep label/notes storage plain text only.
- `source_resume_id` should reference base uploaded resumes only; the broader
  base-vs-tailored distinction belongs to `resume-version-tracker`.

## Relationship to resume-version-tracker

- `resume-version-labeling` establishes labels and the base-resume linkage.
- `resume-version-tracker` adds the explicit submitted-resume type and
  where-used reporting across base and tailored resumes.
- Shared helpers for resume selection/counts should be factored if both ship.

## AC coverage (dev verification 2026-04-14)

| AC | Location | Status |
|---|---|---|
| AC-1: Resume list shows version_label | `ResumeUploadSubform.php:217-219` — 🏷 badge; `"Edit label"` link | ✅ |
| AC-2: Edit form saves version_label + version_notes | `CompanyController::resumeVersionForm()` (line 4794) + `resumeVersionSave()` (line 4933) | ✅ |
| AC-3: Application detail shows linked resume label | `CompanyController` ~line 2054-2062 — "Resume submitted:" with `version_label` fallback | ✅ |
| AC-4: DB schema columns present | `SHOW COLUMNS FROM jobhunter_job_seeker_resumes LIKE 'version%'` → version_label varchar(128), version_notes mediumtext | ✅ |
| AC-5: source_resume_id saved on application | `resumeSourceSave()` line 5078 — sets both `submitted_resume_id` and legacy `source_resume_id` | ✅ |
| SEC-1: Auth required | Routes: `_permission: 'access job hunter'` + `_user_is_logged_in: 'TRUE'` | ✅ |
| SEC-2: CSRF on POST | `job_hunter.resume_version_save` → `_csrf_token: 'TRUE'` (POST-only split route) | ✅ |
| SEC-3: Ownership check | `resumeVersionSave()` checks job_seeker_id ownership; `resumeSourceSave()` checks uid ownership | ✅ |
| SEC-4: Input sanitization | `strip_tags()` + `substr(..., 0, 128)` in save; `htmlspecialchars()` in display | ✅ |
| SEC-5: No content in logs | `resumeVersionSave()` logs only uid + resume_id | ✅ |

## Deviations from spec

- `version_label` stored as `varchar(128)` not `varchar(120)` as noted in implementation notes above — matches AC-4 which specifies 128.
- Feature is combined with `resume-version-tracker` logic; `submitted_resume_id` + `submitted_resume_type` columns also exist on `jobhunter_applications` for typed resume linking.

## Verification targets

```bash
cd /var/www/html/forseti
vendor/bin/drush sql:query "SHOW COLUMNS FROM jobhunter_job_seeker_resumes LIKE 'version%'"
vendor/bin/drush sql:query "SHOW COLUMNS FROM jobhunter_applications LIKE 'source_resume_id'"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
