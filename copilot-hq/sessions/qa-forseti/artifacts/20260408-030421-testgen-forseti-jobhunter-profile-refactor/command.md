# Test Plan Design: forseti-jobhunter-profile-refactor

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T03:04:21+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-profile-refactor/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-profile-refactor "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
