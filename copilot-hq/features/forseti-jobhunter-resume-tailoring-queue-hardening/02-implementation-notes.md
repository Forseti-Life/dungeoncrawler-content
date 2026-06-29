# Implementation Notes — Resume Tailoring Queue Hardening

## Commit
- forseti repo: `d9a803646`
- File changed: `sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeTailoringWorker.php`

## What was done

### Problem with prior behavior
`processItem()` called `handleQueueException()` on failure which re-threw the exception. Drupal's cron runner released (did not delete) the item on unhandled exceptions, causing indefinite retries with no backoff and no discard path. The NULL AI result path threw `SuspendQueueException` which suspended the entire queue.

### Changes made

**Input validation (AC-1 / AC-3):** At the top of `processItem()`, required fields (`uid`, `job_id`, `profile_json`, `job_data`) are checked. Missing fields = permanent discard with watchdog log including field name and job ID.

**Backoff delay enforcement:** If `$data['process_after']` is set and is in the future, throws `DelayedRequeueException($delay)`. Drupal releases the item with a lock delay — it won't be picked up again until the delay expires.

**SuspendQueueException removal:** The NULL AI result path (`!$tailored_result || !isset($tailored_result['tailored_resume_json'])`) now throws `\RuntimeException` with a "no usable result" message. This message matches the `transient_patterns` list in `classifyException()` so retry logic applies.

**Error classification (`classifyException()`):**
- `GuzzleHttp\Exception\ServerException` → `'transient'` (HTTP 5xx)
- `GuzzleHttp\Exception\ConnectException` → `'transient'` (timeout/network)
- `GuzzleHttp\Exception\ClientException` HTTP 429 → `'transient'` (rate limit)
- `GuzzleHttp\Exception\ClientException` other 4xx → `'permanent'`
- Message patterns: timeout/503/502/500/rate limit/unavailable/no usable result → `'transient'`
- Message patterns: unauthorized/401/403/forbidden/missing required → `'permanent'`
- Default (unknown) → `'transient'` (safer — allows retry)

**Retry logic (`handleQueueExceptionWithRetry()`):**
- Reads `retry_count` (0-based) from queue item data
- If transient and `retry_count < 3`: creates a NEW queue item with `retry_count + 1` and `process_after = time() + 2^retry_count * 30` (30s/60s/120s), resets DB status to `pending`, returns (item consumed)
- If transient and `retry_count >= 3` or permanent: logs discard with job ID + reason, sets DB status `failed`, returns (item consumed)
- **Does NOT re-throw** — item is always consumed so there is no unbounded retry

### Log output (no PII)
All watchdog messages include: job ID, error type ('transient'/'permanent'), attempt count, error message (no resume content). 

## Acceptance criteria verification

| AC | Status | Evidence |
|----|--------|---------|
| AC-1: watchdog log with job ID and error type | ✅ | `logError()` calls in `handleQueueExceptionWithRetry()` include `@job_id`, `@error_type`, `@attempt` |
| AC-2: transient failures retry with exponential backoff, max 3 | ✅ | `classifyException()` + `handleQueueExceptionWithRetry()` — backoff 30/60/120s, retries capped at 3 |
| AC-3: permanent failures discarded with watchdog log | ✅ | Permanent path sets DB `failed`, logs "permanent failure (permanent)", returns without re-throw |
| No PII in logs | ✅ | Error messages contain job IDs and error codes only — no resume content |
