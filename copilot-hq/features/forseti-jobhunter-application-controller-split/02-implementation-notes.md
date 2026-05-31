# Implementation Notes: forseti-jobhunter-application-controller-split

- Feature: forseti-jobhunter-application-controller-split
- Author: ba-forseti (stub) — dev-forseti to expand during implementation
- Phase: Phase 2 of 2 (Phase 1 = forseti-jobhunter-application-controller-db-extraction, shipped release-c)

## Approach (to be finalized by dev-forseti)

### Pre-work
1. Read `features/forseti-jobhunter-application-controller-db-extraction/02-implementation-notes.md` for Phase 1 context (constructor DI pattern, service injection, DB extraction map).
2. Audit `JobApplicationController.php` — categorize every public method as either:
   - **Render**: returns `Response`, `render array`, or calls `$this->view()`/`$this->render()` — goes to `ApplicationSubmissionController`
   - **Action/AJAX**: handles form submission, returns `JsonResponse`, or handles redirects post-action — goes to `ApplicationActionController`
3. Note any shared `protected` or `private` helpers — may need duplication or extraction to a shared trait.

### Split strategy
- Create `ApplicationSubmissionController.php` extending Drupal's `ControllerBase` (same as current class).
- Create `ApplicationActionController.php` extending Drupal's `ControllerBase`.
- Copy constructor and DI wiring verbatim from `JobApplicationController.php` into both new classes.
- Move methods (do not refactor signatures) — one method at a time to allow incremental verification.
- Update `job_hunter.routing.yml` as each method moves: change `_controller: '\Drupal\job_hunter\Controller\JobApplicationController::methodName'` to the correct new class.
- After all methods are moved: either delete `JobApplicationController.php` or reduce it to a thin stub (≤ 50 lines) if Drupal caches the old class reference.
- Run `drush cr` after each batch of routing changes.

### Routing update pattern
Before (example):
```yaml
job_hunter.my_jobs:
  path: '/jobhunter/my-jobs'
  defaults:
    _controller: '\Drupal\job_hunter\Controller\JobApplicationController::myJobs'
```
After:
```yaml
job_hunter.my_jobs:
  path: '/jobhunter/my-jobs'
  defaults:
    _controller: '\Drupal\job_hunter\Controller\ApplicationSubmissionController::myJobs'
```

### CSRF split-route preservation (critical)
- Do NOT collapse existing split-route pairs (GET + POST entries for the same path).
- When renaming the controller in routing.yml, only change the `_controller:` value; leave `_csrf_token`, `methods`, and permission lines untouched.
- Reference: KB lesson `knowledgebase/lessons/20260326-csrf-route-split-pattern.md` (or equivalent).

### Verification after each batch
```bash
cd /home/ubuntu/forseti.life/sites/forseti
./vendor/bin/drush cr
php -l web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php
php -l web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php
./vendor/bin/drush router:debug | grep job_hunter
```

### File locations
- New controllers: `web/modules/custom/job_hunter/src/Controller/`
- Routing: `web/modules/custom/job_hunter/job_hunter.routing.yml`

## Pre-split baselines (recorded 2026-04-09 by dev-forseti)

| Metric | Value |
|---|---|
| `wc -l JobApplicationController.php` | **3827** |
| `grep -c "_csrf_token" job_hunter.routing.yml` | **37** |
| Total routed public methods | **20** |
| PHP syntax | Clean (`php -l` exits 0) |
| `grep -c '\$this->database'` | **0** (Phase 1 extraction complete) |

### Routed method inventory (for routing.yml update tracking)

| Method | Route count | Assignment |
|---|---|---|
| `dashboard` | 1 | Submission |
| `jobDiscoverySearchResults` | 1 | Submission |
| `addPostingFromSearch` | 1 | Action |
| `jobDiscovery` | 1 | Submission |
| `myJobs` | 1 | Submission |
| `myJobsArchive` | 1 | Submission |
| `archiveJob` | 1 | Action |
| `unarchiveJob` | 1 | Action |
| `toggleJobApplied` | 1 | Action |
| `applicationSubmission` | 4 | Submission |
| `applicationSubmissionResolveRedirectChain` | 2 | Action |
| `applicationSubmissionIdentifyAuthPath` | 4 | Action |
| `applicationSubmissionCreateAccount` | 4 | Action |
| `applicationSubmissionSubmitApplication` | 4 | Action |
| `applicationSubmissionScreenshot` | 2 | Submission |
| `applicationSubmissionStepStub` | 4 | Submission |
| `interviewFollowup` | 1 | Submission |
| `analytics` | 1 | Submission |
| `view` | 1 | Submission |
| `listJobsRedirect` | 1 | Action |
| `getTitle` (title_callback) | 1 | Submission |

### Method line-count analysis (key findings for AC-3)

| Method | Lines (approx) | Owner controller |
|---|---|---|
| `applicationSubmissionSubmitApplication` | **703** | ActionController |
| `applicationSubmissionStepStub` + 6 private helpers | **502** | SubmissionController |
| `applicationSubmissionCreateAccount` | **359** | ActionController |
| `applicationSubmissionResolveRedirectChain` | **257** | ActionController |
| `view` + 5 private dashboard helpers | **298** | SubmissionController |
| `applicationSubmission` | **232** | SubmissionController |
| `addPostingFromSearch` + 5 private helpers | **274** | ActionController |
| `applicationSubmissionIdentifyAuthPath` | **163** | ActionController |

