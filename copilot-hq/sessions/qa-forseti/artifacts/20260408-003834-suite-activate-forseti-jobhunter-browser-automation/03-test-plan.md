# Test Plan — forseti-jobhunter-browser-automation

- Feature: BrowserAutomationService Phase 1 + Phase 2 (smart routing, attempt logging, credentials UI, Playwright bridge)
- QA owner: qa-forseti
- Release target: 20260405-forseti-release-c (updated; originally 20260228-forseti-release-next)
- AC source: `features/forseti-jobhunter-browser-automation/01-acceptance-criteria.md`
- Date: 2026-04-05 (updated; originally 2026-02-28)
- Status: GROOMING (not yet activated in suite.json — activates at Stage 0 of next release)

## KB references
- `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — `/jobhunter/settings/credentials` uses `_permission: 'access job hunter'`; fixed in `14d891c51`; QA must verify anon=403, authenticated=200.
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — attempt logging must use `job_seeker_id` (profile PK), not raw Drupal UID, in run-history records.

---

## Test cases

### TC-01 — Smart routing: supported ATS → Playwright strategy
- **AC:** Happy Path — smart routing (supported ATS platform)
- **Suite:** `jobhunter-e2e` (Playwright)
- **Description:** Invoke `BrowserAutomationService` with a job context whose ATS platform is in the supported list. Assert the returned/logged strategy is `playwright`.
- **Expected behavior:** Strategy field = `playwright`; no PHP error or exception.
- **Roles covered:** authenticated
- **Notes:** Supported ATS platform list is in `BrowserAutomationService.php`. If list is injected/configurable, use a known-supported value in test fixture.

### TC-02 — Smart routing: unsupported ATS → direct-apply strategy
- **AC:** Happy Path — smart routing (unsupported ATS platform)
- **Suite:** `jobhunter-e2e` (Playwright) or Unit (`BrowserAutomationServiceTest.php`)
- **Description:** Invoke `BrowserAutomationService` with a job context whose ATS platform is NOT in the supported list. Assert strategy = `direct-apply`.
- **Expected behavior:** Strategy field = `direct-apply`; no PHP error.
- **Roles covered:** authenticated

### TC-03 — Attempt logging: run-history record written with correct fields
- **AC:** Happy Path — attempt logging
- **Suite:** Unit (`BrowserAutomationServiceTest.php`) + functional DB verification
- **Description:** After a routing decision, query the run-history DB table. Assert a record exists with: correct `job_seeker_id` (profile ID, not raw UID), `strategy` matching routing outcome, non-null `timestamp`, and a valid `status` value.
- **Expected behavior:** Row present with all four fields populated correctly.
- **Roles covered:** authenticated
- **KB note:** Must use `job_seeker_id` from `jobhunter_job_seeker.id`, NOT Drupal `uid`. Per KB `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.

### TC-04 — Credentials UI: authenticated user receives HTTP 200
- **AC:** Happy Path — credentials UI accessible
- **Suite:** `role-url-audit` (qa-permissions rule `credentials-ui`)
- **Description:** GET `/jobhunter/settings/credentials` as authenticated user.
- **Expected HTTP status:** 200
- **Roles covered:** authenticated
- **Permission rule:** `credentials-ui` in `qa-permissions.json` — `authenticated: allow`
- **KB note:** Fixed in `14d891c51`; if 403 is returned, check routing.yml `_permission` first.

### TC-05 — Credentials UI: anonymous user receives HTTP 403
- **AC:** Edge Case — anonymous user blocked from credentials route
- **Suite:** `role-url-audit` (qa-permissions rule `credentials-ui`)
- **Description:** GET `/jobhunter/settings/credentials` with no session.
- **Expected HTTP status:** 403 (redirect to login is acceptable; final destination must not be 200)
- **Roles covered:** anon
- **Permission rule:** `credentials-ui` in `qa-permissions.json` — `anon: deny`

### TC-06 — Credentials UI: add a credential entry and see it listed
- **AC:** Happy Path — credential add flow
- **Suite:** `jobhunter-e2e` (Playwright)
- **Description:** Authenticated user submits the add-credential form with a valid service/username. Reload the credentials list page and assert the new entry appears.
- **Expected behavior:** Entry visible in list after POST; HTTP 200 on list page.
- **Roles covered:** authenticated

### TC-07 — Credentials UI: delete a credential entry
- **AC:** Happy Path — credential delete flow
- **Suite:** `jobhunter-e2e` (Playwright)
- **Description:** Authenticated user triggers the delete action on an existing credential entry. Reload the list page and assert the entry is no longer present.
- **Expected behavior:** Entry absent from list; no PHP error; HTTP 200 on list page.
- **Roles covered:** authenticated

