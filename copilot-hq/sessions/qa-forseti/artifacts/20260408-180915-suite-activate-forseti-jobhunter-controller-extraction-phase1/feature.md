# Feature Brief: forseti-jobhunter-controller-extraction-phase1

- Work item id: forseti-jobhunter-controller-extraction-phase1
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260408-forseti-release-i
- Priority: P2
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- BA source: JH-R2 (features/forseti-refactor-inventory/ba-refactor-inventory.md)

## Goal

Phase 1 of `JobApplicationController` god-object refactor: move the 54 direct `$this->database` calls in `JobApplicationController.php` (4177 lines) into `ApplicationSubmissionService` or a new `ApplicationAttemptService`. The controller becomes a thin dispatcher after this phase.

## Scope (Phase 1 only)

- **In scope:** Move DB queries from `JobApplicationController` to `ApplicationSubmissionService`. No splitting of controller file in this phase.
- **Not in scope:** Phase 2 (controller file split into step-render + AJAX controllers). Phase 2 is a separate future feature.

## Background

`JobApplicationController` contains 4177 lines with 54 direct `$this->database` calls, full ATS detection, metadata management, and submission logging. `ApplicationSubmissionService.php` (652 lines) exists but the controller bypasses it. Every change risks regression across unrelated routes.

## Definition of Done

- `JobApplicationController.php` has zero direct `$this->database->` calls (all delegated to `ApplicationSubmissionService` or `ApplicationAttemptService`).
- All existing job application submission routes (steps 1–5) function identically (QA regression pass).
- No new methods added to controller — only delegation wiring.
- Commit hash + line-count delta recorded in implementation notes.

## Security acceptance criteria

- Authentication/permission surface: No new routes or permissions introduced. Existing `_permission: 'access job hunter'` on all submission routes unchanged.
- CSRF expectations: No routing changes in this feature. CSRF protection on existing routes unchanged.
- Input validation: No new input surfaces. Validation logic remains in controller or moves to service — behavior identical.
- PII/logging constraints: No new logging introduced. All existing Drupal watchdog/logging patterns preserved.

## Rollback

`git revert` the refactor commit. The service layer is additive — no schema changes.
