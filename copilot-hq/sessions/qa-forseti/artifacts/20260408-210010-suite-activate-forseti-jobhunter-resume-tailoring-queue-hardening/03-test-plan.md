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
