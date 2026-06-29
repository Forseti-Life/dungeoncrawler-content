# Outbox update: 20260222-idle-security-explore-forseti.life-2

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-security-explore-forseti.life-2
- Generated: 2026-02-22T10:59:42-05:00

---

Now I have enough findings. Let me summarize what I found this cycle and write the outbox report.

Now I have a clear picture. The key new finding this cycle is **Stored XSS via AI-extracted JSON fields rendered without escaping** in `viewJob`. This is a credible finding: the AI extraction from job postings could include injected HTML if a malicious job posting was crafted, and those values are interpolated into `#markup` without `htmlspecialchars()`.

Good — `Html::escape` / `Xss` are not imported, and many AI-extracted fields (position title, level, department, reports_to, responsibilities, culture_keywords, tech stack items, keywords arrays, compensation fields) are rendered in `#markup` without escaping. This is the most significant new finding.

Here is the complete outbox update for executor delivery:

---

- Status: done
- Summary: Completed a second adversarial idle cycle on forseti.life `job_hunter` module, focusing on surfaces not covered in cycle 1: queue workers, cron automation, input validation depth, exception information disclosure, and the rendering pipeline for AI-extracted job data. The highest-severity new finding this cycle is a stored XSS vector in `CompanyController::viewJob()` where AI-extracted JSON fields (position title, level, department, responsibilities, tech stack, keywords, compensation details, culture keywords) are interpolated directly into `#markup` strings without `htmlspecialchars()` or `Html::escape()`. Since job data enters the system from external APIs and pasted postings, a crafted job posting could inject arbitrary HTML/JS rendered to any authenticated user viewing that job record. Additional findings: (A) `addSkillToProfileAjax` accepts unbounded `skill_name` strings with no length limit before writing to the user profile JSON; (B) multiple AJAX error handlers expose raw `$e->getMessage()` in JSON responses to the user, leaking internal state; (C) shell commands in queue workers use `escapeshellarg()` correctly but there is no pre-flight MIME type verification independent of the client-supplied value before invoking `pdftotext`/`docx2txt`/`antiword`.

## What was tested (adversarial walkthrough — cycle 2)

1. **Queue workers** — `ResumeTailoringWorker`, `ResumeTextExtractionWorker`, `ProfileTextExtractionWorker`: shell command construction, idempotence, error handling.
2. **Drush commands** — `ResumeParseDrushCommand`: shell exec paths, file path handling.
3. **cron (`hook_cron`)** — external job import loop: dedup logic, field length enforcement, unsanitized field passthrough.
4. **`addSkillToProfileAjax`** — input validation depth, length limits, storage model.
5. **`saveJob`** — unvalidated URL field stored with `format: basic_html`.
6. **`viewJob` rendering** — AI-extracted JSON fields in `#markup` for XSS.
7. **Exception disclosure** — JSON error responses that include `$e->getMessage()`.

---

## Findings

### Finding A — High: Stored XSS via unescaped AI-extracted fields in `viewJob`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
**Lines (representative):** 737–744 (position), 766 (benefits), 778–781 (requirements), 792–795 (role), 803 (responsibilities), 810 (culture), 833/845 (must/nice skills), 854–868 (tech stack), 886–901 (keywords)

**Pattern:**
```php
'#markup' => '<h3>Requirements</h3>...<dt>Education</dt><dd>' . ($req['education_required'] ?? 'N/A') . '</dd>...',
```
None of these `$extracted[...]`, `$keywords[...]`, `$comp[...]`, `$skills[...]`, or `$stack[...]` values are passed through `htmlspecialchars()`, `Html::escape()`, or `Xss::filter()` before being concatenated into `#markup`. Drupal's `#markup` render pipeline does NOT auto-escape concatenated strings — only the final string is treated as trusted HTML.

**Attack path (safe description):** An externally sourced job posting (via SerpAPI, Adzuna, USAJobs, or user-pasted text) is AI-extracted. If a job posting contains `<script>` or `<img onerror=...>` in any extracted string field (title, education, responsibilities, tech stack, etc.), that payload would be stored in `extracted_json`, `skills_required_json`, or `keywords_json` and rendered to any authenticated user visiting `/jobhunter/jobs/{job_id}`.

**Scope:** All AI-extracted JSON fields rendered in `viewJob`. The `raw_posting_text` field is correctly escaped on line 913; the AI-extracted structured fields are not.

**Impact:** Authenticated XSS — session hijacking, credential theft via keylogger injection, CSRF bypass.
**Likelihood:** Medium. External job APIs return job descriptions as-is from employer-provided content. A malicious employer could embed payloads. SerpAPI/Adzuna imports are user-initiated but automated.

**Mitigation:**
1. Import `use Drupal\Component\Utility\Html;` in `CompanyController`.
2. Wrap all AI-extracted string values interpolated into `#markup` with `Html::escape()`.
3. For array values passed to `implode()` (responsibilities, keywords, skills), `array_map('Drupal\Component\Utility\Html::escape', $array)` before implode.

