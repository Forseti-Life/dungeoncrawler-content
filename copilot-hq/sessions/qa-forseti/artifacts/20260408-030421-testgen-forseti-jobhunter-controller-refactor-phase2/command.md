# Test Plan Design: forseti-jobhunter-controller-refactor-phase2

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T03:04:21+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-controller-refactor-phase2/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-controller-refactor-phase2 "<brief summary>"
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

# Acceptance Criteria — forseti-jobhunter-controller-refactor-phase2

- Feature: forseti-jobhunter-controller-refactor-phase2
- Module: job_hunter
- BA source: JH-R2
- PM owner: pm-forseti

## KB references
- knowledgebase/lessons/ — none specific found; precedent from forseti-ai-service-refactor (similar DB extraction pattern)

## Definition of Done

### AC-1: Zero direct DB calls in JobApplicationController
- `grep -c '\$this->database' src/Controller/JobApplicationController.php` returns 0
- All database operations delegated to `ApplicationSubmissionService` or a new `ApplicationAttemptService`

### AC-2: Service layer covers extracted queries
- Each extracted query has a corresponding public method in `ApplicationSubmissionService` (or new service) with PHPDoc
- Services registered in `job_hunter.services.yml` with correct dependencies

### AC-3: Application submission routes still function correctly
- Steps 1–5 pages render without PHP errors
- POST flows for step3/step4/step5 complete without regression
- Verified by QA smoke test against `/jobhunter/application-submission`

### AC-4: No behavior change
- All existing QA test plan items for `forseti-jobhunter-application-submission` still pass
- No new routes, permissions, or UI elements introduced

### AC-5: PHP lint clean
- `php -l src/Controller/JobApplicationController.php` exits 0
- `php -l src/Service/ApplicationSubmissionService.php` exits 0

## Verification method
```bash
# Confirm 0 direct DB calls in controller
grep -c '\$this->database' /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php

# Confirm service methods exist
grep -c 'public function' /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php

# PHP lint
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
```

## Out of scope
- Splitting controller into multiple files (Phase 2)
- Changing route structure or UI