### TC-08 — Playwright bridge: runPlaywrightBridge() callable without fatal error
- **AC:** Happy Path — Playwright bridge smoke test
- **Suite:** `jobhunter-e2e` (Playwright) — integration smoke test
- **Description:** Invoke `runPlaywrightBridge()` with a valid job context object. Assert no PHP fatal error is thrown. Actual Playwright execution may be skipped if Node/Playwright is unavailable in CI; the callable boundary (no fatal) is the acceptance bar.
- **Expected behavior:** Method returns (success or structured error); no uncaught PHP exception; no HTTP 500.
- **Roles covered:** authenticated
- **CI note:** If Node is unavailable, mark test as skipped (not failed) and record in evidence.

### TC-09 — Attempt logging: record created when Playwright bridge fails/times out
- **AC:** Edge Case — logging is not conditional on bridge success
- **Suite:** Unit (`BrowserAutomationServiceTest.php`)
- **Description:** Mock `PlaywrightBridge` to return a failure/timeout status. Assert a run-history record is still written with `status = failure` (or equivalent non-success value). Verify `job_seeker_id` is still correct.
- **Expected behavior:** DB record present; strategy and job_seeker_id populated; status reflects the failure.
- **Roles covered:** authenticated (backend unit)

### TC-10 — Credentials UI: duplicate entry rejected without PHP error
- **AC:** Edge Case — duplicate credential deduplicated
- **Suite:** `jobhunter-e2e` (Playwright) or Functional (`CredentialsControllerTest.php`)
- **Description:** Submit the add-credential form twice with the same service/username combination. Assert the second submission either (a) returns a validation error or (b) silently deduplicates — and in either case no PHP error is thrown.
- **Expected behavior:** No PHP fatal; no duplicate row in DB (or deduplication confirmed).
- **Roles covered:** authenticated

### TC-11 — runPlaywrightBridge() exception caught, structured error returned
- **AC:** Failure Mode — exception handling
- **Suite:** Unit (`BrowserAutomationServiceTest.php`)
- **Description:** Mock `PlaywrightBridge` to throw an exception. Assert the caller (`BrowserAutomationService`) catches it and returns a structured error response (not propagating the exception to the caller or user-visible layer).
- **Expected behavior:** Structured error object/array returned; no uncaught exception; no HTTP 500 visible to end user.
- **Roles covered:** authenticated (backend unit)

### TC-12 — Run-history DB absent: attempt-logging fails gracefully
- **AC:** Failure Mode — schema absent
- **Suite:** Unit (`BrowserAutomationServiceTest.php`)
- **Description:** Simulate the run-history DB table being absent (uninstalled module). Invoke attempt logging and assert a logged error is emitted (watchdog/\Drupal::logger) rather than a PHP fatal or uncaught exception.
- **Expected behavior:** Error logged; no fatal crash; caller receives graceful failure.
- **Roles covered:** backend unit (no role context)
- **Notes:** Can be simulated via a mock DB or by temporarily dropping the schema in a test-only environment. Do NOT run on production or shared dev DB.

---

## AC items that cannot be expressed as automation

| AC item | Reason | Note to PM |
|---|---|---|
| Multi-user Playwright concurrency | Out of scope for Phase 1+2 per AC | Deferred to Phase 3 per AC — no test case needed now. |
| ATS platform auto-detection | Removed from scope (`d94a52bb4`) | No test case needed — feature was removed. |
| Full Playwright E2E against live ATS portals | Requires live external ATS access | Out of scope per AC; cannot be expressed as CI automation. PM to decide if manual exploratory testing is needed before release. |
| `runPlaywrightBridge()` when Node unavailable | Skipped, not failed (CI constraint) | TC-08 handles this as a conditional skip. PM should confirm whether CI must have Node installed for Gate 2, or skip is acceptable. |

---

## Suite assignment summary

| Suite | Test cases |
|---|---|
| `role-url-audit` | TC-04, TC-05 |
| `jobhunter-e2e` (Playwright) | TC-01 (or Unit), TC-02 (or Unit), TC-06, TC-07, TC-08 |
| Unit (`BrowserAutomationServiceTest.php`) | TC-02, TC-03, TC-09, TC-11, TC-12 |
| Functional (`CredentialsControllerTest.php`) | TC-04, TC-05, TC-10 (alternative to Playwright) |

Note: TC-01/TC-02 may be covered at Unit level; TC-04/TC-05 are covered by the `role-url-audit` suite via `qa-permissions.json` rule `credentials-ui` (already present in baseline).

---

## Activation checklist (for Stage 0 of next release)

- [ ] Add `BrowserAutomationServiceTest.php` unit test file and register it in the test suite.
- [ ] Add `CredentialsControllerTest.php` functional test file.
- [ ] Extend `jobhunter-e2e` Playwright suite with TC-06, TC-07, TC-08.
- [ ] Verify `credentials-ui` rule in `qa-permissions.json` is current (already present; confirm anon=deny, authenticated=allow).
- [ ] Edit `qa-suites/products/forseti/suite.json` to include new test files/coverage at Stage 0.
- [ ] Confirm Node + Playwright installed in CI if TC-08 is to be non-skipped.
