# Suite Activation: forseti-csrf-fix

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-06T02:42:45+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-csrf-fix"`**  
   This links the test to the living requirements doc at `features/forseti-csrf-fix/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-csrf-fix-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-csrf-fix",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-csrf-fix"`**  
   Example:
   ```json
   {
     "id": "forseti-csrf-fix-<route-slug>",
     "feature_id": "forseti-csrf-fix",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-csrf-fix",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-csrf-fix

- Feature: CSRF protection for 7 application-submission POST routes
- QA owner: qa-forseti
- Release target: 20260405-forseti-release-c
- Date written: 2026-04-05
- KB references:
  - `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md`
  - Split-route CSRF pattern: separate GET-only + POST-only entries; POST gets `_csrf_token: TRUE`. Twig `path()` auto-appends `?token=` for CSRF-protected routes.

## Notes to PM

**Implementation observation (grooming-time scan):** All 7 `*_post` and `*_short_post` route variants in `job_hunter.routing.yml` already carry `_csrf_token: TRUE` as of scan date. The AC `enumerate_post_routes.py` static check should pass immediately. Gate 2 verification will confirm this against the release commit.

**Automation note:** Test cases TC-01 through TC-03 are executable as a PHP `KernelTest` or `BrowserTest` — no Playwright required. The `role-url-audit` suite covers TC-04/TC-05 only for GET probes; POST probes require a dedicated script because the audit suite uses GET requests only.

**Automation gap flagged:** `role-url-audit` cannot issue authenticated POST requests with a CSRF token directly — this requires either a PHP functional test (preferred) or a curl-based script. TC-02 and TC-03 MUST be covered by `CsrfApplicationSubmissionTest.php` (per AC QA definition). No automation gap for TC-01 (static YAML check) or TC-04/TC-05 (anon behavior already covered by GET-only `application-submission-short` rule in `qa-permissions.json`).

---

## Test Cases

### TC-01 — Static routing YAML check
- **Suite:** script (`python3 enumerate_post_routes.py job_hunter.routing.yml`)
- **AC item:** `enumerate_post_routes.py` outputs zero `CSRF=NO` lines for `access job hunter` POST routes
- **Steps:**
  1. From Drupal module root: `python3 enumerate_post_routes.py web/modules/custom/job_hunter/job_hunter.routing.yml`
- **Expected:** No `CSRF=NO` lines in output for routes with `_permission: 'access job hunter'`; all 7 POST routes (`step3_post`, `step3_short_post`, `step4_post`, `step4_short_post`, `step5_post`, `step5_short_post`, `step_stub_short_post`) show `CSRF=YES`
- **Roles covered:** N/A (static check)
- **Automated:** Yes — single script invocation

### TC-02 — Authenticated POST with valid CSRF token returns 200
- **Suite:** PHP functional test (`CsrfApplicationSubmissionTest.php`)
- **AC items:** Happy path — authenticated form submission succeeds; Data integrity — job records written correctly
- **Routes to cover:** at minimum `step3_post` and `step5_post` (representative of full and short variants per AC)
- **Steps:**
  1. Authenticate as user with `access job hunter` permission
  2. Obtain CSRF token via `/session/token`
  3. POST to `/jobhunter/application-submission/{job_id}/identify-auth-path` with `X-CSRF-Token` header
  4. POST to `/jobhunter/application-submission/{job_id}/submit-application` with `X-CSRF-Token` header
- **Expected:** HTTP 200 at each step; no PHP errors
- **Roles covered:** authenticated
- **Automated:** Yes — PHP BrowserTest / KernelTest

### TC-03 — Authenticated POST without CSRF token returns 403
- **Suite:** PHP functional test (`CsrfApplicationSubmissionTest.php`)
- **AC items:** Failure mode — cross-origin POST without valid CSRF token; Edge case — bare POST returns 403
- **Routes to cover:** at minimum `step3_post` and `step5_post`
- **Steps:**
  1. Authenticate as user with `access job hunter` permission
  2. POST to each route WITHOUT `X-CSRF-Token` header (or with invalid token)
- **Expected:** HTTP 403 for each route
- **Roles covered:** authenticated
- **Automated:** Yes — PHP BrowserTest / KernelTest

### TC-04 — Anonymous POST to any of the 7 routes returns 403
- **Suite:** PHP functional test (`CsrfApplicationSubmissionTest.php`) or curl script
- **AC item:** Failure mode — unauthenticated POST returns 403 (unchanged from pre-fix behavior)
- **Steps:**
  1. Send unauthenticated POST to `/jobhunter/application-submission/{job_id}/submit-application`
- **Expected:** HTTP 403
- **Roles covered:** anon
- **Automated:** Yes — extends TC-03 test class

### TC-05 — GET routes on same paths are unaffected (no CSRF on GET)
- **Suite:** `role-url-audit` (existing `application-submission-short` rule covers short paths; existing `jobhunter-surface` rule covers long paths)
- **AC item:** Implicit — split-route pattern means GET-only routes do not require CSRF token
- **Steps:**
  1. Run `scripts/site-audit-run.sh forseti-life` with authenticated role cookie
  2. Confirm GET `/jobhunter/application-submission/{job_id}/identify-auth-path` returns 200 (not 403) for authenticated role
- **Expected:** HTTP 200 for authenticated, 403 for anon (per existing rules — no regression)
- **Roles covered:** anon, authenticated
- **Automated:** Yes — role-url-audit suite (already active)

### TC-06 — AJAX flow with X-CSRF-Token header works without double-rejection
- **Suite:** PHP functional test (`CsrfApplicationSubmissionTest.php`) — edge case extension
- **AC item:** Edge case — AJAX with `X-CSRF-Token` header from `/session/token` continues to work
- **Steps:**
  1. Authenticate user, obtain `/session/token` value
  2. POST with `X-CSRF-Token: <token>` and `X-Requested-With: XMLHttpRequest` headers
  3. Confirm no PHP errors in watchdog; HTTP 200
- **Expected:** HTTP 200; no double-rejection or PHP error
- **Roles covered:** authenticated
- **Automated:** Yes — PHP BrowserTest

### TC-07 — Rollback verification (manual / config check only)
- **Suite:** Manual / config verification
- **AC item:** Rollback path — reverting `_csrf_token: TRUE` lines + `drush cr` restores prior behavior
- **Steps:**
  1. Remove `_csrf_token: TRUE` from one POST route in a dev environment
  2. Run `drush cr`
  3. Confirm route accepts POST without CSRF token (HTTP 200 instead of 403)
  4. Restore the line
- **Expected:** Rollback is clean; no DB migration needed
- **Roles covered:** authenticated
- **Automated:** No — manual verification step; document in verification report
- **Note to PM:** This is explicitly manual. Risk is low given config-only change; not blocking for Gate 2.

---

## Suite activation plan (Stage 0, next release)

| Test case | Suite | Activation action |
|---|---|---|
| TC-01 | script | Add to `jobhunter-e2e` command sequence or separate script suite entry |
| TC-02, TC-03, TC-04, TC-06 | PHP functional test | Add `CsrfApplicationSubmissionTest.php` suite entry to `suite.json` |
| TC-05 | `role-url-audit` | `application-submission-short` rule already added in release preflight (commit `c0b01ac1`) |
| TC-07 | manual | Document expected behavior in verification report; no suite entry needed |

## Acceptance summary

All AC items are covered:
- Happy path: TC-02
- Edge cases (AJAX): TC-06
- Failure modes (no token, anon): TC-03, TC-04
- Permissions/access control: TC-04, TC-05
- Static YAML check: TC-01
- Data integrity: TC-02 (form submission end-to-end)
- Rollback: TC-07 (manual)

### Acceptance criteria (reference)

# Acceptance Criteria — forseti-csrf-fix

- Feature: CSRF protection for 7 application-submission POST routes
- Release target: 20260402-forseti-release-b
- PM owner: pm-forseti
- Date groomed: 2026-04-05
- Priority: P0 (security)

## Gap analysis reference

Feature type: `enhancement` — Routing config exists; `_csrf_token: TRUE` must be added to 7 routes.
All criteria are `[EXTEND]` (existing routing entry; extend with CSRF declaration).

## Knowledgebase check
- `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` — QA probes must include a valid Drupal session token when testing POST routes; bare POST from QA automation will return 403 even for legitimate scenarios once CSRF is enforced.
- Prior CSRF remediation: commit `694fc424f` (GAP-002 fix) is the implementation pattern to follow.

## Happy Path

- [ ] `[EXTEND]` Dev adds `_csrf_token: TRUE` to all 7 routes: `application_submission_step3`, `step3_short`, `step4`, `step4_short`, `step5`, `step5_short`, `step_stub_short`.
- [ ] `[EXTEND]` Authenticated user submitting the application form in-browser continues to receive HTTP 200 at each step (Drupal form token is included in the standard form submission flow).
- [ ] `[EXTEND]` Script `python3 enumerate_post_routes.py job_hunter.routing.yml` outputs no `CSRF=NO` lines for routes with `_permission: 'access job hunter'`.

## Edge Cases

- [ ] `[EXTEND]` Any route consumed via AJAX with `X-CSRF-Token` header (`/session/token`) continues to work without PHP errors or double-rejection.
- [ ] `[EXTEND]` Step 3 short form (application_submission_step3_short) posts correctly via authenticated AJAX flow.

## Failure Modes

- [ ] `[EXTEND]` A cross-origin POST to `/jobhunter/application-submission/{job_id}/submit-application` without a valid CSRF token returns HTTP 403.
- [ ] `[EXTEND]` An unauthenticated POST to any of the 7 routes returns 403 (unchanged from pre-fix behavior).

## Permissions / Access Control

- [ ] Anonymous user behavior: 403 on all 7 routes (no change).
- [ ] Authenticated user behavior: form submission succeeds with valid CSRF token; fails with 403 if token missing or invalid.
- [ ] Admin behavior: same as authenticated (admin uses same permission `access job hunter` for these routes).

## Data Integrity

- [ ] No data loss: job application records are written correctly for a complete multi-step submission that passes CSRF validation.
- [ ] Rollback path: reverting `_csrf_token: TRUE` lines in `job_hunter.routing.yml` + `drush cr` restores prior behavior with no DB migration needed.

## Dev definition of done
- [ ] All 7 routes show `_csrf_token: TRUE` in `job_hunter.routing.yml`.
- [ ] `python3 enumerate_post_routes.py job_hunter.routing.yml` — zero CSRF=NO results for `access job hunter` POST routes.
- [ ] QA functional test (see below) passes.

## QA test definition
- `web/modules/custom/job_hunter/tests/src/Functional/CsrfApplicationSubmissionTest.php` — new or extended test:
  - Authenticated POST with valid token: expect 200.
  - Authenticated POST without token: expect 403.
  - Cover at minimum routes: `step3`, `step5` (representative of short and full variants).
- Agent: qa-forseti
- Status: pending
