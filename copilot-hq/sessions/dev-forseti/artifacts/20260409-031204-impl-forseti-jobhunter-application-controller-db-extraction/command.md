# Implement: forseti-jobhunter-application-controller-db-extraction

- Release: 20260409-forseti-release-c
- Feature: forseti-jobhunter-application-controller-db-extraction
- Priority: P2
- Module: job_hunter

## Summary
Extract all 54 direct `$this->database` calls from `JobApplicationController.php` (4177 lines) into `ApplicationSubmissionService` or a new `ApplicationAttemptRepository`. Phase 1 only — DB extraction, no controller split.

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

## Reference artifacts
- Feature brief: `features/forseti-jobhunter-application-controller-db-extraction/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-application-controller-db-extraction/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-application-controller-db-extraction/03-test-plan.md`

## Done when
Outbox `Status: done` with commit hash(es) and rollback steps. All 6 ACs verifiably met.
