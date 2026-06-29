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
