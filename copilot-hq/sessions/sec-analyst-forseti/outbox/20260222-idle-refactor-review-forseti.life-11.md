# Outbox update: 20260222-idle-refactor-review-forseti.life-11

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-11
- Generated: 2026-02-22T17:20:19-05:00

---

I now have all the data needed. Let me produce both outboxes — cycle 9 first, then cycle 11.

---

**OUTBOX for `20260222-idle-refactor-review-forseti.life-9`**

The suggested file was `QueueWorkerBaseTrait.php`, which was already reviewed in cycle 10. Pivoting to `OpportunityManagementService.php` as the unreviewed high-value file.

---

- Status: done
- Summary: Idle security review of `src/Service/OpportunityManagementService.php` (forseti.life / job_hunter module) completed. The service handles deletion of jobs, search history, and cached results. Key findings: (1) The three AJAX delete routes (`opportunity_delete_job`, `opportunity_delete_search`, `opportunity_bulk_delete`) are POST-only and require `administer job application automation` permission, but have NO CSRF token requirement and the corresponding `opportunity-management.js` sends NO `X-CSRF-Token` header — confirmed CSRF gap on all three destructive endpoints. (2) `deleteJob()`, `deleteSearchHistory()`, `deleteSearchResult()`, and `bulkDeleteJobs()` contain NO `uid` ownership filter — any admin can delete any other user's data by ID. This is low-risk given admin-only access, but violates defense-in-depth. (3) `bulkDeleteSearches()` correctly uses a transaction but suppresses the transaction commit path — the `$transaction` variable is created in the try block and would be garbage-collected on success (correct DBTNG behavior), but the `rollBack()` in catch is conditional on `isset($transaction)` — if an exception is thrown before the transaction is created (edge case), rollback is silently skipped. (4) `getSearchHistory()` returns all users' search history records to any admin, up to 500 rows, with no multi-user scoping — this is expected for an admin view but should be documented as a deliberate design choice.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 9)
- Feature/work item: Opportunity management — delete/bulk-delete flows (`OpportunityManagementService.php`, `OpportunityManagementController.php`)

## Findings

### DCC-OM-09-A — CSRF missing on all three opportunity management delete routes (High)
- **Surface:** Routes `job_hunter.opportunity_delete_job`, `job_hunter.opportunity_delete_search`, `job_hunter.opportunity_bulk_delete` in `job_hunter.routing.yml` lines 73–95
- **Evidence:** All three routes have `methods: [POST]` and `_permission: 'administer job application automation'` but no `_csrf_token: 'TRUE'` or `_csrf_request_header_mode`. `opportunity-management.js` confirms: zero `X-CSRF-Token` headers sent.
- **Impact:** Any page the admin visits that embeds a hidden form or fetch() call targeting these endpoints can trigger bulk deletion of jobs and search history without consent. Admin-only surface limits blast radius to self-CSRF, but with stored XSS (DCC-0341) this becomes cross-user CSRF.
- **Mitigation:**
  ```yaml
  # job_hunter.routing.yml — add to all three opportunity delete routes:
  requirements:
    _permission: 'administer job application automation'
    _csrf_request_header_mode: 'true'
  ```
  And in `opportunity-management.js`, fetch the CSRF token:
  ```js
  const csrfToken = drupalSettings.path.currentPathIsAdmin ? '' : await fetch('/session/token').then(r => r.text());
  // then add: headers: { 'X-CSRF-Token': csrfToken }
  ```
- **Verification:** Remove `X-CSRF-Token` header from a manual POST to `/jobhunter/opportunity/delete-job` → server returns 403. Add it → returns 200.

### DCC-OM-09-B — No `uid` ownership check in delete operations (Low — admin-only surface)
- **Surface:** `OpportunityManagementService::deleteJob()`, `deleteSearchHistory()`, `deleteSearchResult()`, `bulkDeleteJobs()`, `bulkDeleteSearches()` — none include `->condition('uid', $uid)` filter
- **Impact:** Any admin can delete any other user's jobs/searches by knowing the ID. Given admin-only routes, this is low severity, but violates least-privilege.
- **Mitigation:** Add `uid` ownership filter or document as admin-intentional (acceptable if this is a global admin tool).
- **Verification:** Attempt to delete a job belonging to `uid=1` while authenticated as `uid=2` (admin) → should be blocked if ownership check added.

### DCC-OM-09-C — Transaction rollback may be silently skipped (Low — robustness)
- **Surface:** `bulkDeleteSearches()` lines 366–393, `deleteSearchHistory()` lines 244–273
- **Evidence:** `$transaction = $this->database->startTransaction()` is inside the try block; if an exception fires before this line (e.g., on entry validation), `isset($transaction)` in catch is FALSE and no rollback is attempted. Risk is low but partial deletes could leave orphaned `jobhunter_job_search_results` rows.
- **Mitigation:** Move `$transaction` creation to before the try block, or use a separate `try/catch` just for the transaction start.

## Next actions
- Executor: Create follow-up in `sessions/pm-forseti/inbox/` for DCC-OM-09-A (CSRF on opportunity management routes), ROI 7.
  - Title: `Add CSRF protection to opportunity management delete routes`
  - Files: `job_hunter.routing.yml` (lines 73–95), `js/opportunity-management.js`
  - Patch: add `_csrf_request_header_mode: 'true'` to all three routes; add `X-CSRF-Token` header to JS fetch calls
  - Verification: POST without token → 403; with token → 200
  - `roi.txt`: `7`

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: CSRF on destructive admin endpoints compounds with stored XSS (DCC-0341) — if XSS fires on admin, attacker can trigger bulk job deletion silently. CSRF fix is a 5-line routing.yml change plus a JS header addition.

