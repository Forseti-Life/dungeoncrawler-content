# Suite Activation: forseti-jobhunter-controller-extraction-phase1

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T18:09:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-controller-extraction-phase1"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-controller-extraction-phase1/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-controller-extraction-phase1-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-controller-extraction-phase1",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-controller-extraction-phase1"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-controller-extraction-phase1-<route-slug>",
     "feature_id": "forseti-jobhunter-controller-extraction-phase1",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-controller-extraction-phase1",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
