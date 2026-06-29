# Feature Brief

- Work item id: forseti-jobhunter-controller-refactor-phase2
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260408-forseti-release-c
- Priority: P2
- Feature type: refactor
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Source: BA inventory JH-R2 (ROI 15)

## Goal

Extract the 54 direct `$this->database` calls from `JobApplicationController` (4177 lines) into `ApplicationSubmissionService` or a new `ApplicationAttemptService`. The controller should become a thin dispatcher. This reduces collision risk, makes submission logic independently testable, and is Phase 1 of the god-object decomposition.

## Non-goals

- Splitting the controller file into multiple classes (Phase 2 — deferred).
- Changing application submission behavior, UI, or route structure.
- Touching `forseti-jobhunter-application-submission` AC scope.

## PM Decision

Phase 1 only: move DB queries to service layer. Keep all public route/method signatures unchanged so QA smoke tests remain valid. Controller → thin dispatcher.

## Acceptance Criteria

See: features/forseti-jobhunter-controller-refactor-phase2/01-acceptance-criteria.md

## Risks

- High blast radius: JobApplicationController routes cover steps 1–5 and multiple AJAX handlers. Any regression will break the core job application flow.
- Must not change external behavior.

## Latest updates
- 2026-04-08: Feature brief created from BA inventory JH-R2. Groomed for 20260408-forseti-release-c.
