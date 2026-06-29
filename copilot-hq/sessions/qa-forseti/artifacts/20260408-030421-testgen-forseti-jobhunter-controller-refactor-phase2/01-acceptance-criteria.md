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