## AC-3 feasibility analysis (ESCALATION REQUIRED)

**AC-3 states: each new controller ≤ 800 lines.**

This constraint is **provably unachievable** with a pure structural split:

1. **`applicationSubmissionSubmitApplication` alone = 703 lines** (lines 2504–3206).
   A minimal `ApplicationActionController` containing only this method + constructor (~42 lines) + PHP class header and `use` statements (~30 lines) = **~775 lines before adding any other method**.
   The next-largest action method (`applicationSubmissionCreateAccount`, 359 lines) would push the file to ~1134 lines.

2. **`ApplicationSubmissionController` total routed method code (excluding private helpers) = ~874 lines** (home + dashboard + view + companiesOverview + jobDiscovery + myJobs + myJobsArchive + jobDiscoverySearchResults + applicationSubmission + applicationSubmissionScreenshot + applicationSubmissionStepStub + interviewFollowup + analytics).
   Including the constructor and headers: **~1000 lines minimum**, even without any private helpers.

3. **Even with a shared trait** extracting all private helpers (~1900 lines of private methods to a `JobApplicationControllerTrait`):
   - `ApplicationSubmissionController` public methods only: ~874 + header/constructor ~130 = **~1004 lines** (still > 800)
   - `ApplicationActionController` public methods only: ~1736 + header/constructor ~130 = **~1866 lines** (far > 800)

**The 800-line constraint requires breaking large public methods into sub-methods or service calls — which is Phase 3 business logic extraction, explicitly listed as a non-goal in `01-acceptance-criteria.md`.**

### Realistic line counts for a structural split

| Controller | Realistic minimum |
|---|---|
| `ApplicationSubmissionController` | ~1900 lines (with shared trait for private helpers) |
| `ApplicationActionController` | ~2000 lines (with shared trait for private helpers) |
| Shared `JobApplicationControllerBaseTrait` | ~1100 lines (extracted private helpers) |

### Recommended resolution (for PM decision)

**Option A (recommended)**: Revise AC-3 to ≤ 2200 lines per controller. Proceed with structural split + a shared trait for private helpers. This satisfies the spirit (separation of render vs action) without Phase 3 scope.

**Option B**: Split into 3 controllers (Submission + Action + a third for the `applicationSubmission*` wizard flow, which accounts for ~2000 lines alone). Each would be ~1000–1300 lines. Still not 800, but closer.

**Option C**: Defer the split and escalate the entire feature to Phase 3 where business logic extraction can also decompose the large methods.

Dev recommendation: **Option A**. The structural separation still delivers value (clearer ownership, smaller diffs for future changes). The 800-line target was set without inspection of actual method sizes; the real constraint was likely "reasonably manageable" not literally 800.

## Cross-site sync
- N/A — job_hunter module is forseti.life only.

## Known risks
- `applicationSubmissionSubmitApplication` (703 lines) is a core hot path. Any accidental change during the split would be production-impacting. Must be moved verbatim with no edits.
- Shared private/protected helpers: at least 14 private methods are only used within one logical group. Group-specific privates stay in the owning controller; truly shared ones go in the existing `JobHunterControllerTrait` or a new base class.
- Constructor size: DI wiring is 8 services — copy verbatim into both new files.

## Dev sign-off checklist
- [ ] PM confirms revised AC-3 (target line count per controller)
- [ ] All AC from `01-acceptance-criteria.md` verified with commands
- [ ] `drush cr` exits 0 with no fatal errors in watchdog
- [ ] `git diff --stat` reviewed to confirm no unintended files changed

## Implementation Results (dev-forseti, release-d)

### Final split rationale

`loadSelectedJobContext()` was the only private helper used across both controller halves (submission + action). Extracted to `ApplicationControllerHelperTrait.php`. All other private helpers had single-controller usage and were moved with their owning method.

`detectAtsPlatformFromUrl()` was action-only (used only inside SubmitApplication chain). `buildJobJourneyFlow()` was submission-only. No further trait extraction required.

### File sizes (post-split)

| File | Lines |
|---|---|
| ApplicationSubmissionController.php | 1795 |
| ApplicationActionController.php | 2027 |
| ApplicationControllerHelperTrait.php | 22 |
| JobApplicationController.php (stub) | 17 |

All files ≤ 2500 lines (AC-3 updated by PM, Option A).

### Verification

- `php -l` passes on all three new files — no syntax errors
- `job_hunter.routing.yml`: 0 remaining `JobApplicationController::` references
- `job_hunter.routing.yml`: `_csrf_token` count = 37 (unchanged from pre-split)
- `drush cr` completed with exit 0, no new PHP errors in watchdog
- Smoke test: `https://forseti.life/` → 200; `https://forseti.life/jobhunter/my-jobs` → 403 (auth-protected, expected)

### Sign-off checklist

- [x] All 3 new files pass `php -l`
- [x] routing.yml CSRF count unchanged (37)
- [x] routing.yml has 0 refs to old class
- [x] `JobApplicationController.php` stubbed to ≤ 20 lines
- [x] `drush cr` succeeded
- [x] Smoke test passed
- [x] Committed
