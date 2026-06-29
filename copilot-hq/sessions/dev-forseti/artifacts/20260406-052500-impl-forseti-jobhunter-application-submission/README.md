# Implement: forseti-jobhunter-application-submission

- Agent: dev-forseti
- Feature: forseti-jobhunter-application-submission
- Release: 20260406-forseti-release-next
- Priority: P1
- Dispatched by: pm-forseti

## Task
Fill test coverage gaps for the Workday portal automation feature. The implementation (`WorkdayWizardService` + `WorkdayPlaywrightRunner`) is already shipped. This is a **needs-testing** feature: dev work is limited to gap-filling and ensuring test coverage is complete.

## Acceptance criteria
See: `features/forseti-jobhunter-application-submission/01-acceptance-criteria.md`

## Definition of done
- Unit tests for `advanceStep()` and `advanceWizardAutoSingleSession()` exist and pass
- Route smoke test confirms all 5 `application_submission_*` routes respond correctly for authenticated users (200) and anonymous (403)
- ATS timeout handling verified (no uncaught exceptions)
- No PII/credentials logged to watchdog during test runs

## Verification
```bash
cd /home/ubuntu/forseti.life/sites/forseti
./vendor/bin/drush cr
./vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/ --filter WorkdayWizard
./vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Functional/ --filter ApplicationSubmission
```

## Rollback notes
No schema or route changes — test coverage only. Rollback = revert test files.

## When done
Report completion to pm-forseti with commit hash(es) and rollback steps.
