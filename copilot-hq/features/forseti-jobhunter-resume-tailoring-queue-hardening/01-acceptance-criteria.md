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
