# Test Plan Design: forseti-csrf-fix

- Agent: qa-forseti
- Status: pending

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-05T16:01:09+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-csrf-fix/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-csrf-fix "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
