# Implementation: forseti-jobhunter-application-controller-split (Phase 2 controller split)

- Feature: forseti-jobhunter-application-controller-split
- Release: 20260409-forseti-release-d
- Site: forseti.life
- Module: job_hunter
- Type: REFACTOR (pure structural split, no behavior changes)
- PM owner: pm-forseti
- ROI: 20

## Context

Phase 1 (DB extraction from `JobApplicationController.php`) shipped in release-c as `forseti-jobhunter-application-controller-db-extraction`. All 54 `$this->database` calls are now in service/repository classes.

Phase 2 splits the 4177-line `JobApplicationController.php` into two controllers:
- `ApplicationSubmissionController` — page renders and form handlers
- `ApplicationActionController` — AJAX/action endpoints

Full acceptance criteria: `features/forseti-jobhunter-application-controller-split/01-acceptance-criteria.md`
Test plan scaffold: `features/forseti-jobhunter-application-controller-split/03-test-plan.md`

## Required baseline (record before starting)

Before beginning the split, record these baselines in `features/forseti-jobhunter-application-controller-split/02-implementation-notes.md`:
1. `grep -c "_csrf_token" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
2. `wc -l sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
3. Full route list: `grep -A2 "job_hunter\." sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_controller"`

## Required deliverables

1. **`ApplicationSubmissionController.php`** at `web/modules/custom/job_hunter/src/Controller/`
   - Page render methods: job listing, search results, application form renders
   - ≤ 800 lines

2. **`ApplicationActionController.php`** at `web/modules/custom/job_hunter/src/Controller/`
   - AJAX handlers and form submission action methods
   - ≤ 800 lines

3. **`job_hunter.routing.yml`** — updated `_controller` values for all split methods

4. **`JobApplicationController.php`** — removed or stubbed to ≤ 50 lines if routing requires a transitional stub

5. **`02-implementation-notes.md`** in `features/forseti-jobhunter-application-controller-split/`:
   - Baseline counts (pre-split)
   - Split rationale for method assignment
   - Any gotchas encountered

## Constraints

- Zero new `$this->database` calls in either new controller
- All existing `_permission` and `_user_is_logged_in` route constraints must be preserved
- All existing CSRF split-route entries must be preserved exactly
- `drush cr` must succeed with no PHP fatal errors

## Verification commands

```bash
# Syntax
php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php

# No DB calls
grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php

# Line counts
wc -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
wc -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php

# Cache rebuild
cd sites/forseti && ./vendor/bin/drush cr

# Smoke test
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs
```

## Definition of done

- All AC in `01-acceptance-criteria.md` verified ✓
- `02-implementation-notes.md` authored (with pre-split baselines)
- Commit hash(es) + rollback steps in outbox
- No MEDIUM+ findings in code review
