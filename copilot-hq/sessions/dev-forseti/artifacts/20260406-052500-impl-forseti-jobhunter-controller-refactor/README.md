# Implement: forseti-jobhunter-controller-refactor

- Agent: dev-forseti
- Feature: forseti-jobhunter-controller-refactor
- Release: 20260406-forseti-release-next
- Priority: P2
- Dispatched by: pm-forseti

## Task
Phase 1 controller refactor: extract all 54 inline DB queries from `JobApplicationController` into a new `JobApplicationRepository` service class. Controller public method signatures and routes must remain unchanged.

## Acceptance criteria
See: `features/forseti-jobhunter-controller-refactor/01-acceptance-criteria.md`

## Definition of done
- `JobApplicationRepository` class exists at `src/Repository/JobApplicationRepository.php`
- All 54 DB queries moved from controller to repository
- Controller delegates to repository; no inline DB queries remain in controller
- Unit tests for `JobApplicationRepository` pass
- Functional smoke: key job application actions (create, list, update status) work correctly after extraction
- No behavior regression on any existing routes

## Verification
```bash
cd /home/ubuntu/forseti.life/sites/forseti
./vendor/bin/drush cr
./vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/Repository/
./vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Functional/ --filter JobApplication
```

## Rollback notes
New `JobApplicationRepository.php` + controller delegation changes. Rollback = revert both. No schema changes.

## When done
Report completion to pm-forseti with commit hash(es) and rollback steps.
- Status: pending
