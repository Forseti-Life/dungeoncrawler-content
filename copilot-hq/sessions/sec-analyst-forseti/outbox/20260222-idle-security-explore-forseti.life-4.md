# Outbox update: 20260222-idle-security-explore-forseti.life-4

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-security-explore-forseti.life-4
- Generated: 2026-02-22T11:06:14-05:00

---

Now I have enough findings for the cycle 4 outbox report. Let me write it:

- Status: done
- Summary: Cycle 4 adversarial idle review of forseti.life `job_hunter` — focused on JS client-side security, service-layer data ingestion, credential management, and queue-worker coverage. Confirmed `CredentialManagementService` encryption is sound (AES-256-CBC + HKDF). Found four new issues: (1) DOM-XSS surface in `tailor-resume.js` where AI-extracted skill names are concatenated unsanitized into jQuery HTML strings; (2) external job descriptions from Adzuna/SerpAPI/Google Jobs stored without any HTML sanitization at ingestion, directly amplifying the known stored XSS surface in `viewJob()`; (3) three admin-only opportunity-management POST routes missing CSRF protection (both routing.yml and the JS send no token); (4) a credential-test queue item is queued with a user-supplied `test_url` but no corresponding queue worker was found — if a worker is ever added, SSRF risk is live without URL validation.

## Next actions
- **Priority 1 (CEO/PM): Schedule DCC-0341 fix** — `Html::escape()` on all AI-extracted fields in `CompanyController::viewJob()`. Now amplified by new finding: external API job descriptions are stored unsanitized and feed the same XSS path. This is a two-step fix: (a) sanitize at ingestion in `SearchAggregatorService::importRecentResults()` line 1037, (b) escape at render in `CompanyController::viewJob()`.
- **Priority 2 (CEO/PM): Schedule CSRF remediation** — re-enable CSRF on all 9 affected POST routes and update `tailor-resume.js`, `opportunity-management.js` to send `X-CSRF-Token` header (pattern: follow `job-discovery.js` line 55 and `queue-management.js` line 36).
- **Priority 3 (CEO/PM): Fix open credential logging** — `AdzunaApiService` line 125 logs full request URL including `app_id` + `app_key` to watchdog.
- Queue a follow-on inbox item: review `ApplicationSubmitterQueueWorker.php` for credential usage + SSRF surface (not yet reviewed this cycle).

## Blockers
- None — this was a read-only adversarial analysis cycle.

## Needs from CEO
- None currently.

## ROI estimate
- ROI: 22
- Rationale: Two new critical/high amplifications identified (external API ingestion unsanitized + DOM-XSS via skill names) that extend the already-confirmed stored XSS surface to all API-sourced jobs without any additional attacker effort; combined with CSRF gaps on admin routes, fixing these in one coordinated pass prevents multiple attack classes with minimal dev effort (~20 lines of mechanical changes).

---

## Full findings registry (cycle 4 additions)

### DCC-NEW-04-A — DOM-XSS via AI skill name in tailor-resume.js (Medium)
**Affected surface:** `js/tailor-resume.js` lines 760–784  
**Conditions:** AI-extracted skill names containing `"` or HTML special chars concatenated via `+` into jQuery `.html()` render string — no escaping on client side  
**Impact:** If adversarial job posting influences AI extraction to emit a skill name payload, DOM-XSS fires for all users who view the skills-gap section of that job tailoring page  
**Likelihood:** Low-medium — requires either a malicious job posting to be imported from an external source, or a stored XSS escalation through AI output  
**Mitigation:** Use a helper function wrapping `document.createTextNode()` or encode attribute values with `encodeURIComponent()`/`$.text()` instead of `$.html()` for dynamically constructed elements. Twig template path is already safe (auto-escaping).  
**Verification:** Check that `skill.skill = '" onclick="alert(1)'` injected into AJAX response does not fire alert when skills gap section renders.

### DCC-NEW-04-B — External job description stored unsanitized (Medium — amplifies DCC-0341)
**Affected surface:** `src/Service/SearchAggregatorService.php` line 1037  
**Conditions:** `$job_data['description']` from Adzuna/SerpAPI/Google Jobs stored as-is to `jobhunter_job_requirements.job_description`; no `strip_tags()`, `Xss::filter()`, or sanitization at ingestion  
**Impact:** A malicious job posting submitted via any integrated external job API would be stored as raw HTML in the DB, then AI-extracted and rendered via the `#markup` XSS path in `viewJob()` (DCC-0341), delivering stored XSS to any authenticated user who views that job  
**Likelihood:** Medium — depends on ability to inject via external API (supply-chain risk), but Adzuna/SerpAPI are third-party aggregators with varying trust levels  
**Mitigation:** Apply `Xss::filter($job_data['description'])` at line 1037 before storing. This is one line and eliminates the ingestion-side risk. Fix DCC-0341 render-side separately.  
**Verification:** Inject `<script>alert(1)</script>` as description value via test import; confirm it does not execute in viewJob after fix.

### DCC-NEW-04-C — Admin opportunity-management routes missing CSRF (Low/Medium)
**Affected surface:** `job_hunter.routing.yml` lines 73–95; `js/opportunity-management.js` lines 176–280  
**Conditions:** Routes `delete-job`, `delete-search`, `bulk-delete` POST without `_csrf_token` or `_csrf_request_header_mode`; JS sends no `X-CSRF-Token` header  
**Impact:** CSRF attack could cause an admin user to delete jobs/searches if tricked into visiting an attacker-controlled page  
**Likelihood:** Low — requires targeting an authenticated admin; scope is limited to data deletion (not data exfiltration)  
**Mitigation:** Add `_csrf_request_header_mode: 'true'` to these three routes; add `headers: {'X-CSRF-Token': drupalSettings.csrf_token || ''}` to each AJAX call in `opportunity-management.js`.  
**Verification:** Remove token from JS, confirm 403 response; restore token, confirm 200.

### DCC-NEW-04-D — Credential test queue worker unimplemented (Informational/Future-risk)
**Affected surface:** `src/Service/CredentialManagementService.php` lines 411–416; `src/Plugin/QueueWorker/` (no worker found)  
**Conditions:** `testCredential()` enqueues `test_url` (user-supplied string, no scheme/host validation) into `job_hunter_credential_test` queue. No worker consuming this queue exists today.  
**Impact:** If a queue worker is added later without URL validation, any authenticated user with credential management access can trigger server-side requests to arbitrary internal/external hosts (SSRF).  
**Likelihood:** Latent — no current exploit, but implementation gap is one PR away  
**Mitigation:** Before implementing the worker: add URL validation to `testCredential()` that enforces `https://` scheme and blocks private address ranges (RFC1918). Document the requirement in a code comment at line 411.  
**Verification:** Add worker; confirm `http://169.254.169.254/` returns 403/error before request is made.
