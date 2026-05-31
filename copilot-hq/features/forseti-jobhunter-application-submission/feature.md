# Feature Brief

- Work item id: forseti-jobhunter-application-submission
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 2
- Group: application
- Group Title: Application Management
- Group Sort: 1
- Status: shipped
- Release: 20260406-forseti-release-b
- Priority: P1
- Feature type: needs-testing
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260405-forseti-release-c
- Source: BA inventory JH-R4 (ROI 18)

## Goal

Formally track and test the 5-step Workday portal automation workflow shipped in commit `7dea91e8f` as `WorkdayWizardService` + `WorkdayPlaywrightRunner`. This feature was Phase 3 of browser automation but was never assigned a feature brief, AC document, or QA test plan. The existing implementation needs a clear definition-of-done and test coverage before it can be considered production-verified.

## Non-goals

- Rewriting or refactoring WorkdayWizardService (tracked separately if needed).
- Supporting ATS portals other than Workday (out of scope for this feature).
- Playwright environment provisioning (handled by infrastructure; assumed available in test env).

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| WorkdayWizardService — step advancement (`advanceStep()`) | `src/Service/WorkdayWizardService.php` | Full (no tests) |
| WorkdayWizardService — auto-advance single-session (`advanceWizardAutoSingleSession()`) | `src/Service/WorkdayWizardService.php` | Full (no tests) |
| Credential retrieval in Playwright context | `WorkdayWizardService` + `WorkdayPlaywrightRunner.php` | Full (no tests) |
| ATS unavailability / Playwright timeout handling | `WorkdayPlaywrightRunner.php` | Partial (no tests) |
| Submission confirmation signal back to application record | 5 `application_submission_*` routes | Partial (no tests) |

### Coverage determination

- **Feature type: needs-testing** — Implementation is shipped. Dev work limited to gap-filling (e.g., confirm timeout handling is complete). All AC items are `[TEST-ONLY]`.

### Test path guidance for QA

- `web/modules/custom/job_hunter/tests/src/Unit/` — unit tests for `advanceStep()` and `advanceWizardAutoSingleSession()` with mock Playwright runner.
- `web/modules/custom/job_hunter/tests/src/Functional/` — smoke test confirming routes are registered and respond correctly for authenticated users.

## Risks

- WorkdayWizardService has no prior QA coverage; test gaps could surface regressions at Gate 2.
- Playwright integration tests may require a special test environment; QA should flag if test plan requires live Playwright (out of scope for Gate 2 functional tests).

## Security acceptance criteria
- Authentication/permission surface: All 5 `application_submission_*` routes require authenticated user with `access job hunter` permission; anonymous → 403.
- CSRF expectations: Any POST routes under `application_submission_*` must use Drupal CSRF token; GET-only routes do not require token (split-route pattern per org CSRF policy).
- Input validation: Playwright runner inputs (ATS credentials, step parameters) must be validated before use; no raw user input passed to shell.
- PII/logging constraints: ATS credentials (username/password) must not be logged in Drupal watchdog or PHP error logs; credential retrieval via WorkdayWizardService only.

## Latest updates

- 2026-04-06: Scoped into release — suite activation sent to QA.

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-06: Scoped into release — suite activation sent to QA.

- 2026-04-05: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-04-05: Feature brief created from BA inventory JH-R4; WorkdayWizardService formally tracked for 20260402-forseti-release-b.
- See also: `forseti-jobhunter-browser-automation/02-implementation-notes.md` — update required to reference WorkdayWizardService as new code (flagged in JH-R4).
