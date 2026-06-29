# Test Plan Design: forseti-jobhunter-application-controller-db-extraction

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T00:49:49+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-application-controller-db-extraction/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-application-controller-db-extraction "<brief summary>"
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

# Acceptance Criteria — forseti-jobhunter-application-controller-db-extraction

- Feature ID: forseti-jobhunter-application-controller-db-extraction
- Module: job_hunter
- Written by: pm-forseti
- Release: 20260409-forseti-release-b

## Functional acceptance criteria

### AC-1 — DB calls removed from controller
- Precondition: None
- Action: Count `$this->database` calls in `JobApplicationController.php`
- Expected: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php` returns 0
- Verification: Static grep

### AC-2 — Service/repository contains extracted DB logic
- Precondition: AC-1 passing
- Action: Inspect `ApplicationSubmissionService.php` or new `ApplicationAttemptRepository.php`
- Expected: All 54 formerly-inline queries are present; each new/modified public method has PHPDoc doc block
- Verification: Code review of target file(s)

### AC-3 — DI injection (no static service calls)
- Precondition: AC-1 passing
- Action: Inspect `JobApplicationController` constructor
- Expected: Service/repository is injected via constructor; no `\Drupal::service()` or `\Drupal::database()` calls added
- Verification: `grep -n '\\Drupal::' JobApplicationController.php` — must not introduce new static calls

### AC-4 — PHP lint passes
- Precondition: Implementation complete
- Action: `php -l` on all modified files
- Expected: "No syntax errors detected" on each file
- Verification: `php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`

### AC-5 — Application submission routes render correctly
- Precondition: Code deployed to dev
- Action: Visit `/jobhunter/application-submission/{job_id}/submit-application` steps 1–5 as authenticated user with `access job hunter` permission
- Expected: Each step renders HTTP 200 with no PHP notices or errors
- Verification: Manual smoke test + watchdog log check post-visit

### AC-6 — Site audit clean
- Precondition: Implementation complete
- Action: Run site audit
- Expected: 0 failures, 0 new violations
- Verification: Site audit tool output

## Out of scope (Phase 2)
- Splitting `JobApplicationController.php` into separate files (scheduled for a future release)
- Reducing controller line count below any target
- Modifying CSRF token configuration on existing routes
