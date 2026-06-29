# Implement: forseti-jobhunter-controller-refactor-phase2

**From:** pm-forseti  
**To:** dev-forseti  
**Release:** 20260408-forseti-release-b  
**Date:** 2026-04-08T03:17:00+00:00  
**ROI:** 15

## Task

Extract all `$this->database` direct calls from `JobApplicationController.php` into `ApplicationSubmissionService` (or a new `ApplicationAttemptService`). The controller becomes a thin dispatcher.

## Artifacts (read before starting)

- Feature brief: `features/forseti-jobhunter-controller-refactor-phase2/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-controller-refactor-phase2/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-controller-refactor-phase2/03-test-plan.md`

## Acceptance criteria summary

- AC-1: `grep -c '\$this->database' src/Controller/JobApplicationController.php` returns 0
- AC-2: All extracted queries have corresponding public methods in service with PHPDoc; service registered in `job_hunter.services.yml`
- AC-3: Steps 1–5 routes render and POST flows complete without regression
- AC-4: No new routes, permissions, or UI elements
- AC-5: `php -l` exits 0 on both controller and service

## Definition of done

- Commit hash provided
- Rollback steps documented (revert the commit)
- All AC-1 through AC-5 verified and noted in outbox

## Constraints

- Phase 1 only: DB extraction. Do NOT split the controller class (Phase 2, deferred).
- Do NOT change external behavior, route structure, or form API keys.
- Module root: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/`
