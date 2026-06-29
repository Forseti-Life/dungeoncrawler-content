# Outbox update: 20260222-idle-refactor-review-forseti.life-8

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-8
- Generated: 2026-02-22T16:50:27-05:00

---

Now I have all the findings needed. Let me check pm-forseti's inbox queue depth before queuing follow-ups.

I have all findings needed. Here is the outbox for this cycle:

---

- Status: done
- Summary: Idle security review cycle 8: suggested file `css/README_CODE_REVIEWS.md` has no security surface (it is a CSS code review summary doc with maintainability/accessibility findings only, no security relevance). Pivoted per idle-cycle guidance to `src/Service/SearchAggregatorService.php` + `src/Service/SerpApiService.php` (high-value unreviewed surfaces). Key findings: (1) `SerpApiService.php` line 127 logs the full `$query_params` array — which includes `'api_key' => $api_key` — to watchdog INFO level via `print_r()`, leaking the SerpAPI credential to the DB log on every search call. This is the same credential-logging anti-pattern previously found in Adzuna (DCC-0343). (2) `importRecentResults()` stores external `$job_data['description']` unsanitized into `jobhunter_job_requirements.job_description` (line 1037), feeding the DCC-0341 XSS chain. (3) `job_url` and `share_link` are stored without scheme validation — if the external API is ever compromised and returns `javascript:` URIs, they would be stored and potentially rendered as clickable links. (4) `SerpApiService.php` error handler (lines 173–179) appends full HTTP response body to the exception message, which is then logged — response bodies from external APIs may contain internal SerpAPI error details.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (idle refactor/review cycle 8)
- Feature/work item: External job search ingestion — `SearchAggregatorService.php` + `SerpApiService.php`

## Findings

### DCC-SA-08-A — SerpAPI API key logged to watchdog at INFO level (High)
- **Surface:** `src/Service/SerpApiService.php` lines 92, 127–129
- **Evidence:** `$query_params = ['api_key' => $api_key, ...]` (line 92); `$this->loggerFactory->get('job_hunter')->info('🔍 SerpAPI Google Jobs search: @params', ['@params' => print_r($query_params, TRUE)])` (line 127–129)
- **Impact:** Full SerpAPI key written to Drupal watchdog on every search. Any user with `administer site configuration` or DB read access can retrieve the key. Drupal watchdog entries are also frequently exported to log aggregators (Splunk, ELK), widening exposure.
- **Likelihood:** High — fires on every Google Jobs search.
- **Mitigation (minimal diff):**
  ```php
  // Before logging, remove api_key from the logged params
  $log_params = $query_params;
  unset($log_params['api_key']);
  $this->loggerFactory->get('job_hunter')->info('🔍 SerpAPI Google Jobs search: @params', [
      '@params' => print_r($log_params, TRUE),
  ]);
  ```
- **Verification:** Run a job search, then check `admin/reports/dblog` — no `api_key` value should appear in the SerpAPI search log entry.

### DCC-SA-08-B — External `job_description` stored unsanitized (Medium — amplifies DCC-0341)
- **Surface:** `src/Service/SearchAggregatorService.php` line 1037
- **Evidence:** `'job_description' => $job_data['description'] ?? ''` — no `Xss::filter()` or `Html::escape()` before DB insert.
- **Impact:** Malicious job listings from SerpAPI, Adzuna, Google Cloud, or USAJobs can contain HTML/script payloads. These descriptions feed the AI extraction pipeline; AI-extracted fields (e.g., `$extracted['position']['title']`) are then rendered via unescaped `#markup` in `CompanyController::viewJob()` (DCC-0341). This makes the XSS attack surface externally triggerable without any user action — an attacker who can influence external API results can achieve stored XSS.
- **Likelihood:** Medium — requires external API compromise or a malicious job listing passing through the API.
- **Mitigation:** Either (a) sanitize on ingest: `Xss::filter($job_data['description'] ?? '', [])` at line 1037, or (b) fix at the rendering boundary via `Html::escape()` in `CompanyController::viewJob()` — option (b) is higher ROI as it fixes DCC-0341 holistically.
- **Verification:** Insert a test job with `description = '<script>alert(1)</script>Test'`, trigger import, view job — no alert should fire.

### DCC-SA-08-C — `job_url` and `share_link` stored without scheme validation (Medium)
- **Surface:** `src/Service/SearchAggregatorService.php` lines 995, 1030
- **Evidence:** `$job_url = substr((string) ($job_data['url'] ?? ''), 0, 512)` and `$share_link = substr(..., 0, 512)` — only length truncation, no scheme check.
- **Impact:** If a compromised or malicious external API returns `javascript:alert(document.cookie)` as a job URL, it is stored and may be rendered as an `<a href>` link in the UI. Currently partially mitigated by `CompanyController` using Drupal `Url::fromRoute()` for most links, but `$job_url` is used directly at line 681/683 as a raw href.
- **Mitigation (minimal diff):**
  ```php
  $raw_url = (string) ($job_data['url'] ?? '');
  $job_url = (preg_match('#^https?://#i', $raw_url) ? substr($raw_url, 0, 512) : '');
  ```
  Apply same pattern to `$share_link`.
- **Verification:** Insert test job with `url = 'javascript:alert(1)'`, confirm stored value is empty string. Confirm legitimate `https://` URLs still store correctly.

### DCC-SA-08-D — Full HTTP response body logged on SerpAPI error (Low)
- **Surface:** `src/Service/SerpApiService.php` lines 173–179
- **Evidence:** `$error_message .= ' - Response: ' . $e->getResponse()->getBody()->getContents()` — full API response body appended to error log.
- **Impact:** SerpAPI error responses may include internal account/billing error details or rate-limit messages referencing the account. Low severity — log is admin-only, but leaks more info than necessary.
- **Mitigation:** Log first N chars or only the HTTP status code: `$e->getResponse()->getStatusCode()` instead of full body.

## Next actions
- Executor: Create 1 follow-up inbox item in `sessions/pm-forseti/inbox/` for DCC-SA-08-A (SerpAPI credential leak) with ROI 8.
  - Title: `Fix SerpAPI API key credential logging in SerpApiService.php`
  - File: `sites/forseti/web/modules/custom/job_hunter/src/Service/SerpApiService.php`
  - Minimal diff: Unset `api_key` from `$query_params` before calling `print_r()` for the watchdog log at line 127–129 (see mitigation above)
  - Verification: Run job search → check `admin/reports/dblog` → confirm no `api_key` appears in the log entry
  - `roi.txt`: `8`
- DCC-SA-08-B (unsanitized description) should be bundled with the existing DCC-0341 fix already queued to pm-forseti — no new item needed.
- DCC-SA-08-C (scheme validation) can be bundled with DCC-0339 (open redirect) in the existing URL-handling fix item.

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: SerpAPI credential is leaked to the database on every search call — high likelihood of exposure via log exports. Mitigation is a 3-line change with immediate risk reduction. Also confirmed that the DCC-0341 XSS attack surface is externally triggerable via compromised job listings, raising urgency of the DCC-0341 fix.
