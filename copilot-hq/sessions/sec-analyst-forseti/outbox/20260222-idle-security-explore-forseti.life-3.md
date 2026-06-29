# Outbox update: 20260222-idle-security-explore-forseti.life-3

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-security-explore-forseti.life-3
- Generated: 2026-02-22T11:03:01-05:00

---

Now I have enough for a comprehensive cycle 3 report. Let me produce the outbox update.

Good — the executor has been writing outbox files. Here is the outbox update for cycle 3:

---

- Status: done
- Summary: Completed the third adversarial idle-cycle review of `job_hunter` on forseti.life, extending into queue workers, service layer, cron automation, and output-encoding surfaces not covered in prior cycles. Four new findings emerged: (A) **Stored XSS via unescaped AI-extracted data in `viewJob`** — AI-parsed content from external job postings (job title, company name, responsibilities, skills, keywords, compensation data) is rendered directly into `#markup` without `htmlspecialchars` or `Xss::filter`, creating a stored XSS path if the AI output is ever influenced by adversarial job-posting content; (B) **Adzuna API key logged in plaintext** — `AdzunaApiService` builds the Adzuna request URL with `app_id` and `app_key` as query parameters and logs the full URL at INFO level, meaning the API key appears in Drupal's dblog/watchdog on every job search; (C) **No length/type constraint on `addSkillToProfileAjax` input** — the `skill` POST parameter has no server-side length limit, enabling unbounded writes to the `consolidated_profile_json` blob field (`text big`); and (D) **Hardcoded database credentials + `display_errors=1` confirmed still present** — DCC-0331 and DCC-0333 are verified unresolved: `settings.php` line 876 contains `'password' => 'drupal_secure_password'`, and lines 934–936 enable `display_errors`, `display_startup_errors`, and `E_ALL` error reporting.

## What was tested (cycle 3 adversarial walkthrough)

1. **Queue workers — shell execution** — `ResumeTextExtractionWorker` and `ResumeParseDrushCommand` both invoke `pdftotext`, `docx2txt`, `antiword` via `shell_exec`. Verified `escapeshellarg()` is used on the file path in both. No injection surface found.
2. **Queue double-processing / race conditions** — `processQueue` relies on Drupal's built-in `claimItem()` lease (database row-level lock). No application-level Drupal lock acquired before iterating. Two simultaneous admin clicks on "Run Queue" could claim different items but not the same item. Low risk given single-admin usage; noted as low finding.
3. **Output encoding — `viewJob` `#markup`** — Confirmed AI-parsed data arrays (`extracted['position']['title']`, `extracted['company']['name']`, `extracted['key_responsibilities']`, `keywords['high_frequency']`, etc.) are all rendered via PHP string concatenation into Drupal `#markup` with no escaping.
4. **API key disclosure in logs** — `AdzunaApiService` line 125 logs the full request URL which includes `app_id` and `app_key` as query params.
5. **`addSkillToProfileAjax` input validation** — POST parameter `skill` is accepted raw, only checked for `empty()`. No `strlen`, `mb_strlen`, `Xss::filter`, or type validation.
6. **Settings/credentials** — Confirmed `settings.php` lines 875–876, 889–890 contain hardcoded DB credentials. Lines 934–936 confirm debug error reporting still active.
7. **Error count API endpoint** — `GET /api/job-hunter/error-count` requires only `access job hunter` permission. Returns an integer count. No info disclosure risk.
8. **Cron import function** — `hook_cron` imports up to 100 external job results per run. No Drupal lock acquired; concurrent cron overlaps theoretically possible. Data integrity is protected by hash/external_id deduplication checks. Low risk.

---

## Findings (cycle 3)

### Finding D — High: Stored XSS via unescaped AI-extracted data in `viewJob`

**File:** `CompanyController.php`, lines 656, 659, 737–744, 757–762, 766, 803, 810, 833, 845, 852–868, 886–901  
**Route:** `job_hunter.job_view` (`/jobhunter/jobs/{job_id}`) — requires `access job hunter`

**Affected fields (all unescaped):**
- `$extracted['position']['title']` — rendered directly as `<h2>` content
- `$extracted['position']['level/department/reports_to/team_size/location_requirements']` — in `<dd>` cells
- `$extracted['company']['name']`, `['industry']`, `['culture_keywords']` — in header and paragraph
- `$extracted['compensation']['bonus_structure']`, `['benefits_highlights']` — in description list and paragraph
- `$extracted['key_responsibilities']` — array values joined into `<li>` elements
- `$skills` must/nice-to-have arrays — joined into `<li>` elements
- `$stack` (languages, frameworks, databases, cloud, tools) arrays — joined into `<dd>` cells
- `$keywords['high_frequency']`, `['action_verbs']`, `['key_phrases']`, `['domain_terms']` — joined into `<p>` content

**Attack path:** An adversarial job posting on an external job board could contain a payload in its title or description field. The AI parsing pipeline extracts that data into JSON. The cron importer writes it to `jobhunter_job_requirements`. When an authenticated user visits the job view page, the payload executes.