---

Now producing the cycle 11 outbox:

---

- Status: done
- Summary: Idle security review of `src/Service/ResumePdfService.php` (forseti.life / job_hunter module, 1291 lines) completed. The service generates PDF resumes using TCPDF from AI-extracted JSON. Key findings: (1) `loadStyleSchema()` at line 140 constructs a filesystem path as `$module_path . '/config/resume_styles/' . $name . '.json'` where `$name` is the `$style_schema_name` parameter — currently all callers use the hardcoded default `'keith_aumiller'`, so no path traversal is possible today, but the API has no validation against directory traversal patterns (e.g., `../../etc/passwd`) and no whitelist. This is a latent path traversal risk if any future caller passes user-controlled input. (2) URLs from resume content (`$website['url']`, `$linkedin['url']`) are passed directly to `TCPDF::Cell()` as clickable link targets at lines 364, 381, 390 without scheme validation — a `javascript:` URI or `data:` URI in a website URL field would be embedded as a clickable link in the generated PDF, potentially triggering action in PDF readers that evaluate JavaScript. (3) The `generateAndSavePdf()` method at line 116 falls back to `\Drupal::currentUser()->id()` if `$userId` is NULL — this uses a Drupal static call rather than an injected service, making testing harder and creating a subtle bug risk if called in a CLI/queue context where the current user is anonymous (uid=0), storing the PDF at `private://job_hunter/resumes/0/tailoredresumes/`. (4) No file size limit on the generated PDF before `saveData()` — a maliciously large resume with hundreds of experiences could generate a large PDF consuming disk space.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 11)
- Feature/work item: PDF resume generation — `src/Service/ResumePdfService.php`

## Findings

### DCC-PDF-11-A — `$style_schema_name` not validated against whitelist — latent path traversal (Medium)
- **Surface:** `ResumePdfService::loadStyleSchema()` line 140
- **Evidence:**
  ```php
  $schema_path = $module_path . '/config/resume_styles/' . $name . '.json';
  ```
  No check that `$name` contains only safe characters or matches an allowed list.
- **Impact:** If any future caller passes a user-controlled `$style_schema_name`, an attacker could traverse out of the `resume_styles/` directory (e.g., `../../settings`). Currently all callers use the hardcoded default — safe today.
- **Mitigation (minimal diff):**
  ```php
  // At start of loadStyleSchema():
  if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
    $this->logger->error('Invalid style schema name: @name', ['@name' => $name]);
    return FALSE;
  }
  ```
- **Verification:** Pass `$style_schema_name = '../../etc/passwd'` → function returns FALSE; no file outside `resume_styles/` is read.

### DCC-PDF-11-B — Resume URL fields embedded in PDF without scheme validation (Medium)
- **Surface:** Lines 364, 381, 390 — `$this->pdf->Cell(... $url)` where `$url` comes from `$website['url']` and `$linkedin['url']` in AI-extracted resume JSON
- **Impact:** A `javascript:` URI stored in the resume's website URL field would be embedded as a clickable hyperlink in the generated PDF. While most modern PDF readers block JS execution, this is a known attack surface in older Acrobat versions and embedded PDF viewers.
- **Mitigation (minimal diff):**
  ```php
  // Add helper before using $url as a link target:
  $safe_url = (preg_match('#^https?://#i', $url)) ? $url : '';
  $this->pdf->Cell(0, 0, $text, 0, 1, 'C', FALSE, $safe_url);
  ```
- **Verification:** Set `$website['url'] = 'javascript:alert(1)'` in test resume data → generated PDF contains no clickable link for that entry.

### DCC-PDF-11-C — `\Drupal::currentUser()` static call in queue context may assign PDF to uid=0 (Low)
- **Surface:** `generateAndSavePdf()` line 116: `$uid = $userId ?? \Drupal::currentUser()->id();`
- **Impact:** If called from a queue worker without an explicit `$userId`, the Drupal current user in a CLI/cron context is anonymous (uid=0). PDF would be stored at `private://job_hunter/resumes/0/tailoredresumes/`, potentially misattributed or inaccessible to the correct user.
- **Mitigation:** Require `$userId` to be explicitly passed; throw `\InvalidArgumentException` if NULL:
  ```php
  if ($userId === NULL) {
    throw new \InvalidArgumentException('userId must be explicitly provided; do not rely on current user context.');
  }
  $uid = $userId;
  ```
- **Verification:** Call `generateAndSavePdf()` without `$userId` → exception thrown. Confirm all existing callers pass an explicit `$userId`.

## Next actions
- Executor: Create 1 follow-up in `sessions/pm-forseti/inbox/` for DCC-PDF-11-A + DCC-PDF-11-B (bundle: schema name whitelist + URL scheme validation), ROI 5.
  - Title: `Harden ResumePdfService: schema name whitelist + URL scheme validation`
  - File: `sites/forseti/web/modules/custom/job_hunter/src/Service/ResumePdfService.php`
  - Patch: Add `preg_match` check at loadStyleSchema() entry; add `preg_match('#^https?://#i', $url)` guard before all `Cell()` link targets at lines 364, 381, 390
  - Verification: Test with path traversal schema name → FALSE returned; test with `javascript:` URL → no clickable link in PDF
  - `roi.txt`: `5`

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Path traversal in schema loading is a latent risk requiring a 2-line regex guard. URL scheme validation in PDF generation is a defense-in-depth measure against javascript: URI injection in PDF readers. Both are low-effort fixes with meaningful security value before GA.
