# Outbox update: 20260222-idle-refactor-review-forseti.life-4

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-4
- Generated: 2026-02-22T14:32:02-05:00

---

I have all the information needed. Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-4.md`:

---

- Status: done
- Summary: Reviewed `GoogleJobsSearchController.php` (356 lines) for the `job_hunter` module (forseti.life). The controller handles Google Cloud Talent Solution API search and job import, and is structurally sound — it has proper DI, try-catch on all endpoints, and caching on search results. However it has 7 requirements clarity gaps: the `apiImport` and `apiBatchImport` endpoints have no CSRF protection despite being state-mutating POST endpoints, the batch import has no size cap (unbounded array from caller), error responses leak raw exception messages to the client, the cache TTL is hardcoded at 3600s with no documented rationale or configuration path, `job_name` in `apiGetJobDetails` is a URL path segment passed directly to the Cloud Talent API with no format validation, the `employment_types` filter accepts a free-form comma-delimited string with no allowlist, and there is no idempotency handling for duplicate import attempts. All downstream seats (dev-forseti 3, qa-forseti 3, pm-forseti 28+) are at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/GoogleJobsSearchController.php`
- 356 lines — Cloud Talent Solution search + single/batch import + job detail endpoints

## Requirements clarity improvements (7 found)

### 1. POST endpoints missing CSRF protection (HIGH — security gap)
`apiImport` (`POST /jobhunter/api/googlejobs/import`) and `apiBatchImport` (`POST /jobhunter/api/googlejobs/batch-import`) are state-mutating endpoints that insert records into `jobhunter_job_requirements`. The routing config only requires `_permission: 'access job hunter'` — there is no `_csrf_token: 'TRUE'` requirement. Any authenticated user's browser can be tricked into submitting a cross-site request that imports arbitrary job data.
- Diff direction — routing file: add `_csrf_token: 'TRUE'` to both POST route `requirements` blocks. Add `methods: [POST]` is already present; add token. Caller JS must fetch the token from `/session/token` and include it as `X-CSRF-Token` header.
- AC: A POST to `/jobhunter/api/googlejobs/import` without a valid CSRF token returns HTTP 403. Verified by making an unauthenticated cross-origin POST to the endpoint with a valid session cookie but no CSRF token.

### 2. Batch import has no size cap — unbounded array (HIGH — DoS risk)
`apiBatchImport` iterates `$content['jobs']` with no upper-bound check. A caller can submit hundreds or thousands of jobs in a single request, causing a long-running PHP process, potential timeout, and database lock contention.
- Diff direction: Add immediately after the `is_array` check:
  ```php
  $max_batch = 50;
  if (count($content['jobs']) > $max_batch) {
    return new JsonResponse(['error' => 'Batch size exceeds maximum of ' . $max_batch . ' jobs'], 400);
  }
  ```
- AC: POST with 51 jobs returns HTTP 400 with `"Batch size exceeds maximum of 50 jobs"`. POST with 50 jobs processes normally. Document the `$max_batch` value as a constant or config item so it can be tuned without code change.

### 3. Raw exception messages leaked to client (MEDIUM — security + UX)
All three catch blocks return `$e->getMessage()` directly in the JSON response body:
```php
return new JsonResponse(['error' => $e->getMessage()], 500);
```
This exposes internal stack context, service names, and API keys/URLs that may appear in exception messages from the Cloud Talent SDK.
- Diff direction: Replace with a generic client-facing message and log the full exception server-side:
  ```php
  $this->logError('Google Jobs search failed: @error', ['@error' => $e->getMessage()]);
  return new JsonResponse(['error' => 'An error occurred. Please try again.'], 500);
  ```
- AC: When Cloud Talent Service throws an exception, the client receives a generic error string. The full exception is present in Drupal's watchdog/dblog. Verify by temporarily triggering a Cloud Talent auth error and confirming no API key or service URL appears in the HTTP response body.

### 4. Cache TTL hardcoded at 3600s, undocumented rationale (MEDIUM — maintainability)
```php
$expire = time() + 3600;
```
The 1-hour TTL is a magic number with no documented reasoning. Search results from Cloud Talent Solution can include salary data, job availability, and application deadlines — staleness tolerance is not defined. Additionally `time() + 3600` is a Drupal anti-pattern; the Drupal cache system expects an absolute Unix timestamp but `CacheBackendInterface::CACHE_PERMANENT` or `\Drupal::time()->getRequestTime() + TTL` is preferred for testability.
- Diff direction: Define a constant: `const SEARCH_CACHE_TTL = 3600;`. Add a docblock comment: "Cache search results for 1 hour. Rationale: Cloud Talent results are semi-static; 1h balances freshness with API quota. Adjust if job listing churn increases." Replace `time()` with `\Drupal::time()->getRequestTime()`.
- AC: Cache TTL is defined in one place. A future config change requires editing only the constant. Verify by mocking `\Drupal::time()` in a unit test.

### 5. `job_name` URL path parameter not format-validated (MEDIUM — injection risk)
`apiGetJobDetails(Request $request, $job_name)` passes `$job_name` directly to `$this->cloudTalentService->getJob($job_name)` without any format check. Cloud Talent job names follow the pattern `projects/{project}/tenants/{tenant}/jobs/{job_id}`. An attacker could submit a crafted `job_name` that causes unexpected API calls or path traversal within the Cloud Talent API namespace.
- Diff direction: Add format validation before the service call:
  ```php
  if (!preg_match('/^projects\/[a-zA-Z0-9_-]+\/tenants\/[a-zA-Z0-9_-]+\/jobs\/[a-zA-Z0-9_-]+$/', $job_name)) {
    return new JsonResponse(['error' => 'Invalid job identifier format'], 400);
  }
  ```
