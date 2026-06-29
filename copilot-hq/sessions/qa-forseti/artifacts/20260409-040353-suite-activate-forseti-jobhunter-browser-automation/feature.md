# Feature Brief

- Work item id: forseti-jobhunter-browser-automation
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260409-forseti-release-d
- Priority: P1
- Feature type: needs-testing
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti

## Goal

BrowserAutomationService (Phase 1 + Phase 2) enables intelligent job application routing via smart strategy selection (direct apply vs. Playwright-assisted), attempt logging, credential storage UI, DB schema for run history, and a Playwright bridge that executes ATS-specific flows.

Shipped code lives in `web/modules/custom/job_hunter/` across commits:
- Phase 1: `240187db2` — BrowserAutomationService smart routing + attempt logging
- Phase 2 scaffold: `07bb74bf0` — DB schema, credentials UI, Playwright bridge scaffold
- Phase 2 wired: `01cb73ea1` — runPlaywrightBridge() fully connected

## Non-goals

- Multi-user Playwright concurrency
- ATS platform auto-detection (removed in `d94a52bb4`)

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| Smart routing strategy selection | BrowserAutomationService.php | Full |
| Attempt logging | BrowserAutomationService.php | Full |
| Credentials UI | CredentialsController.php + /jobhunter/settings/credentials route | Full |
| DB schema (run history) | job_hunter.install / schema hook | Full |
| Playwright bridge scaffold | PlaywrightBridge.php | Full |
| runPlaywrightBridge() wired | BrowserAutomationService.php → PlaywrightBridge | Full |
| Automated test coverage | tests/src/ | None |

### Coverage determination

Feature type: needs-testing — Full implementation shipped; automated tests absent.

### Test path guidance for QA

| Requirement | Test file | Test type |
|---|---|---|
| Smart routing strategy selection | tests/src/Unit/Service/BrowserAutomationServiceTest.php | Unit |
| Attempt logging | tests/src/Unit/Service/BrowserAutomationServiceTest.php | Unit |
| Credentials UI 200 (authenticated) | tests/src/Functional/Controller/CredentialsControllerTest.php | Functional |
| Credentials UI 403 (anonymous) | tests/src/Functional/Controller/CredentialsControllerTest.php | Functional |
| Playwright bridge integration | Manual / Playwright E2E | Integration |

## Acceptance Criteria (link)

None yet — AC doc pending QA handoff triage.

## Risks

- No automated tests yet: regressions in routing logic will be invisible until Phase 3 QA pass.
- Playwright bridge is integration-dependent; test environment must have Node + Playwright installed.
- Credentials UI is authenticated-only — confirm ACL in qa-permissions.json before Gate 2.

## Latest updates

- 2026-04-08: Scoped into release — suite activation sent to QA.

- 2026-04-06: Scoped into release — suite activation sent to QA.

- 2026-04-05: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-03-27: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-02-28: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-02-28: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-02-27: Feature stub created post-shipping. Code confirmed on origin/main (`01cb73ea1`). AC + test-plan not yet written. Triaged as P1 for next release cycle.
- 2026-04-08: Deferred from 20260407-forseti-release-b — no dev implementation or Gate 2 APPROVE before release close.

## Security acceptance criteria
- Authentication/permission surface: Credentials UI and run history routes must be auth-required (authenticated user role minimum); anonymous access must return 403.
- CSRF expectations: All POST routes (credential save, run trigger) must use split-route pattern with `_csrf_token: TRUE` on POST-only route entry.
- Input validation: Job URL and ATS credential fields must be sanitized server-side before storage; no raw HTML in stored values.
- PII/logging constraints: Stored credentials (username/password) must not appear in Drupal watchdog or Apache access logs; log only attempt IDs and status codes.
