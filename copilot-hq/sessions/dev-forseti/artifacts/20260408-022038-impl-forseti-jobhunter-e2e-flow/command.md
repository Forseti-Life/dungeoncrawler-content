# Implementation Task — forseti-jobhunter-e2e-flow

- Release: 20260407-forseti-release-c
- Feature: forseti-jobhunter-e2e-flow
- Priority: P1
- Dispatched by: pm-forseti

## Goal

Implement the end-to-end Job Hunter application flow. This connects profile data, browser automation, and application submission into a coherent user-facing workflow: user selects a job, the system pre-fills application fields using the stored profile, submits via browser automation, and records the outcome.

## Reference files

- Feature brief: `features/forseti-jobhunter-e2e-flow/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-e2e-flow/01-acceptance-criteria.md`
- Implementation notes: `features/forseti-jobhunter-e2e-flow/02-implementation-notes.md`
- Test plan: `features/forseti-jobhunter-e2e-flow/03-test-plan.md`

## Definition of done

All acceptance criteria in `01-acceptance-criteria.md` met.
Job Hunter surface route (`/jobhunter`) protected: anon=403 per `jobhunter-surface` rule in `qa-permissions.json`.
All sub-routes of the flow protected — no anonymous access to any job application data or automation triggers.
PHP syntax clean on all modified files.
Code committed with commit message referencing `forseti-jobhunter-e2e-flow`.

## Dependencies

This feature depends on `forseti-jobhunter-profile` and `forseti-jobhunter-browser-automation`. Implement those first or coordinate parallel implementation carefully to avoid merge conflicts.
