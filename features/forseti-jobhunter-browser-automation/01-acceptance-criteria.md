# Acceptance Criteria — forseti-jobhunter-browser-automation

- Feature: BrowserAutomationService Phase 1 + Phase 2 (smart routing, attempt logging, credentials UI, Playwright bridge)
- Release target: 20260228-forseti-release-next
- PM owner: pm-forseti
- Date groomed: 2026-02-28

## Gap analysis reference

Feature type: `needs-testing` — All implementation is shipped. No Dev work required; all criteria are `[TEST-ONLY]`.

Gap analysis source: `features/forseti-jobhunter-browser-automation/feature.md` § Gap Analysis

## Knowledgebase check
- KB: `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — credentials UI route `/jobhunter/settings/credentials` uses `_permission: 'access job hunter'` (confirmed fixed in `14d891c51`); QA must verify authenticated=allow, anon=403.
- KB: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — attempt logging must use correct `job_seeker_id` (not raw UID) in run history records.

## Happy Path

- [ ] `[TEST-ONLY, REQ-ID: REQ-02.8]` Smart routing: a job application attempt with a supported ATS platform routes to the Playwright strategy; an unsupported platform routes to the direct-apply strategy.
- [ ] `[TEST-ONLY, REQ-ID: REQ-06.1]` Attempt logging: each routing decision writes a record to the run-history DB table with correct `job_seeker_id`, strategy used, timestamp, and status.
- [ ] `[TEST-ONLY, REQ-ID: REQ-08.6]` Credentials UI: authenticated user can access `/jobhunter/settings/credentials` (HTTP 200), add a credential entry, and see it listed.
- [ ] `[TEST-ONLY, REQ-ID: REQ-08.6]` Credentials UI: credential entry can be deleted via the delete action; it is removed from the list.
- [ ] `[TEST-ONLY, REQ-ID: REQ-02.8]` Playwright bridge: `runPlaywrightBridge()` is callable without a PHP fatal error when invoked with a valid job context (integration smoke test; actual Playwright execution may be skipped in CI if Node unavailable).

## Edge Cases

- [ ] `[TEST-ONLY, REQ-ID: REQ-08.5]` Anonymous user accessing `/jobhunter/settings/credentials` receives HTTP 403.
- [ ] `[TEST-ONLY, REQ-ID: REQ-06.9]` Attempt log record is created even when the Playwright bridge returns a failure/timeout status (logging is not conditional on bridge success).
- [ ] `[TEST-ONLY, REQ-ID: REQ-08.6]` Duplicate credential entries for the same service/username are rejected or deduplicated without a PHP error.

## Failure Modes

- [ ] `[TEST-ONLY, REQ-ID: REQ-06.9]` If `runPlaywrightBridge()` throws, the calling code catches the exception and returns a structured error (no uncaught exception visible to the user).
- [ ] `[TEST-ONLY, REQ-ID: REQ-06.1]` If the run-history DB schema is absent (uninstalled module), the attempt-logging call fails gracefully with a logged error rather than a fatal crash.

## Out of scope for this AC (defer to Phase 3)
- Multi-user Playwright concurrency
- ATS platform auto-detection (removed)
- Full Playwright E2E automation against live external ATS portals