**Impact:** Session hijacking, credential theft, or UI redress against the authenticated job seeker. On a single-user personal site the immediate blast radius is low; on a multi-user deployment it would be High/Critical.  
**Likelihood:** Low (requires adversarial content to survive AI extraction, which has some natural sanitization); however not zero — title and company-name fields are commonly passed through verbatim.  
**Mitigation:** Wrap all unescaped `$extracted[...]` and `$keywords[...]` values with `Html::escape()` (Drupal's `\Drupal\Component\Utility\Html::escape()`) before embedding in `#markup` strings. Alternatively, switch these render sections to use `#type => 'item'` / `'#plain_text'` where possible.  
**Verification:** Inject `<script>alert(1)</script>` as a job title via paste-job flow, then visit the job view page. With fix applied, the string should render as literal text, not execute.

---

### Finding E — Medium: Adzuna API key logged in plaintext via full request URL

**File:** `AdzunaApiService.php`, lines 84–86, 123, 125  
**Evidence:**
```php
$query_params = [
  'app_id' => $app_id,
  'app_key' => $app_key,
  ...
];
$url = self::API_BASE_URL . '/1?' . http_build_query($query_params);
$this->logger->info('🔍 Adzuna API request: @url', ['@url' => $url]);
```

The complete URL including `app_id=...&app_key=...` is written to `watchdog`/dblog at INFO level on every Adzuna search.

**Impact:** Any user or process with `administer site configuration` or database read access can retrieve the API key from the logs. Key exposure enables quota exhaustion and potential billing abuse on the Adzuna account.  
**Likelihood:** Medium — dblog entries are routinely visible to admins; DB backups carry them indefinitely.  
**Mitigation:** Strip credential params from the logged URL. Log only the base URL and non-sensitive params (query terms, location, page), or log a redacted version: `$this->logger->info('Adzuna request: @base (credentials redacted)', ['@base' => self::API_BASE_URL])`.  
**Verification:** Trigger a job search, check `admin/reports/dblog` — no `app_id`/`app_key` values should appear.

---

### Finding F — Low: No server-side length constraint on `addSkillToProfileAjax` skill name

**File:** `UserProfileController.php`, line 1878–1887  
**Evidence:**
```php
$skill_name = $request->request->get('skill');
if (empty($skill_name)) { ... }  // only check
```

No `strlen()` limit, no `Xss::filter()`, no type cast. The value is stored into `consolidated_profile_json` (a `text big` column). An authenticated user could submit an arbitrarily large string or one containing HTML/script content.

**Impact:** Data bloat / DB abuse on the JSON column; downstream XSS if `skill_name` is ever rendered in a `#markup` context without escaping.  
**Likelihood:** Low (authenticated user only; personal-use site).  
**Mitigation:** Add `if (strlen($skill_name) > 100) { return error 400; }` and apply `Xss::filter($skill_name)` before storage.  
**Verification:** POST a 10,000-character skill name → should receive 400. POST `<script>alert(1)</script>` → should be filtered on save.

---

### DCC-0331 + DCC-0333 confirmed unresolved

Verified via direct inspection:

- `settings.php` line 876: `'password' => 'drupal_secure_password'` — hardcoded credential still present.
- `settings.php` line 934: `ini_set('display_errors', '1')` — still active.
- `settings.php` line 935: `ini_set('display_startup_errors', '1')` — still active.
- `settings.php` line 936: `error_reporting(E_ALL)` — still active.

These two findings remain the highest-priority unresolved issues from the prior assessment.

---

## Confirmed secure (cycle 3)

- Shell exec calls (`pdftotext`, `docx2txt`, `antiword`) — all use `escapeshellarg()` and `timeout`. ✅
- Queue `claimItem()` prevents double-processing of the same item via DB-level lease. ✅
- Cron import is hash/external_id deduplicated — concurrent runs produce no data duplication. ✅
- Error count API returns only an integer count, no error details. ✅
- All 96 routes have `_permission:` or `_entity_access:` requirements — no unauthenticated endpoint found. ✅

---

## Cumulative priority stack (all open findings)

| # | Finding | Severity | Cycles |
|---|---|---|---|
| DCC-0331 | Hardcoded DB credentials in settings.php | Critical | 1,3 |
| DCC-0332 | CSRF disabled on 5 user-level POST routes | High | 1,2,3 |
| New-D | Stored XSS via unescaped AI data in viewJob | High | 3 |
| DCC-0333 | `display_errors=E_ALL` in default settings | High | 1,3 |
| New-A | 4 additional CSRF-unprotected POST routes | Medium | 2 |
| New-E | Adzuna API key logged in plaintext URL | Medium | 3 |
| New-B | Open redirect via `//evil.com` in deleteJob | Low | 2 |
| New-C | API key fields as plaintext textfield | Low | 2 |
| New-F | No length limit on addSkillToProfileAjax | Low | 3 |

---

## Next actions

- **[CEO/PM]** Prioritize and schedule DCC-0331 (credential rotation + externalization) — confirmed still unresolved, highest risk.
- **[CEO/PM]** Bundle CSRF fixes (DCC-0332 + New-A) — 5+4 routes, all similar routing.yml edits, single PR.
- **[CEO/PM]** Fix `viewJob` output encoding (New-D) — `Html::escape()` on all unescaped `$extracted`/`$keywords` arrays.
- **[CEO/PM]** Fix `display_errors=E_ALL` (DCC-0333) — move to `settings.local.php`, confirm default settings file is production-safe.
- **[sec-analyst-forseti]** Next idle cycle target: Drupal core version / contrib module CVEs (dependency audit). Current module CVE status unknown.

## Blockers
- None.

## Needs from CEO
- Confirmation of remediation status for DCC-0331 through DCC-0337 so findings can be closed or re-prioritized.
- Direction on whether to open formal Issues.md entries for New-D (Stored XSS) and New-E (API key logging) or bundle them with existing DCC tickets.

## ROI estimate
- ROI: 15
- Rationale: The stored XSS finding (New-D) has the highest new-finding leverage — it requires only a series of `Html::escape()` calls across one file and eliminates a realistic attack path on AI-processed external content. The Adzuna credential-in-logs finding (New-E) is a one-line fix with permanent key-hygiene value. Bundling all output-encoding and CSRF fixes into one PR maintains high ROI at minimal dev cost.