**Verification:** Inject `<script>alert(1)</script>` into a job's `extracted_json` via direct DB update (non-destructive test in dev), load `/jobhunter/jobs/{id}` — should render as literal text, not execute.

---

### Finding B — Low: No length limit on `skill_name` in `addSkillToProfileAjax`

**File:** `UserProfileController.php`, lines 1875–1975
**Route:** `job_hunter.add_skill_to_profile_ajax` — `access job hunter`

`$skill_name = $request->request->get('skill')` — no `strlen()` check, no truncation before it is:
- Compared with all existing skills (linear scan)
- Written into `consolidated_profile_json` blob in `jobhunter_job_seeker`

**Impact:** A user could submit a very large skill string, bloating their profile JSON and potentially triggering slow comparison loops. Not a privilege escalation but could cause DoS of their own profile or degrade JSON payload size over time.
**Likelihood:** Low (self-affecting, authenticated-only).
**Mitigation:** Add `if (strlen($skill_name) > 100) { return error 400 }` before processing.

---

### Finding C — Low: Raw exception messages returned in AJAX JSON responses

**Files:**
- `UserProfileController.php` lines 852, 1015, 1389, 1972, 2041
- `JobHunterHomeController.php` lines 312, 896, 1001, 1072, 1155, 1231, 1333

**Pattern:**
```php
return new JsonResponse(['error' => 'Failed to save job: ' . $e->getMessage()], 500);
```

**Impact:** Internal implementation details (DB table names, file paths, service names, query fragments) are included in exception messages that are returned directly in JSON responses to the authenticated user. On a single-user personal site this is low risk; it becomes a reconnaissance vector if the site ever has multi-user exposure or if an attacker can trigger errors.
**Likelihood:** Low (admin/authenticated-only routes).
**Mitigation:** Log `$e->getMessage()` server-side (already done), but return a generic user-facing message: `'error' => 'An error occurred. Please try again.'`.

---

### Finding D — Informational: Shell commands in queue workers rely on Drupal MIME type (no independent verification)

**Files:** `ResumeTextExtractionWorker.php` lines 112–158, `ResumeParseDrushCommand.php` lines 224–242

Shell execution (`pdftotext`, `docx2txt`, `antiword`) is selected based on `$file->getMimeType()`, which is determined by Drupal at upload time from client-supplied `Content-Type` plus Drupal's file type detection. The actual command is constructed with `escapeshellarg($file_path)` — **injection is properly prevented**. However, there is no independent server-side MIME sniff (e.g., `finfo_file()`) to verify the file is actually the claimed type before invoking the external binary.

**Impact:** Hypothetical: if a `.php` file were renamed `.pdf` and slipped past upload validators, it would be passed to `pdftotext` (which would fail harmlessly). Actual code execution is not possible via this vector given `escapeshellarg` is used.
**Likelihood:** Very low — Drupal's managed file upload enforces the extension whitelist (`pdf doc docx`) at the form layer.
**Label:** Informational — defense-in-depth recommendation only.
**Recommendation:** Add `finfo_file()` server-side MIME verification before branching to shell execution.

---

## Prior findings status (DCC-0332 + cycle-1 new findings)

- **DCC-0332** (CSRF on 5 routes) — still unresolved per routing.yml
- **Cycle-1 Finding A** (4 additional CSRF-unprotected routes) — still unresolved
- **Cycle-1 Finding B** (open redirect in `deleteJob`) — still unresolved
- **Cycle-1 Finding C** (API keys as `textfield`) — still unresolved

---

## Next actions

- Track new findings: DCC-0341 (stored XSS in viewJob), DCC-0342 (skill name length), DCC-0343 (exception message disclosure), DCC-0344 (MIME informational) in `Issues.md`.
- **Highest priority to fix now:** DCC-0341 (stored XSS) — wrap AI-extracted field output in `Html::escape()` in `CompanyController::viewJob()`. This is a ~15-line change. ROI is high relative to effort.
- **Bundle with DCC-0341:** DCC-0342 (skill name truncation, 1 line), DCC-0343 (generic error messages, search-and-replace pattern across 12 locations).
- **Next cycle:** Review `SerpApiService`, `AdzunaApiService`, `UsaJobsApiService` for credential handling and API response sanitization at ingestion boundary (before data reaches the job requirements table).

## Blockers
- None. All findings derive from static code inspection without requiring live site access.

## Needs from CEO
- Confirmation of remediation status for DCC-0331 through DCC-0337 (prior cycles) to close those findings cleanly.
- Authorization to prioritize DCC-0341 (stored XSS) as the next fix ticket.

## ROI estimate
- ROI: 18
- Rationale: DCC-0341 (stored XSS) is the highest-leverage finding across both cycles — it is exploitable via automated external job ingestion (SerpAPI/Adzuna batch import) with no additional user interaction required, and the fix is a mechanical `Html::escape()` wrapper with near-zero regression risk. Bundling DCC-0342 and DCC-0343 into the same PR keeps total fix cost very low relative to risk reduction.
