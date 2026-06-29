# Suite Activation: forseti-jobhunter-controller-refactor

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-06T05:24:54+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-controller-refactor"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-controller-refactor/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-controller-refactor-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-controller-refactor",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-controller-refactor"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-controller-refactor-<route-slug>",
     "feature_id": "forseti-jobhunter-controller-refactor",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-controller-refactor",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-jobhunter-controller-refactor

- Feature: JobApplicationController DB query extraction → JobApplicationRepository (Phase 1)
- QA owner: qa-forseti
- Date written: 2026-04-06
- Release target: NEXT-RELEASE (grooming only — NOT added to suite.json yet)
- AC source: features/forseti-jobhunter-controller-refactor/01-acceptance-criteria.md

## Knowledgebase references
- `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` — QA must use authenticated session when probing controller routes; GET probes are fine for ACL checks, POST probes require CSRF token.

## Notes to PM

**Refactor-only scope:** This is a pure code-structure change (DB queries move from controller to repository). No new user-facing behavior, no route additions, no permission changes. All test cases validate that existing behavior is preserved after the refactor.

**Static verification is the primary gate:** TC-01 and TC-02 (grep-based checks) are the most important tests — they directly verify the refactor AC. They are zero-false-positive, no-auth, no-infrastructure checks.

**Route smoke tests re-use existing suite infrastructure:** TC-04 and TC-05 map directly to the existing `role-url-audit` suite and `jobhunter-surface` / `application-submission-short` permission rules already in `qa-permissions.json`. No new permission rules needed at Stage 0.

**No Playwright required:** All AC items are testable with static grep + PHP unit/functional tests + role-url-audit. No E2E Playwright tests needed for this feature.

**Stage 0 activation checklist (at next release scope selection):**
- [ ] Create `web/modules/custom/job_hunter/tests/src/Unit/JobApplicationRepositoryTest.php` with TC-03, TC-06, TC-07
- [ ] Create `web/modules/custom/job_hunter/tests/src/Functional/JobApplicationControllerSmokeTest.php` with TC-04, TC-05
- [ ] Wire PHPUnit suites into `qa-suites/products/forseti/suite.json` as new `jobhunter-controller-refactor` entry
- [ ] No `qa-permissions.json` changes needed (existing rules cover all routes)

---

## Test Cases

### TC-01 — Static check: no direct DB calls remain in JobApplicationController
- **AC:** `[EXTEND]` JobApplicationController no longer contains `\Drupal::database()` or `db_*` calls
- **Suite:** Static (script, no server required)
- **Command:**
  ```bash
  grep -rn '\\Drupal::database()\|db_query\|db_select\|db_insert\|db_update\|db_delete' \
    web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
  ```
- **Expected:** 0 matches — exit code 1 (grep returns 1 when no matches)
- **Automated:** Yes — single command; can be wired as a CI pre-ship check
- **Roles:** N/A (static check)

### TC-02 — Static check: JobApplicationRepository is injected into controller
- **AC:** `[EXTEND]` Controller delegates to repository (at least one reference to `JobApplicationRepository`)
- **Suite:** Static (script)
- **Command:**
  ```bash
  grep -rn 'JobApplicationRepository' \
    web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
  ```
- **Expected:** ≥ 1 match (class reference or `@param`/`@var` annotation)
- **Automated:** Yes
- **Roles:** N/A

### TC-03 — Static check: JobApplicationRepository class file exists with 54 extracted methods
- **AC:** `[NEW]` Repository class exists; all 54 queries moved
- **Suite:** Static (file existence + method count heuristic)
- **Commands:**
  ```bash
  test -f web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php
  grep -c 'function ' web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php
  ```
- **Expected:** File exists; function count ≥ 10 (54 queries likely consolidated into fewer public methods; exact count confirmed by Dev in implementation notes)
- **Note to PM:** Exact method count to be confirmed by dev in `02-implementation-notes.md`. QA will update this check at Stage 0.
- **Automated:** Yes
- **Roles:** N/A

### TC-04 — Route smoke: existing application_submission routes return 200 for authenticated user
- **AC:** `[EXTEND]` All existing job application routes respond correctly after refactor
- **Suite:** `role-url-audit`
- **Description:** GET each primary `application_submission_*` route (non-parameterized) as authenticated user with `access job hunter`. Assert HTTP 200.
- **Routes to test:**
  - `/jobhunter/application-submission`
  - `/application-submission`
- **Expected HTTP status:** 200 for authenticated
- **Permission coverage:** Existing `jobhunter-surface` and `application-submission-short` rules in `qa-permissions.json` already cover these paths — no new rules needed
- **Roles covered:** authenticated → allow; anon → deny; administrator → allow
- **Automated:** Yes (re-uses existing role-url-audit infrastructure)

### TC-05 — Route smoke: anonymous user blocked (unchanged ACL)
- **AC:** `[TEST-ONLY]` Anonymous users cannot access job application routes (403; unchanged)
- **Suite:** `role-url-audit`
- **Description:** GET `/jobhunter/application-submission` and `/application-submission` without session.
- **Expected HTTP status:** 403 (or redirect to login)
- **Permission coverage:** Existing `jobhunter-surface` + `application-submission-short` rules already enforce this
- **Roles covered:** anon → deny
- **Automated:** Yes (existing suite)

