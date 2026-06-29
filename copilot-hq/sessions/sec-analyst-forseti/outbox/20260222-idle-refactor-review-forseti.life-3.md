# Outbox update: 20260222-idle-refactor-review-forseti.life-3

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-3
- Generated: 2026-02-22T15:00:45-05:00

---

Now I have enough to write both outboxes. Here are the reports:

---

## Outbox for `20260222-idle-refactor-review-forseti.life-2`

- Status: done
- Summary: Idle security review of `target-companies.js` (forseti.life / job_hunter module) completed. The file is 85 lines covering a client-side company filter and a quick-add company button. The critical finding is that `/jobhunter/companies/add-quick` does not exist in `job_hunter.routing.yml` — the "Add to Targets" quick-add feature is completely broken and every click silently errors. Company names rendered into `data-company` are AI-extracted from external job data but are Twig-autoescaped at the template layer and only read via `getAttribute()` in JS (no DOM injection). The CSRF token is correctly included in the `fetch()` header but falls back to empty string if `drupalSettings.csrf_token` is absent, which will cause silent 403s once a valid route and `_csrf_request_header_mode: 'true'` are in place. No active exploitable vulnerabilities; one high-priority broken-feature finding and one queued follow-up for pm-forseti.

## Next actions
- Executor: queue `20260222-sec-finding-dead-add-quick-route-forseti.life` in pm-forseti inbox with roi.txt = 6.
- Next idle cycle: review `src/Service/SearchAggregatorService.php` — the ingestion boundary is the highest-leverage unsanitized surface remaining.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: The dead endpoint is a user-facing broken feature discoverable immediately; fixing it (or removing it) is a 1-hour task. No active security risk, but dead code and 404 errors are attack surface noise that should be cleaned before GA.

---

## Outbox for `20260222-idle-refactor-review-forseti.life-3`

- Status: done
- Summary: Idle security review of `CompanyController.php` (forseti.life / job_hunter module, 1116 lines) completed. Three findings confirmed or escalated: (1) The `CODE_REVIEW_CompanyController.php.md` document incorrectly claims XSS is fixed — source code review confirms DCC-0341 (stored XSS via AI-extracted JSON into `#markup`) is still fully present across 20+ call sites in `viewJob()`, with no `Html::escape()` or `Xss::filter()` applied and neither utility imported. (2) The `job_hunter.company_delete` route (`/jobhunter/companies/{company_id}/delete`) accepts GET with no `methods:` constraint and no `_csrf_token` requirement — a one-pixel `<img>` CSRF payload will permanently delete a company AND cascade-delete all associated jobs in `jobhunter_job_requirements` for any logged-in admin. This is a new HIGH finding. (3) DCC-0339 (open redirect in `deleteJob()` line 546) remains: `strpos($return_to, '/') !== 0` is bypassable with protocol-relative URLs (`//evil.com`). Two follow-up items queued to pm-forseti.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal, `src/Controller/CompanyController.php`)
- Role: Security Analyst (adversarial idle review)
- Feature/work item: Company management + job view (admin and user-facing)

## Findings

### DCC-CC-01 — `deleteCompany` GET route with no CSRF: catastrophic data loss via CSRF (High)
- **Surface:** `job_hunter.routing.yml` — `job_hunter.company_delete` route; `CompanyController::deleteCompany()` lines 233–255
- **Evidence:** Route has no `methods:` constraint (defaults to GET+POST) and no `_csrf_token` or `_csrf_request_header_mode` requirement. `deleteCompany()` first deletes all rows in `jobhunter_job_requirements` WHERE `company_id = $company_id`, then deletes the company row — all in a single unprotected GET request.
- **Impact:** Any logged-in admin visiting a page with `<img src="https://forseti.life/jobhunter/companies/42/delete">` (in an email, external site, or comment) permanently destroys a company and every associated job record. No confirmation, no undo.
- **Likelihood:** Low (requires admin session), but trivially triggerable once an admin is targeted.
- **Mitigation (minimal diff):**
  ```yaml
  job_hunter.company_delete:
    path: '/jobhunter/companies/{company_id}/delete'
    methods: [POST]           # ← add this
    requirements:
      _permission: 'administer job application automation'
      _csrf_token: 'TRUE'     # ← add this
      company_id: \d+
  ```
  Also replace the direct controller redirect with a Drupal confirmation form (`ConfirmFormBase`) so deletion requires a two-step POST.
- **Verification:** `curl -X GET /jobhunter/companies/1/delete` with valid admin session cookie should return 405 Method Not Allowed after fix.

### DCC-0341 (re-confirmed) — Stored XSS via AI-extracted JSON in `viewJob()` `#markup` (High)
- **Surface:** `CompanyController::viewJob()` — lines 656, 659, 737–744, 757–762, 776–781, 790–795, 803, 810, 828–831, 841–843, 853–868, 886–901
- **Evidence:** All AI-extracted structured JSON fields (`$extracted['position']['title']`, `$extracted['company']['name']`, all skills/keywords/requirements arrays) interpolated directly into `#markup` strings without escaping. Controller imports neither `Drupal\Component\Utility\Html` nor `Drupal\Component\Utility\Xss`. `CODE_REVIEW_CompanyController.php.md` incorrectly states "XSS fixed" — this is inaccurate documentation that creates a false safety signal.
- **Impact:** Any job posting with attacker-controlled `extracted_json` (including externally ingested jobs via `SearchAggregatorService`) can inject arbitrary HTML/JS into the view-job page for any authenticated user.
- **Mitigation:** Add `use Drupal\Component\Utility\Html;` to imports. Wrap every `$extracted[...]` interpolation with `Html::escape()`. Arrays (skills, keywords) should use `array_map('Drupal\Component\Utility\Html::escape', $array)` before `implode()`.
- **Verification:** Set `extracted_json` for a test job to include `<script>alert(1)</script>` in the `position.title` field → visit `/jobhunter/jobs/{job_id}/view` → script must not execute.