- AC: A request to `/jobhunter/api/googlejobs/details/../../evil` returns HTTP 400. A request with a well-formed job name proceeds normally.

### 6. `employment_types` filter has no allowlist (MEDIUM — input validation gap)
```php
$employment_types = $request->query->get('employment_types', '');
// ...
$params['employment_types'] = explode(',', $employment_types);
```
The Cloud Talent Solution API accepts a defined set of employment type values (e.g., `FULL_TIME`, `PART_TIME`, `CONTRACTOR`, `INTERN`). The current code passes arbitrary caller-supplied values to the API without validation. Invalid values silently cause the API to return empty results or an error that the catch block hides behind a generic 500.
- Diff direction: Define a constant array:
  ```php
  const VALID_EMPLOYMENT_TYPES = ['FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'PER_DIEM', 'FLY_IN_FLY_OUT', 'OTHER_EMPLOYMENT_TYPE'];
  ```
  Filter the input: `array_filter(explode(',', $employment_types), fn($t) => in_array(trim($t), self::VALID_EMPLOYMENT_TYPES, true))`. Invalid values are silently dropped (or optionally return HTTP 400 — PM to decide).
- AC: A request with `employment_types=FULL_TIME,EVIL_TYPE` only passes `FULL_TIME` to the API. Verify by asserting the array passed to `cloudTalentService->searchJobs()` contains only allowed values.

### 7. No idempotency on import — duplicate jobs not surfaced (LOW — UX/data integrity)
`apiImport` returns `{'success': true, 'job_id': X}` but when a job is already imported, `cloudTalentService->importJob()` returns `null` and the controller returns `{'error': 'Failed to import job', 500}`. However, the search results already set `is_imported: true` on the frontend — so the import button should not be active for already-imported jobs. The server-side behavior for a duplicate import attempt is an opaque 500 error rather than a meaningful `409 Conflict`.
- Diff direction: In `apiImport`, before calling `importJob`, check if the job already exists in `jobhunter_job_requirements` by `external_job_id`. If found, return HTTP 200 with `{'success': true, 'job_id': <existing_id>, 'already_imported': true}` instead of 500. This also covers the race condition where two tabs import the same job simultaneously.
- AC: A second import of the same job_name returns HTTP 200 with `already_imported: true`. A first import returns HTTP 200 with `already_imported: false`. No 500 is returned for a duplicate.

## Clarifying questions for PM/stakeholders

1. Should `employment_types` validation failures be silent (filter out invalid values) or explicit (HTTP 400)? Recommend: silent filter for better UX, since a partial result is better than a hard error.
2. Is the 1-hour search cache TTL acceptable given job availability can change? Are there API quota pressures that justify it?
3. What is the intended batch import size limit — 50 is a reasonable default, but PM should confirm against typical user workflow (how many jobs does a user realistically batch-import at once?).

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap note: dev-forseti (3), qa-forseti (3), pm-forseti (28+) are all at cap. Executor should route when capacity opens.

### FU-1 → dev-forseti: Add CSRF protection to POST import endpoints + batch size cap (ROI 55)
- Files: `job_hunter.routing.yml` + `GoogleJobsSearchController.php`
- Diff:
  1. Routing: add `_csrf_token: 'TRUE'` to `job_hunter.google_jobs_import` and `job_hunter.google_jobs_batch_import` requirements blocks.
  2. Controller `apiBatchImport`: add `if (count($content['jobs']) > 50) { return new JsonResponse(['error' => 'Batch size exceeds maximum of 50 jobs'], 400); }` after the `is_array` check.
  3. Controller: define `const MAX_BATCH_IMPORT = 50;` and reference it.
- Verification: POST to `/jobhunter/api/googlejobs/import` without `X-CSRF-Token` header (with valid session) → expect HTTP 403. POST batch with 51 jobs → expect HTTP 400. Confirm caller JS sends `X-CSRF-Token` from `/session/token`.
- `roi.txt`: 55

### FU-2 → dev-forseti: Sanitize error responses + add job_name format validation (ROI 40)
- File: `GoogleJobsSearchController.php`
- Diff:
  1. All three catch blocks: replace `'error' => $e->getMessage()` with `'error' => 'An error occurred. Please try again.'` (keep logError call unchanged).
  2. `apiGetJobDetails`: add regex format check on `$job_name` before service call (pattern: `^projects/[a-zA-Z0-9_-]+/tenants/[a-zA-Z0-9_-]+/jobs/[a-zA-Z0-9_-]+$`). Return HTTP 400 on mismatch.
  3. `apiSearch`: add `employment_types` allowlist filter using `VALID_EMPLOYMENT_TYPES` constant.
- Verification: Trigger a Cloud Talent auth failure (bad config); confirm response body contains no API keys/URLs. Request `/api/googlejobs/details/../../evil`; confirm HTTP 400. Request with `employment_types=FULL_TIME,BAD`; confirm only `FULL_TIME` is passed to service.
- `roi.txt`: 40

## Blockers
- None.

## Next actions
- Executor: route FU-1 (CSRF + batch cap, ROI 55) and FU-2 (error sanitization + validation, ROI 40) to dev-forseti inbox when seat has capacity.
- Executor: optionally surface Gap #7 (duplicate import idempotency) as a low-priority follow-up after FU-1 and FU-2 are resolved — it is a UX improvement rather than a security issue.
- BA: no further action on this item.

## ROI estimate
- ROI: 45
- Rationale: The CSRF vulnerability on the import endpoints is a concrete exploitable security gap in a state-mutating API — the highest-priority finding. The error message leakage is a secondary security risk. Combined with the batch size DoS vector, these three issues justify a higher-than-baseline idle-cycle ROI. All fixes are targeted and low-effort relative to their security impact.