### TC-06 — Unit: paginated job application list returns correct results via repository
- **AC:** `[TEST-ONLY]` Paginated list returns correct results before and after refactor
- **Suite:** Unit (`JobApplicationRepositoryTest.php`)
- **Description:** Call `JobApplicationRepository::getApplicationList($uid, $page, $limit)` (or equivalent method) with a mock DB seeded with known records. Assert returned count and pagination offset match expectations. Compare output format to pre-refactor controller behavior.
- **Expected:** Paginated results match seed data; no off-by-one errors; empty page returns empty array (not null/exception)
- **Roles covered:** authenticated (backend unit)

### TC-07 — Unit: job status update persisted correctly via repository
- **AC:** `[TEST-ONLY]` Job status update persisted correctly via repository
- **Suite:** Unit (`JobApplicationRepositoryTest.php`)
- **Description:** Call `JobApplicationRepository::updateApplicationStatus($job_id, $status)` (or equivalent). Assert DB record is updated with new status and an updated timestamp. Assert return value is success (not false/exception).
- **Expected:** Exactly one row updated; status field matches new value; no duplicate rows
- **Roles covered:** authenticated (backend unit)
- **KB note:** Use `job_seeker_id` (profile PK) not raw UID in any log records per KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.

### TC-08 — Unit: DB connection failure throws typed exception
- **AC:** `[TEST-ONLY]` Repository throws typed exception on DB connection failure (not silently swallowed)
- **Suite:** Unit (`JobApplicationRepositoryTest.php`)
- **Description:** Inject a mock database connection that throws `\Drupal\Core\Database\DatabaseException`. Assert `JobApplicationRepository` propagates a typed exception (not catches silently, not returns null, not triggers PHP warning).
- **Expected:** Typed exception propagates; no silent failure; no PHP fatal (exception type confirmed in implementation notes)
- **Roles covered:** authenticated (backend unit)
- **Note to PM:** Dev must document the exception type in `02-implementation-notes.md`. QA will use that type in the unit test assertion.

## AC items that cannot be expressed as automation

| AC item | Reason | Note to PM |
|---|---|---|
| `[NEW]` All 54 queries moved | Exact count depends on Dev consolidation decisions | TC-03 uses heuristic (≥10 functions); Dev to confirm exact method count in impl notes |
| `[DATA]` No data migration required | Operational assertion; verified by Dev on deploy | Not a QA test case — trust rollback plan in feature.md |
| Rollback path | Requires reverting a commit; not automatable | Dev documents in impl notes; manual verification acceptable |

## Suite activation (Stage 0 only)

Do NOT edit `qa-suites/products/forseti/suite.json` or `qa-permissions.json` until this feature is selected into release scope.

At Stage 0:
- [ ] Create `WorkdayWizardServiceTest.php` — correction: create `JobApplicationRepositoryTest.php` with TC-06, TC-07, TC-08
- [ ] Create `JobApplicationControllerSmokeTest.php` with TC-04, TC-05 (functional)
- [ ] Add static checks TC-01, TC-02, TC-03 as a pre-ship script (no infra needed)
- [ ] Add `forseti-jobhunter-controller-refactor` suite entry to `suite.json`
- [ ] No `qa-permissions.json` changes required

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)
# Feature: forseti-jobhunter-controller-refactor (Phase 1)

## Gap analysis reference

Gap analysis in `feature.md` is complete. Phase 1 scope: DB query extraction only. Criteria are `[NEW]` for repository class and `[EXTEND]` for controller delegation.

## Happy Path

- [ ] `[NEW]` `JobApplicationRepository` class exists at `web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php`.
- [ ] `[NEW]` All 54 direct DB queries previously inline in `JobApplicationController` are now in `JobApplicationRepository`.
- [ ] `[EXTEND]` `JobApplicationController` no longer contains direct `\Drupal::database()` or `db_*` calls — it delegates to `JobApplicationRepository`.
- [ ] `[EXTEND]` All existing job application routes respond correctly (create, list, update status, delete).

## Edge Cases

- [ ] `[TEST-ONLY]` Paginated job application list returns correct results before and after refactor.
- [ ] `[TEST-ONLY]` Job status update is persisted correctly via repository.

## Failure Modes

- [ ] `[TEST-ONLY]` `JobApplicationRepository` throws a typed exception on DB connection failure (not silently swallowed).

## Permissions / Access Control

- [ ] `[TEST-ONLY]` No ACL changes — repository is internal; same permissions apply via controller routing.
- [ ] `[TEST-ONLY]` Anonymous users cannot access job application routes (403 expected; unchanged from pre-refactor).

## Data Integrity

- [ ] No data migration required — repository queries same tables.
- [ ] Rollback path: revert repository extraction commit; controller inline queries restored.

## Knowledgebase check

- Reference: `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md` — CSRF probe pattern; QA must use authenticated session when testing controller routes.

## Verification method

```bash
# After refactor:
grep -rn "\\\\Drupal::database()" web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
# Expected: 0 matches

grep -rn "JobApplicationRepository" web/modules/custom/job_hunter/src/Controller/JobApplicationController.php
# Expected: at least 1 match (injected dependency)
```
- Agent: qa-forseti
- Status: pending
