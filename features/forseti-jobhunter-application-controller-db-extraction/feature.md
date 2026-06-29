# Feature Brief

- Feature ID: forseti-jobhunter-application-controller-db-extraction
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 2
- Group: application
- Group Title: Application Management
- Group Sort: 7
- Status: shipped
- Priority: P2
- Release: 20260409-forseti-release-c
- Source: BA inventory JH-R2 (ROI 15)
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti

## Summary
Extract all 54 direct `$this->database` calls from `JobApplicationController.php` (4177 lines) into `ApplicationSubmissionService` or a new `ApplicationAttemptRepository`. Phase 1 focuses solely on the DB extraction; controller split is Phase 2.

## Problem
`JobApplicationController` contains 54 raw DB queries mixed with route/render logic. An `ApplicationSubmissionService.php` already exists but the controller bypasses it for most DB operations. Every change to submission logic risks cross-step regression.

## PM Decision (JH-R2 scope approval)
- Phase 1 scope: Move all 54 direct DB calls from `JobApplicationController.php` into `ApplicationSubmissionService` or a new `ApplicationAttemptRepository`.
- Phase 2 (future release): Split the controller file into `ApplicationSubmissionController` (page renders) + action/AJAX handlers.
- Phase 1 does NOT require the controller to drop below any line-count target — only DB calls must reach zero.

## Acceptance criteria
- AC-1: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php` → 0
- AC-2: `ApplicationSubmissionService` (or a new `ApplicationAttemptRepository`) contains all extracted DB logic with PHPDoc for each new public method
- AC-3: Controller injects the service/repository via DI (constructor injection, not `\Drupal::service()`)
- AC-4: `php -l` passes on all modified files
- AC-5: All `application_submission_step*` routes continue to render correctly (smoke test steps 1–5)
- AC-6: Site audit 0 failures post-implementation

## Security acceptance criteria
- Authentication/permission surface: No permission changes. All routes keep existing `_permission: 'access job hunter'` and CSRF tokens.
- Input validation: Extraction must NOT bypass existing input sanitization — ensure all parameters still pass through the same validation as before extraction.
- PII/logging constraints: No new logging of form field values (job seeker data).

## Rollback
`git revert` to previous `JobApplicationController.php`; remove any new repository file.

## Verification method
- `grep -c '\$this->database' JobApplicationController.php` → 0
- `php -l` on all modified files
- Smoke test: HTTP 200 on `/jobhunter/application-submission/{job_id}/submit-application` steps 1–5
- Site audit clean
