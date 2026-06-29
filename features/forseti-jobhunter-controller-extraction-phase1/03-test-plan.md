# Test Plan: forseti-jobhunter-controller-extraction-phase1

**QA owner:** qa-forseti
**Release:** 20260408-forseti-release-i

## Test cases

### TC-1: No direct DB calls in JobApplicationController
- **Type:** static analysis
- **Command:** `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
- **Expected:** 0
- **Pass criteria:** Output is `0`

### TC-2: ApplicationSubmissionService contains migrated methods
- **Type:** static analysis
- **Command:** Check that `ApplicationSubmissionService.php` (or new service) contains the methods documented in dev implementation notes
- **Expected:** All 54 former direct DB calls present as service methods
- **Pass criteria:** Dev diff confirms all 54 present in service layer

### TC-3: Step 1 renders for authenticated user
- **Type:** functional, authenticated smoke test
- **Method:** GET `/jobhunter/application-submission/{job_id}/step-1` as authenticated user
- **Expected:** 200
- **Pass criteria:** HTTP 200, page renders without 500

### TC-4: Step 3 POST submits correctly
- **Type:** functional, authenticated
- **Method:** POST step-3 form as authenticated user
- **Expected:** Advances to step-4 or confirmation (200/redirect)
- **Pass criteria:** Not 500

### TC-5: No regressions in site audit
- **Type:** regression
- **Method:** QA site audit crawl of all job_hunter routes
- **Expected:** No new 403/500 errors vs. pre-refactor baseline
- **Pass criteria:** Audit PASS (0 new failures)
