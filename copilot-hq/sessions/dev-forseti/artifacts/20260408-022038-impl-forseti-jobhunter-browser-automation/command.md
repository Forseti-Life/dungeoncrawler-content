# Implementation Task — forseti-jobhunter-browser-automation

- Release: 20260407-forseti-release-c
- Feature: forseti-jobhunter-browser-automation
- Priority: P1
- Dispatched by: pm-forseti

## Goal

Implement browser automation support in the Job Hunter module. This enables the application to interact with job board sites via headless browser automation (Playwright or equivalent). Includes credential UI for storing site-specific login credentials, and a controller for managing automation sessions.

## Reference files

- Feature brief: `features/forseti-jobhunter-browser-automation/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-browser-automation/01-acceptance-criteria.md`
- Implementation notes: `features/forseti-jobhunter-browser-automation/02-implementation-notes.md`
- Test plan: `features/forseti-jobhunter-browser-automation/03-test-plan.md`

## Definition of done

All acceptance criteria in `01-acceptance-criteria.md` met.
Credential UI route protected: anon=403, authenticated=allow (confirmed by `credentials-ui` permission rule in `qa-permissions.json`).
PHP syntax clean on all modified files.
Code committed with commit message referencing `forseti-jobhunter-browser-automation`.

## Security requirement

Credential storage must not expose stored credentials to anonymous users. The credential UI must require authentication.
