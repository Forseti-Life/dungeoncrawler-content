# Feature Brief

- Work item id: forseti-csrf-fix
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260406-forseti-release-b
- Priority: P0
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260405-forseti-release-c
- Source: BA inventory JH-R1 (ROI 25)

## Goal

Add `_csrf_token: TRUE` to all 7 application-submission POST routes that were added after the GAP-002 CSRF remediation (`694fc424f`) and currently lack CSRF protection. These routes control external ATS job application submission; a CSRF attack could trigger unauthorized job submissions on behalf of a logged-in user.

## Non-goals

- Changing the business logic or routing structure of the submission workflow.
- Refactoring the controller or service layer (tracked separately as JH-R2).

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| CSRF token on `application_submission_step3` | `job_hunter.routing.yml` — currently absent | None |
| CSRF token on `application_submission_step3_short` | `job_hunter.routing.yml` — currently absent | None |
| CSRF token on `application_submission_step4` | `job_hunter.routing.yml` — currently absent | None |
| CSRF token on `application_submission_step4_short` | `job_hunter.routing.yml` — currently absent | None |
| CSRF token on `application_submission_step5` | `job_hunter.routing.yml` — currently absent | None |
| CSRF token on `application_submission_step5_short` | `job_hunter.routing.yml` — currently absent | None |
| CSRF token on `application_submission_step_stub_short` | `job_hunter.routing.yml` — currently absent | None |

### Coverage determination

- **Feature type: enhancement** — Routing config exists; the CSRF token line must be added to each route.

### Test path guidance for QA

- `web/modules/custom/job_hunter/tests/src/Functional/` — add or extend a CSRF functional test.
- Verify both: legitimate authenticated POST succeeds (with valid token); cross-origin POST without token receives 403.

## Risks

- If any of the 7 routes are consumed via AJAX with `X-CSRF-Token` header already set by Drupal JS tooling, confirm there is no double-rejection.
- Reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` for prior CSRF/QA-probe conflict pattern.

## Latest updates

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-06: Scoped into release — suite activation sent to QA.

- 2026-04-05: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-04-05: Feature brief created from BA inventory JH-R1; scoped into 20260402-forseti-release-b.

## Security acceptance criteria
- Authentication/permission surface: All 7 application-submission POST routes require authenticated user (`access job hunter` permission). Anonymous access must be denied.
- CSRF expectations: All 7 POST routes must have `_csrf_token: TRUE` in `job_hunter.routing.yml`. Drupal's CSRF system issues a token per-session; cross-origin POSTs without a valid token must receive 403. Twig `path()` auto-appends `?token=` for CSRF-protected routes.
- Input validation: All form input passes through existing Drupal Form API validation. No raw `$_POST` access.
- PII/logging constraints: Application submission data (resume content, personal details) must not be logged at DEBUG level. Watchdog entries for errors must not include full form payloads.
