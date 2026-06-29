# Feature Stub: forseti-jobhunter-application-controller-split (Phase 2)

- Site: forseti.life
- Module: job_hunter
- Dispatched by: pm-forseti
- Release target: 20260409-forseti-release-d (or e if deferred)
- ROI: 20

## Context

Phase 1 (DB extraction) shipped in release-c as `forseti-jobhunter-application-controller-db-extraction`.

Phase 2 is documented in that feature's spec:
> "Phase 2 (future release): Split the controller file into `ApplicationSubmissionController` (page renders) + action/AJAX handlers."

`JobApplicationController.php` is 4177 lines. Now that all 54 DB calls are extracted, Phase 2 splits the controller class into:
- `ApplicationSubmissionController` — page render methods (form renders, search results)
- `ApplicationActionController` — AJAX handlers and form submission actions

## Required deliverables

Create `features/forseti-jobhunter-application-controller-split/` with:

1. **`feature.md`** — standard feature brief:
   - Work item id: `forseti-jobhunter-application-controller-split`
   - Website: forseti.life
   - Module: job_hunter
   - Status: ready
   - Priority: P2
   - Feature type: refactor
   - Goal: Split `JobApplicationController.php` (4177 lines, post-extraction) into `ApplicationSubmissionController` (page renders) and `ApplicationActionController` (AJAX/form actions). No behavior changes — pure structural split.
   - Non-goals: No new business logic. No additional DB extraction (handled in Phase 1).
   - Phase: Phase 2 of 2 (Phase 1 = `forseti-jobhunter-application-controller-db-extraction`, shipped release-c)

2. **`01-acceptance-criteria.md`** — measurable ACs:
   - AC-1: `ApplicationSubmissionController.php` exists with all page-render public methods extracted
   - AC-2: `ApplicationActionController.php` exists with all AJAX/form-action public methods extracted
   - AC-3: `JobApplicationController.php` either deleted or reduced to a thin compatibility shim (<50 lines)
   - AC-4: All routes in `job_hunter.routing.yml` that previously pointed to `JobApplicationController` now point to the correct split controller
   - AC-5: `php -l` passes on all 3 files
   - AC-6: Site audit after change: 0 violations, 0 404s on job hunter routes
   - Security AC: All route permissions unchanged from Phase 1 state

3. **`02-implementation-notes.md`** — stub (dev fills in):
   - Approach: identify render vs. action methods, create 2 new controller classes, update routing.yml, delete/shim original
   - Cross-site sync: N/A (forseti-only module)

## Acceptance criteria for THIS BA task

- `features/forseti-jobhunter-application-controller-split/feature.md` exists with Status: ready
- `features/forseti-jobhunter-application-controller-split/01-acceptance-criteria.md` exists with AC-1 through AC-6 populated
- `features/forseti-jobhunter-application-controller-split/02-implementation-notes.md` exists (stub ok)
- `bash scripts/pm-scope-activate.sh forseti forseti-jobhunter-application-controller-split` does not fail on missing grooming artifacts
