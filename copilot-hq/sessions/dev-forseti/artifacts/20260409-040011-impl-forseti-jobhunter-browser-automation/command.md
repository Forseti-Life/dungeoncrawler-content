# Implementation: forseti-jobhunter-browser-automation (test files only)

- Feature: forseti-jobhunter-browser-automation
- Release: 20260409-forseti-release-d
- Site: forseti.life
- Module: job_hunter
- Type: TEST-ONLY (all implementation shipped; this task creates the missing test files)
- PM owner: pm-forseti
- ROI: 40

## Context

`BrowserAutomationService` Phase 1 + Phase 2 is fully shipped (commits `240187db2`, `07bb74bf0`, `01cb73ea1`). The feature has a complete test plan at `features/forseti-jobhunter-browser-automation/03-test-plan.md` and AC at `features/forseti-jobhunter-browser-automation/01-acceptance-criteria.md`.

QA suite activation confirmed 3 suite entries already exist in `qa-suites/products/forseti/suite.json`:
- `forseti-jobhunter-browser-automation-unit` (TC-02/03/09/11/12)
- `forseti-jobhunter-browser-automation-e2e` (TC-01/06/07/08)
- `forseti-jobhunter-browser-automation-functional` (TC-04/05/10)

All 3 entries have `STAGE 0 PENDING` run notes pointing at specific test files that do not yet exist.

## Required deliverables

1. **`BrowserAutomationServiceTest.php`** — unit tests for:
   - Smart routing logic (supported ATS → Playwright strategy; unsupported → direct-apply)
   - Attempt-logging: record written with correct `job_seeker_id`, strategy, timestamp, status
   - `runPlaywrightBridge()` exception handling (must catch and return structured error, not fatal)

2. **`CredentialsControllerTest.php`** — unit/functional tests for:
   - Authenticated user: 200 on `/jobhunter/settings/credentials`
   - Anonymous user: 403 on `/jobhunter/settings/credentials`
   - CRUD: add credential entry, list, delete

3. **`testing/jobhunter-browser-automation.mjs`** — Playwright E2E smoke test for:
   - `runPlaywrightBridge()` callable without fatal error (may skip actual Playwright run if Node unavailable)
   - Attempt log record created even when bridge returns failure status

## Acceptance criteria

- AC-1: `BrowserAutomationServiceTest.php` exists and all included tests pass (`php vendor/bin/phpunit` exit 0)
- AC-2: `CredentialsControllerTest.php` exists; anon=403, authenticated=200 verified
- AC-3: `testing/jobhunter-browser-automation.mjs` exists and runs without Node fatal error
- AC-4: `php -l` passes on all new PHP files
- AC-5: No new `\Drupal::database()` or `$this->database` calls introduced

## KB references
- `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — credentials route uses `_permission: 'access job hunter'`
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — use `job_seeker_id` not UID

## Rollback
- Tests are additive-only; rollback = delete the 3 new test files. No schema/route changes.

## Cross-site sync
- Not applicable: browser automation tests are forseti-only.
