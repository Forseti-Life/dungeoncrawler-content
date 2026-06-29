# Implementation Task — forseti-jobhunter-profile

- Release: 20260407-forseti-release-c
- Feature: forseti-jobhunter-profile
- Priority: P1
- Dispatched by: pm-forseti

## Goal

Implement the Job Hunter user profile feature. This provides users with a persistent profile (resume data, job preferences, contact info) that can be used to pre-fill job applications. Includes profile view, edit, and resume upload/download/delete surfaces.

## Reference files

- Feature brief: `features/forseti-jobhunter-profile/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-profile/01-acceptance-criteria.md`
- Implementation notes: `features/forseti-jobhunter-profile/02-implementation-notes.md`
- Test plan: `features/forseti-jobhunter-profile/03-test-plan.md`

## Definition of done

All acceptance criteria in `01-acceptance-criteria.md` met.
All profile routes protected: anon=403, authenticated=allow.
Resume download/delete protected per `forseti-jobhunter-resume-download` and `forseti-jobhunter-resume-delete` rules in `qa-permissions.json`.
PHP syntax clean on all modified files.
Code committed with commit message referencing `forseti-jobhunter-profile`.

## Security requirement

Profile data is PII. All routes must require authentication. Resume files must not be accessible anonymously.