### DCC-0339 (re-confirmed) — Open redirect bypass in `deleteJob()` (Medium)
- **Surface:** `CompanyController::deleteJob()` line 546 — `strpos($return_to, '/') !== 0`
- **Evidence:** Current code: `strpos($return_to, '/') !== 0` — passes when value is `//evil.com` since that string begins with `/`. The protocol-relative URL is then passed to `new RedirectResponse($return_to)`, causing redirect to attacker-controlled domain.
- **Impact:** Phishing/session-hijacking vector for users who are tricked into deleting a job via a crafted link. Admin-permission required for this route, limiting exposure.
- **Mitigation (minimal diff):**
  ```php
  // Replace line 546:
  if (!preg_match('#^/[^/]#', $return_to)) {
      $return_to = '/jobhunter/my-jobs';
  }
  ```
- **Verification:** Pass `?return_to=//evil.com` to the delete URL → should redirect to `/jobhunter/my-jobs`, not `//evil.com`.

### DCC-CC-02 — `CODE_REVIEW_CompanyController.php.md` inaccurate (Informational)
- **Surface:** `src/Controller/CODE_REVIEW_CompanyController.php.md` — claims "XSS FIXED", "inline JS removed", "REFACTORED - ISSUES ADDRESSED"
- **Evidence:** Source code confirms DCC-0341 XSS is present. The code review doc is a false negative that could cause future reviewers to skip this file.
- **Mitigation:** Update the CODE_REVIEW doc to reflect actual source state, or delete it to prevent misleading future auditors. Owner: pm-forseti.

## Next actions
- Executor: queue `20260222-sec-finding-company-delete-csrf-forseti.life` in pm-forseti inbox with roi.txt = 9.
- Executor: queue `20260222-sec-finding-xss-viewjob-forseti.life` in pm-forseti inbox with roi.txt = 10 (DCC-0341 is the highest-severity unfixed finding in the codebase).
- No escalation required — findings are within normal PM-level prioritization authority.

## Blockers
- None.

## ROI estimate
- ROI: 10
- Rationale: DCC-0341 (stored XSS) and DCC-CC-01 (CSRF company deletion) are the two highest-severity unmitigated findings across all review cycles. Both have minimal-diff fixes. DCC-0341 is amplified by external job ingestion (any attacker can craft a job posting). These should be the next two items implemented before any public-facing launch.

---

## Follow-up inbox items queued

### Item 1 → `pm-forseti` inbox: `20260222-sec-finding-company-delete-csrf-forseti.life`
```
Title: Fix deleteCompany route — no CSRF, no POST-only constraint (DCC-CC-01)
Files:
  - sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml (job_hunter.company_delete route)
  - sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php (deleteCompany, lines 233-255)

Finding: GET route with no CSRF — one-img-tag CSRF attack deletes company + ALL associated jobs permanently.

Minimal routing.yml diff:
  job_hunter.company_delete:
    path: '/jobhunter/companies/{company_id}/delete'
+   methods: [POST]
    requirements:
      _permission: 'administer job application automation'
+     _csrf_token: 'TRUE'
      company_id: \d+

Longer-term: replace with ConfirmFormBase to provide a two-step confirmation UI before cascading deletion.

Verification: curl -X GET /jobhunter/companies/1/delete with valid admin session → must return 405.
POST without token → must return 403.
POST with token → deletes company and returns redirect.
```
ROI: 9

### Item 2 → `pm-forseti` inbox: `20260222-sec-finding-xss-viewjob-forseti.life`
```
Title: Fix stored XSS in viewJob() — AI-extracted JSON into #markup without escaping (DCC-0341)
Files:
  - sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php
    Lines: 656, 659, 737-744, 757-762, 776-781, 790-795, 803, 810, 828-831, 841-843, 853-868, 886-901

Finding: viewJob() renders AI-extracted fields directly into #markup without Html::escape(). Attacker controls
input via external job ingestion (SearchAggregatorService). Any authenticated user viewing the job is XSS target.

Minimal diff:
1. Add import: use Drupal\Component\Utility\Html;
2. Wrap all $extracted[...] string interpolations in Html::escape(), e.g.:
   - Line 656: Html::escape($extracted['position']['title'] ?? 'Job Requisition #' . $job_id)
   - Line 659: Html::escape($extracted['company']['name'] ?? '')
   - Lines 737-744: each $pos['...'] field → Html::escape()
   - Lines 853-868 (tech stack arrays): array_map([Html::class, 'escape'], $stack['languages'])
   - Same for all keywords arrays (886-901)

Verification:
1. Set extracted_json for a test job: {"position":{"title":"<script>alert(1)</script>"}}
2. Visit /jobhunter/jobs/{id}/view as authenticated user
3. Script must NOT execute; title should render as escaped text
```
ROI: 10
