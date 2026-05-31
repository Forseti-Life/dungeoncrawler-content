# Acceptance Criteria — forseti-jobhunter-profile-refactor

- Feature: forseti-jobhunter-profile-refactor
- Module: job_hunter
- BA source: JH-R3
- PM owner: pm-forseti

## KB references
- knowledgebase/lessons/ — CSRF split-route pattern documented from forseti-jobhunter-profile (release-c fix)

## Definition of Done

### AC-1: Education history extracted to subform
- `EducationHistorySubform` class exists at `src/Form/Subform/EducationHistorySubform.php`
- `UserProfileForm.php` delegates education history build/validate/submit to `EducationHistorySubform`
- Education history save/load works identically to pre-refactor (no data loss)

### AC-2: Resume upload extracted to subform
- `ResumeUploadSubform` class exists at `src/Form/Subform/ResumeUploadSubform.php`
- `UserProfileForm.php` delegates resume upload/delete management to `ResumeUploadSubform`
- Resume upload, display, and delete (via `deleteResumeFileSubmit`) function identically to pre-refactor

### AC-3: CSRF protections intact
- `job_hunter.delete_resume` route retains `methods: [POST]` and `_csrf_token: 'TRUE'` (must not be regressed)
- Verify: `grep -A3 "job_hunter.delete_resume" job_hunter.routing.yml` shows POST + CSRF token

### AC-4: UserProfileForm line count reduced
- `wc -l src/Form/UserProfileForm.php` result is less than the pre-refactor 7425 lines
- Extracted classes account for the difference

### AC-5: No behavior change
- Profile page renders without PHP errors
- Education history add/edit/delete persists correctly
- Resume upload, display, and delete work as before
- All existing `forseti-jobhunter-profile` QA test plan items still pass

### AC-6: PHP lint clean
- `php -l src/Form/UserProfileForm.php` exits 0
- `php -l src/Form/Subform/EducationHistorySubform.php` exits 0
- `php -l src/Form/Subform/ResumeUploadSubform.php` exits 0

## Verification method
```bash
# Confirm CSRF intact
grep -A5 "job_hunter.delete_resume" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# Confirm subform files exist
ls /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Form/Subform/

# Line count reduced
wc -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php

# PHP lint
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php
```

## Out of scope
- Extracting consolidated JSON sync or job preferences sections (Phase 2)
- Changing form routes, field names, or user-visible behavior
