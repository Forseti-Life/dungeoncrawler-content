# Feature Brief

- Work item id: forseti-jobhunter-application-controller-split
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 2
- Group: application
- Group Title: Application Management
- Group Sort: 8
- Status: shipped
- Release: 20260409-forseti-release-d
- Feature type: refactor
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Source: Phase 2 of forseti-jobhunter-application-controller-db-extraction (PM-created stub 2026-04-09)

## Summary

Split `JobApplicationController.php` (4177 lines, currently DB-clean after release-c extraction) into two controllers:
- `ApplicationSubmissionController` — page renders and form handlers
- `ApplicationActionController` — AJAX/action endpoints

Phase 1 (DB extraction) shipped in release-c (`cfd24e07e`). Phase 2 reduces the controller to manageable size and separates concerns so each controller can be independently tested and extended.

## Goal

- Reduce `JobApplicationController.php` from 4177 lines to < 800 lines per resulting controller file.
- No behavior changes — pure structural split. All public method signatures must remain identical (or callers updated in the same commit).
- Zero new `$this->database` calls introduced (AC from Phase 1 remains valid).

## Acceptance criteria

- AC-1: `JobApplicationController.php` no longer exists (or contains only a thin delegating stub if routing requires it).
- AC-2: Two new controller files: `ApplicationSubmissionController.php` (page renders) and `ApplicationActionController.php` (AJAX/action handlers).
- AC-3: All routes in `job_hunter.routing.yml` that previously pointed to `JobApplicationController::*` now point to the correct split controller.
- AC-4: `php -l` passes on all modified files.
- AC-5: Anonymous GET on auth-required routes still returns 403; smoke test `/jobhunter/jobs` returns 200 to anonymous.
- AC-6: No new `$this->database` calls (grep count = 0 in both new controllers).

## Non-goals

- Business logic extraction (service layer refactoring) — deferred to Phase 3.
- Test file creation — handled by QA as a follow-on.

## Security acceptance criteria

- Authentication/permission surface: All existing `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'` route constraints must be preserved after the split.
- CSRF expectations: Existing split-route pattern with `_csrf_token: TRUE` on POST-only entries must be preserved; no collapse of split routes during the controller rename.
- Input validation requirements: No change to existing input handling — structural split only.
- PII/logging constraints: No new logging added; existing watchdog calls must remain unchanged.

## Implementation notes (to be authored by dev-forseti)

- See `features/forseti-jobhunter-application-controller-db-extraction/02-implementation-notes.md` for the Phase 1 context (repository structure, constructor DI pattern).
- KB reference: `knowledgebase/lessons/20260406-controller-split-phase1-pattern.md` (if exists); otherwise document the pattern during implementation.

## Test plan (to be authored by qa-forseti)

- TC-1: Route smoke test — all routes return expected HTTP status codes (200/403) before and after split.
- TC-2: CSRF token present on all form/action pages that previously had it.
- TC-3: `php -l` on both new controller files exits 0.
- TC-4: Regression — `/jobhunter/my-jobs` 200 for authenticated, 403 for anonymous.

## Journal

- 2026-04-09: Feature stub created by pm-forseti as Phase 2 follow-on from forseti-jobhunter-application-controller-db-extraction.
