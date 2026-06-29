# Feature Brief

- Work item id: forseti-jobhunter-controller-refactor
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260405-forseti-release-b
- Priority: P2
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Source: BA inventory JH-R2 (ROI 15)

## Goal

Begin extracting the `JobApplicationController` god object (4177 lines, 54 direct DB queries) into focused service classes. Phase 1 scope: extract the DB query layer into a `JobApplicationRepository` service, leaving controller routing and request handling in place. This reduces the defect surface, makes the module testable, and unblocks future extraction rounds.

## Non-goals

- Rewriting the full controller in one pass.
- Changing any URL routes or request/response contracts.
- Extracting business logic beyond the DB layer in this phase.

## PM Decision

Phase 1 extraction approved: `JobApplicationRepository` extracts DB queries only. Controller public method signatures unchanged. Subsequent phases (business logic extraction) deferred to future releases.

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| JobApplicationRepository service exists | N/A | None (new class) |
| All 54 DB queries moved to repository | `JobApplicationController.php` (inline) | None |
| Controller delegates to repository | N/A | None |
| No behavior regression in controller actions | N/A | None (test-only after refactor) |

### Coverage determination

- **Feature type: enhancement** — New service class; dev extraction work required.

### Test path guidance for QA

- `web/modules/custom/job_hunter/tests/src/Unit/Repository/` — unit tests for `JobApplicationRepository`.
- Functional smoke test: key job application actions (create, list, update status) still work correctly after extraction.

## Risks

- High file complexity (4177 lines); dev must scope tightly to DB-layer only to avoid scope creep.
- Risk of regression if any controller action is tested only via manual testing today.

## Security acceptance criteria
- Authentication/permission surface: No new routes introduced; existing controller auth surface unchanged. All existing routes retain their current permission requirements.
- CSRF expectations: No new routes; existing CSRF behavior unchanged. No split-route changes required.
- Input validation: DB layer extraction only — no new user input surfaces introduced. Repository methods receive the same validated inputs as the original controller.
- PII/logging constraints: No new logging introduced; DB query results (including user data) must not be written to watchdog during repository operations.

## Latest updates

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-04-05: Feature brief created from BA inventory JH-R2; PM approved Phase 1 extraction scope; scoped for 20260405-forseti-release-b.
