# Suite Activation: forseti-jobhunter-hook-install-fix

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T23:54:31+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-hook-install-fix"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-hook-install-fix/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-hook-install-fix-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-hook-install-fix",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-hook-install-fix"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-hook-install-fix-<route-slug>",
     "feature_id": "forseti-jobhunter-hook-install-fix",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-hook-install-fix",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-jobhunter-hook-install-fix

- Feature: Add missing table creation calls to job_hunter_install()
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Date written: 2026-04-09

## Overview

This is a minimal additive bugfix. Two PHP function calls are added to `hook_install()`. Verification is primarily static (grep-based). No functional behavior change for existing sites.

---

## Test Cases

### TC-01 — Static: both helpers called in hook_install()
- **Suite:** static grep
- **AC item:** Both `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` called in `job_hunter_install()`
- **Steps:**
  1. `grep -A60 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"`
- **Expected:** Both function names appear in output (one line each)
- **Roles covered:** N/A (static)
- **Automated:** Yes — single grep invocation

### TC-02 — Static: no duplicate calls
- **Suite:** static grep
- **AC item:** No duplicate invocations of the two new helpers
- **Steps:**
  1. `grep -c "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table" sites/forseti/web/modules/custom/job_hunter/job_hunter.install`
- **Expected:** Count matches expected call sites (install function call + function definition = 2 per helper, total 4)
- **Automated:** Yes

### TC-03 — Table creation succeeds on simulated fresh install
- **Suite:** PHP unit/functional (optional; static check is primary)
- **AC item:** Fresh install creates both tables without error
- **Steps:**
  1. Invoke `job_hunter_install()` in a test environment with empty schema
  2. Confirm `jobhunter_interview_notes` and `jobhunter_saved_searches` tables exist after invoke
- **Expected:** Both tables created; no PHP exception thrown
- **Automated:** Optional — static check is sufficient for Gate 2

## Pass/Block criteria

- **PASS:** TC-01 output contains both helper function names; no regressions on existing functionality
- **BLOCK:** Either helper function missing from grep output; or TC-03 throws exception in test environment

### Acceptance criteria (reference)

# Acceptance Criteria — forseti-jobhunter-hook-install-fix

- Feature: Add missing table creation calls to job_hunter_install()
- Release target: 20260409-forseti-release-j
- PM owner: pm-forseti
- Date groomed: 2026-04-09
- Priority: P1

## Gap analysis reference

Feature type: `bugfix` — Fresh Drupal install of the job_hunter module will fail to create `jobhunter_interview_notes` and `jobhunter_saved_searches` tables because the two helper functions are defined but not called in `hook_install()`. Existing sites are unaffected (tables created via `drush updb`). Fix is additive only.

## Knowledgebase check
- No prior lessons on this specific pattern found.
- Pattern: ensure all `_job_hunter_create_*` helpers are invoked in `job_hunter_install()` alongside the other table creation calls at lines 49–67.

## Happy Path

- [ ] `_job_hunter_create_interview_notes_table()` is called inside `job_hunter_install()` in the table-creation block
- [ ] `_job_hunter_create_saved_searches_table()` is called inside `job_hunter_install()` in the table-creation block
- [ ] Both calls are inside the existing `try` block, after existing table creation calls

## Edge Cases

- [ ] Function calls placed after all existing `_job_hunter_create_*` calls (preserve ordering convention)
- [ ] No duplicate calls — verify functions are not already called elsewhere in `hook_install()` before adding

## Failure Modes

- [ ] If dev adds calls but does not commit `job_hunter.install`, fresh install test will fail — require commit before Gate 2
- [ ] If helper functions throw exceptions, install would fail — test functions are defensive (skip if table exists)

## Permissions / Access Control

- Not applicable — install hook only.

## Data Integrity

- [ ] No DB migration or schema change required for existing sites.
- [ ] Rollback: revert the two lines from `job_hunter_install()`.

## Dev definition of done
- [ ] Both helper function calls appear in `job_hunter_install()` within the table-creation `try` block
- [ ] Verified via: `grep -A40 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"`
- [ ] Both lines appear in grep output

## QA test path
- Verify via static grep that both functions are now called in `job_hunter_install()`.
- Functional test: simulate fresh install (or mock invocation) and confirm both tables are created.
