# Acceptance Criteria: forseti-jobhunter-controller-extraction-phase1

KB reference: none found (new refactor category).

## AC-1 — Zero direct DB calls in JobApplicationController

**Given** `src/Controller/JobApplicationController.php` after the refactor,
**When** I search for `$this->database` (or equivalent direct DB calls),
**Then** zero matches are found.

**Verification:** `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php` → 0.

## AC-2 — DB queries delegated to ApplicationSubmissionService (or new service)

**Given** the 54 moved DB queries,
**When** I search for them in `ApplicationSubmissionService.php` or a new `ApplicationAttemptService.php`,
**Then** all 54 former controller DB calls are present as service methods.

**Verification:** Dev provides file diff and grep evidence that all calls are delegated.

## AC-3 — All submission routes function identically post-refactor

**Given** the job application step 1–5 routes,
**When** QA runs a regression pass as authenticated user,
**Then** all pages render correctly with no new 403/500 errors.

**Verification:** QA smoke test of `/jobhunter/application-submission/{job_id}/step-1` through step-5 as authenticated user. All return expected responses.

## AC-4 — No new controller methods added

**Given** the refactored controller,
**When** I compare public method count before and after,
**Then** the count has not increased (delegation only, no new behavior).

**Verification:** Dev records method count delta in implementation notes.
