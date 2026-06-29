# Feature: forseti-jobhunter-resume-tailoring-queue-hardening

- Feature ID: forseti-jobhunter-resume-tailoring-queue-hardening
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Priority: P3
- Release: 20260408-forseti-release-j

## Summary
Harden the `ResumeTailoringWorker` queue processor to handle API failures gracefully — add retry backoff, dead-letter logging, and error recovery to prevent silent job drops when the AI tailoring API is unavailable.

## Problem
`ResumeTailoringWorker.php` currently processes queue items but has limited error handling. Failed tailoring jobs (e.g., AI API timeout, LangGraph unavailable) may be silently lost rather than re-queued or logged for manual retry.

## Acceptance criteria
- AC-1: Failed queue items are logged to Drupal watchdog with job ID, error type, and timestamp
- AC-2: Transient failures (HTTP 5xx, timeout) trigger re-queue with exponential backoff (max 3 retries)
- AC-3: Permanent failures (invalid data, auth error) are logged and item is discarded with error record
- AC-4: `php -l` passes
- AC-5: No regression in happy-path queue processing

## Security acceptance criteria
- Authentication/permission surface: Queue worker runs as Drupal cron — no authentication surface change.
- CSRF expectations: Not applicable (queue worker, no HTTP endpoints).
- Input validation requirements: Queue item data validated before processing — malformed items discarded safely.
- PII/logging constraints: Watchdog logs must NOT include resume content or personal data — log job IDs and error codes only.

## Rollback
- `git revert` to previous `ResumeTailoringWorker.php`.

## Verification method
- Static: `php -l ResumeTailoringWorker.php`
- Code review: verify retry logic present, verify no PII in log messages
- Site audit clean
