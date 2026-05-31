# Lesson Learned: Empty CSRF token on btn-save-job causes E2E test false failure

## Date
2026-02-27

## Symptom
`jobhunter-e2e` Playwright suite exits code 2 (`submission.success: false`) after 6 attempts.
Step 2 (job search + save) completes navigation to `/jobhunter/job-discovery/search` and
finds "Data Engineer" + "Philadelphia" in the page text, but `btn-save-job` click does not
persist a saved job for the QA user. `markAppliedDataEngineer()` always finds 0 table rows.

## Root cause
`data-csrf-token=""` is empty on all `a.btn-save-job` links rendered by
`/jobhunter/job-discovery/search`. The JS handler in `job-search-results.js` intercepts the
click (`e.preventDefault()`), then when `csrfToken` is empty, falls back to
`window.location.href = saveUrl` (direct GET navigation to `/jobhunter/addposting?job_id=...`).

In a headless Playwright browser, this JS-triggered navigation either does not complete within
the 1200ms `waitForLoadState('domcontentloaded').catch(() => {})` window, or the catch swallows
the navigation error silently. The function returns `{ saved: true }` only if the click
succeeds before a page navigation — but the navigation triggers immediately on click, causing
the return path to be skipped.

**Manually**, GET to `/jobhunter/addposting?job_id=forseti_N` with an authenticated session
returns 302 → `/jobhunter/my-jobs` and saves the job correctly (confirmed: row inserted into
`jobhunter_saved_jobs`). The endpoint works; the JS interaction path in Playwright does not.

## Contributing factor
The CSRF token is generated in the controller (`$this->csrfTokenGenerator->get('job_hunter.addposting')`)
but renders empty in both curl and Playwright contexts. This is a Twig variable scoping or
cache issue in `JobApplicationController::jobDiscoverySearchResults()`.

## Fix path (for Dev)
1. Verify why `#save_job_csrf_token` renders empty in `job-search-results.html.twig`.
2. Fix token rendering so `data-csrf-token` has a value.
3. With a non-empty CSRF token, the JS uses `fetch()` + JSON response instead of direct navigation — this path is reliable in Playwright.

## Prevention: pre-suite CSRF smoke check (added to seat instructions)
Before running the full `jobhunter-e2e` Playwright suite, run this 1-second check:

```bash
# Requires: qa_cookies.txt with authenticated QA session
CSRF_COUNT=$(curl -b /tmp/qa_cookies.txt -s \
  "http://localhost/jobhunter/job-discovery/search?q=data+engineer&location=Philadelphia&sources[]=forseti" \
  | grep -c 'data-csrf-token="[^"]\+\"')
if [ "$CSRF_COUNT" -eq 0 ]; then
  echo "FAIL: btn-save-job CSRF tokens are empty — E2E suite will fail (DEF-001 class bug). Fix token rendering before running Playwright."
  exit 1
fi
echo "PASS: $CSRF_COUNT non-empty CSRF tokens found on search results page."
```

If this check fails, skip the Playwright suite and report DEF-001 class defect directly.
This saves ~3 minutes per iteration in the Dev↔QA repair loop.

## Impact
- Each failed E2E run wastes ~3 minutes and produces a potentially misleading report.
- The underlying save endpoint works correctly; the failure is purely in how JS handles empty CSRF.
- Without the smoke check, the defect requires full Playwright run to detect.
