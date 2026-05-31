# Feature Brief

- Work item id: forseti-jobhunter-controller-refactor-phase2
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 6
- Group: infrastructure
- Group Title: Technical Infrastructure
- Group Sort: 3
- Status: shipped
- Release: 20260408-forseti-release-b
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

## Security acceptance criteria
- Security AC exemption: pure DB-layer extraction refactor — no new routes, no new permissions, no new user input surfaces introduced. Existing route ACLs and CSRF protections are unchanged; verified in AC-1 and AC-3.

## Latest updates

- 2026-04-08: Scoped into release — suite activation sent to QA.

- 2026-04-08: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-08: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-04-08: Feature brief created from BA inventory JH-R2. Groomed for 20260408-forseti-release-c.
