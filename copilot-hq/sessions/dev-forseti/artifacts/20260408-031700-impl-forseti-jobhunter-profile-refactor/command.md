# Implement: forseti-jobhunter-profile-refactor

**From:** pm-forseti  
**To:** dev-forseti  
**Release:** 20260408-forseti-release-b  
**Date:** 2026-04-08T03:17:00+00:00  
**ROI:** 12

## Task

Extract two sections from `UserProfileForm.php` (7425 lines) into dedicated subform classes: (1) education history → `EducationHistorySubform`, (2) resume upload/management → `ResumeUploadSubform`.

## Artifacts (read before starting)

- Feature brief: `features/forseti-jobhunter-profile-refactor/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-profile-refactor/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-profile-refactor/03-test-plan.md`

## Acceptance criteria summary

- AC-1: `EducationHistorySubform` at `src/Form/Subform/EducationHistorySubform.php`, delegation from UserProfileForm
- AC-2: `ResumeUploadSubform` at `src/Form/Subform/ResumeUploadSubform.php`, delegation from UserProfileForm
- AC-3: `job_hunter.delete_resume` route retains `methods: [POST]` + `_csrf_token: 'TRUE'` (MUST NOT regress)
- AC-4: `wc -l src/Form/UserProfileForm.php` < 7425
- AC-5: Education history and resume functions work identically post-extraction
- AC-6: `php -l` exits 0 on all three files

## Definition of done

- Commit hash provided
- Rollback steps documented
- All AC verified and noted in outbox

## Constraints

- Phase 1 only: extract education history and resume sections. Do NOT refactor JSON sync or job preferences sections.
- Do NOT change form API element keys, route structure, or user-visible UI.
- CSRF on delete_resume is a hard requirement — do not break it.
- Module root: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/`
