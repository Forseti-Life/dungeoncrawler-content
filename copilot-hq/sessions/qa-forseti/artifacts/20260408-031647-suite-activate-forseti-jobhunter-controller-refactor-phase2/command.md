# Suite Activation: forseti-jobhunter-controller-refactor-phase2

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T03:16:47+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-controller-refactor-phase2"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-controller-refactor-phase2/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-controller-refactor-phase2-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-controller-refactor-phase2",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-controller-refactor-phase2"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-controller-refactor-phase2-<route-slug>",
     "feature_id": "forseti-jobhunter-controller-refactor-phase2",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-controller-refactor-phase2",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-jobhunter-controller-refactor-phase2

- Feature: forseti-jobhunter-controller-refactor-phase2
- Module: job_hunter
- QA owner: qa-forseti
- Status: groomed (next-release — do NOT activate in suite until Stage 0)
- KB references: precedent from forseti-ai-service-refactor (same DB extraction pattern); knowledgebase/lessons/ — none specific found

## Summary

Refactoring `JobApplicationController` to extract direct DB calls into `ApplicationSubmissionService` (or a new `ApplicationAttemptService`). This is a pure internal refactor — no routes, permissions, or UI change. QA focus is regression detection (existing flows must not break) and structural static checks (no DB calls remain in controller).

---

## Test Cases

### TC-01 — Static: Zero direct DB calls in JobApplicationController
- **Suite:** `unit` (static grep)
- **Command:** `grep -c '\$this->database' web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
- **Expected result:** `0`
- **Roles covered:** N/A (static)
- **AC:** AC-1

### TC-02 — Static: Service public methods with PHPDoc exist
- **Suite:** `unit` (static grep)
- **Command:** `grep -c 'public function' web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php`
- **Expected result:** `>= 6` (current baseline; increases after refactor)
- **Roles covered:** N/A (static)
- **AC:** AC-2
- **Note:** Each extracted DB query must have a corresponding public method with `/** ... */` PHPDoc. Manual inspection of PHPDoc coverage is required at verification time.

### TC-03 — Static: ApplicationSubmissionService registered in job_hunter.services.yml
- **Suite:** `unit` (static grep)
- **Command:** `grep -c 'ApplicationSubmissionService\|application_submission_service' web/modules/custom/job_hunter/job_hunter.services.yml`
- **Expected result:** `>= 2` (class + service id)
- **Roles covered:** N/A (static)
- **AC:** AC-2

### TC-04 — Static: PHP lint clean — JobApplicationController
- **Suite:** `unit` (lint)
- **Command:** `php -l web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
- **Expected result:** `No syntax errors detected`
- **Roles covered:** N/A (static)
- **AC:** AC-5

### TC-05 — Static: PHP lint clean — ApplicationSubmissionService
- **Suite:** `unit` (lint)
- **Command:** `php -l web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php`
- **Expected result:** `No syntax errors detected`
- **Roles covered:** N/A (static)
- **AC:** AC-5

### TC-06 — Smoke: Application submission step 1 renders (GET)
- **Suite:** `functional` (Playwright or curl)
- **URL:** `/jobhunter/application-submission`
- **Expected result:** HTTP 200 for authenticated user with `access job hunter` permission; HTTP 302/403 for anonymous
- **Roles covered:** authenticated job seeker, anonymous
- **AC:** AC-3
- **Deferred:** Playwright/Node absent; curl smoke against production requires `ALLOW_PROD_QA=1`

### TC-07 — Smoke: Application submission step 2 (job-specific) renders (GET)
- **Suite:** `functional` (Playwright or curl)
- **URL:** `/jobhunter/application-submission/{job_id}` (use a known test job_id)
- **Expected result:** HTTP 200 for authenticated job seeker; HTTP 403 for anonymous
- **Roles covered:** authenticated job seeker, anonymous
- **AC:** AC-3

### TC-08 — Smoke: POST flows step 3/4/5 complete without error
- **Suite:** `e2e` (Playwright)
- **Flow:** Fill and submit steps 3, 4, 5 via Playwright browser automation
- **Expected result:** Each POST returns HTTP 200 or expected redirect; no PHP errors in Drupal watchdog; no regression vs pre-refactor baseline
- **Roles covered:** authenticated job seeker
- **AC:** AC-3, AC-4
- **Deferred:** Playwright/Node absent; flagged as manual verification or post-infra-fix automation

### TC-09 — Regression: No new routes or permissions introduced
- **Suite:** `role-url-audit`
- **Command:** `grep -c 'job_hunter\.' web/modules/custom/job_hunter/job_hunter.routing.yml` (count must not increase); `grep -c "access job hunter\|administer job" web/modules/custom/job_hunter/job_hunter.routing.yml` (permission set must not change)
- **Expected result:** Route count and permission set unchanged vs pre-refactor baseline
- **Roles covered:** N/A (structural)
- **AC:** AC-4

### TC-10 — Regression: Existing application-submission QA tests still pass
- **Suite:** `functional` (re-run existing suite entries for forseti-jobhunter-application-submission)
- **Expected result:** All pre-existing PASS/FAIL outcomes unchanged
- **Roles covered:** authenticated job seeker, admin
- **AC:** AC-4
- **Note to PM:** Verify pre-existing suite entries for `forseti-jobhunter-application-submission` are still in suite.json at Stage 0 and not inadvertently removed.

---

## Automation gap flags (for PM)

| Gap | Impact | Recommendation |
|---|---|---|
| TC-08 POST E2E requires Playwright/Node | Medium — cannot auto-verify step 3/4/5 submission flows | pm-infra: install Node + Playwright before this feature's Gate 2; or accept manual smoke as risk mitigation |
| TC-06/TC-07 functional smoke requires live env or curl with auth cookies | Low — GET render can be verified with curl + session cookie | QA can perform manual curl check at Gate 2 if `ALLOW_PROD_QA=1` authorized |
| PHPDoc coverage (TC-02) is manual at verify time | Low — static grep only counts public functions, not PHPDoc presence | Dev should self-enforce PHPDoc on each extracted method per AC-2 wording |

---

## Suite mapping (activate at Stage 0)

| TC | Suite type | suite.json entry id (proposed) |
|---|---|---|
| TC-01 | unit | `forseti-jobhunter-controller-refactor-phase2-unit-db-calls` |
| TC-02 | unit | `forseti-jobhunter-controller-refactor-phase2-unit-service-methods` |
| TC-03 | unit | `forseti-jobhunter-controller-refactor-phase2-unit-services-yml` |
| TC-04 | unit | `forseti-jobhunter-controller-refactor-phase2-unit-lint-controller` |
| TC-05 | unit | `forseti-jobhunter-controller-refactor-phase2-unit-lint-service` |
| TC-06 | functional | `forseti-jobhunter-controller-refactor-phase2-functional-step1-render` |
| TC-07 | functional | `forseti-jobhunter-controller-refactor-phase2-functional-step2-render` |
| TC-08 | e2e | `forseti-jobhunter-controller-refactor-phase2-e2e-post-flows` |
| TC-09 | unit | `forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes` |
| TC-10 | functional | (re-run existing forseti-jobhunter-application-submission entries) |

### Acceptance criteria (reference)

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
