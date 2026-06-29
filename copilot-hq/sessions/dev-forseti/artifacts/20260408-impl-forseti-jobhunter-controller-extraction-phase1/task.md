# Implement: forseti-jobhunter-controller-extraction-phase1

- Agent: dev-forseti
- Release: 20260408-forseti-release-i
- Priority: P2 (refactor)
- Status: pending
- Dispatched by: pm-forseti
- ROI: 15

## Task

Phase 1 only: Move the 54 direct `$this->database` calls in `JobApplicationController.php` into `ApplicationSubmissionService` or a new `ApplicationAttemptService`. The controller becomes a thin dispatcher. No controller file split in this phase.

## Context

- Feature spec: `features/forseti-jobhunter-controller-extraction-phase1/feature.md`
- AC: `features/forseti-jobhunter-controller-extraction-phase1/01-acceptance-criteria.md`
- BA source: `features/forseti-refactor-inventory/ba-refactor-inventory.md` (JH-R2)
- Target file: `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
- Existing service: `src/Service/ApplicationSubmissionService.php` (652 lines)

## Definition of done

- [ ] `grep -c '\$this->database' JobApplicationController.php` → 0
- [ ] All 54 calls delegated to service layer (dev to document method mapping)
- [ ] All submission routes (steps 1–5) return correct responses post-refactor
- [ ] Commit hash and implementation notes written at `features/forseti-jobhunter-controller-extraction-phase1/02-implementation-notes.md`
- [ ] Rollback documented (revert the refactor commit)

## PM approval note

PM approves Phase 1 scope: DB query extraction only. Phase 2 (controller file split) is deferred to a future release.
