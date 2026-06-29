# Suite Activation: forseti-jobhunter-resume-tailoring-queue-hardening

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T21:00:10+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-resume-tailoring-queue-hardening"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-resume-tailoring-queue-hardening/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-resume-tailoring-queue-hardening-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-resume-tailoring-queue-hardening",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-resume-tailoring-queue-hardening"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-resume-tailoring-queue-hardening-<route-slug>",
     "feature_id": "forseti-jobhunter-resume-tailoring-queue-hardening",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-resume-tailoring-queue-hardening",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-resume-tailoring-queue-hardening

**QA owner:** qa-forseti
**Release:** 20260408-forseti-release-j

## Test cases

### TC-1: PHP syntax clean on ResumeTailoringWorker
- **Type:** static
- **Command:** `php -l sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- **Expected:** No syntax errors
- **Pass criteria:** Exit 0

### TC-2: Retry logic present in code
- **Type:** code review / static
- **Method:** `grep -n "retry\|backoff\|retry_count\|max_retries" .../ResumeTailoringWorker.php`
- **Expected:** At least one match showing retry count tracking
- **Pass criteria:** Evidence of retry mechanism in code

### TC-3: No PII in watchdog log calls
- **Type:** code review / static
- **Method:** Review all `logger(...)` calls in `ResumeTailoringWorker.php`
- **Expected:** Log messages contain only job IDs and error codes — no resume content or user data
- **Pass criteria:** No string variables containing user-provided content passed to logger

### TC-4: Permanent failure path discards item safely
- **Type:** code review
- **Method:** Trace code path for `auth` or `invalid_data` error scenarios
- **Expected:** Item is not re-queued on permanent failure; error record written
- **Pass criteria:** No infinite re-queue loop possible; dead-letter path confirmed

### TC-5: Site audit clean
- **Type:** static analysis
- **Command:** `./vendor/bin/drush site:audit`
- **Expected:** 0 failures, 0 violations
- **Pass criteria:** Audit exit 0 or known pre-existing issues only

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-resume-tailoring-queue-hardening

## AC-1 — Failed queue items logged to watchdog

**Given** `ResumeTailoringWorker` processes a failing item,
**When** the failure occurs (API timeout, HTTP 5xx, or invalid data),
**Then** a watchdog entry exists with job ID, error type, and timestamp.

**Verification:** Code review confirms `\Drupal::logger('job_hunter')->error(...)` calls include job ID and error classification.

## AC-2 — Transient failures trigger retry with backoff (max 3 retries)

**Given** a transient failure (HTTP 5xx or timeout) occurs,
**When** the worker encounters the failure,
**Then** the item is re-queued with exponential backoff and retry count incremented, up to max 3 retries.

**Verification:** Code review confirms retry count tracking and backoff logic present; after 3 retries item is discarded with permanent failure log.

## AC-3 — Permanent failures logged and discarded safely

**Given** a permanent failure (auth error, invalid/missing data),
**When** the worker encounters the failure,
**Then** item is discarded with an error log entry (job ID + error code) — no infinite re-queue loop.

**Verification:** Code review confirms permanent failure classification and safe discard path.

## AC-4 — PHP syntax passes

**Verification:** `php -l sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeTailoringWorker.php` → no errors

## AC-5 — No regression in happy-path queue processing

**Verification:** Happy-path queue item (valid data, API success) processes without error. No watchdog errors on normal run.
- Agent: qa-forseti
- Status: pending
