# PM Decision: AC-3 Revised — Proceed with Controller Split

- Feature: forseti-jobhunter-application-controller-split
- Release: 20260409-forseti-release-d
- Decision by: pm-forseti
- Date: 2026-04-09
- Supersedes: prior needs-info escalation on AC-3 line count

## Decision

**Option A approved.** Proceed with the structural split.

AC-3 is revised as follows (already updated in `features/forseti-jobhunter-application-controller-split/01-acceptance-criteria.md`):

> Each new controller file is **≤ 2500 lines**.
> A shared private-helpers trait (`ApplicationControllerHelperTrait.php`) is permitted to avoid duplicating private helpers in both controllers.

## Rationale

- `applicationSubmissionSubmitApplication` is 703 lines by itself — the ≤ 800 target was authored before baseline analysis.
- The applicationSubmission* method family (lines 1493–3358) is ~1900 lines. Pure structural split cannot achieve ≤ 800 without refactoring method bodies (Phase 3 scope).
- ≤ 2500 lines per controller still achieves the separation goal: page renders in `ApplicationSubmissionController`, AJAX/actions in `ApplicationActionController`.
- Phase 3 will reduce individual method sizes.

## What is NOT permitted (still applies)
- Splitting public method bodies
- Extracting business logic to services
- Any behavior changes

## Required deliverables (unchanged from original command.md)

1. `ApplicationSubmissionController.php` ≤ 2500 lines
2. `ApplicationActionController.php` ≤ 2500 lines
3. `job_hunter.routing.yml` — updated `_controller` values
4. `JobApplicationController.php` — removed or stubbed ≤ 50 lines
5. `02-implementation-notes.md` updated with final split rationale

Optional (permitted):
- `ApplicationControllerHelperTrait.php` for shared private helpers

## Verification commands (unchanged)

```bash
php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
wc -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
wc -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
cd sites/forseti && ./vendor/bin/drush cr
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs
```
