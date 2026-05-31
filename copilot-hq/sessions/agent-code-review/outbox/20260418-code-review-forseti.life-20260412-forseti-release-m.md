- Status: done
- Verdict: BLOCK
- Summary: Pre-ship code review completed for forseti.life release 20260412-forseti-release-m. Release window: commits in `sites/forseti/` since last completed review cutoff (`1537dcbe4`, release-j push) through coordinated push `3fab57647` (2026-04-18). 6 HIGH and 1 MEDIUM finding. All 6 HIGH findings are CSRF seed/delivery bugs that will produce 100% 403 failures on new job_hunter routes shipped in this release.

After dev fixes are committed, a targeted re-review of `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` and `sites/forseti/web/modules/custom/job_hunter/job_hunter.install` is required before APPROVE can be issued.

---

## Release scope

- **Commits reviewed:** `1537dcbe4..3fab57647` filtered to `sites/forseti/`
- **Primary new modules:** `community_incident_report` (new), `ai_conversation` (Ollama provider added), `job_hunter` (8+ new features: company interest/research tracker, contact tracker, offer tracker, resume source link, analytics dashboard, offer tracker, resume version labeling, contact-referral tracker)
- **Prior review note:** The 2026-04-14 null-commit APPROVE (`1a73a4a01`) covered only the dungeoncrawler inbox item; the forseti-specific implementation commits were never reviewed.

---

## Checklist results

| Check | Result |
|---|---|
| Missing file/arg guards | N/A — no shell scripts reviewed |
| Unhandled subprocess exit codes | N/A |
| Log directory placement | N/A |
| GNU-only filesystem calls | N/A |
| Silent `\|\| true` on critical path | N/A |
| Hardcoded absolute paths | PASS — `COPILOT_HQ_ROOT` fallback is `/home/ubuntu/forseti.life/copilot-hq` (correct production path) |
| Idempotency / leftover state | PASS |
| Drupal CSRF: `_csrf_token` not on GET routes | PASS — all new POST routes are `methods: [POST]` only |
| Drupal CSRF: route-path seed | **FAIL (6 routes)** — see findings below |
| Drupal CSRF: token delivery channel (HTML form) | PASS — Form API routes use built-in CSRF |
| Drupal CSRF: token delivery channel (JS fetch) | **FAIL** — `resume_source_save` sends token in `X-CSRF-Token` header |
| Stale private duplicates of canonical data | PASS — `CONTACT_RELATIONSHIP_TYPES`, `CONTACT_REFERRAL_STATUSES`, `COMPANY_INTEREST_STATUSES` are defined once in `CompanyController.php` with no divergent copies |
| Schema hook pairing | **FAIL (MEDIUM)** — 4 new tables missing from `hook_install()` |
| Drupal `_method: 'POST'` in requirements | PASS — all new routes use `methods: [POST]` at route level |
| Authorization bypass on override params | PASS — all new save methods derive `$uid` from `currentUser()` and enforce ownership |
| Environment path fallbacks | PASS |
| Unparameterized SQL JSON_EXTRACT key names | N/A — no new JSON_EXTRACT patterns introduced |
| Multi-site fork parity (`ai_conversation`) | PASS in new code — Ollama commit uses `$this->buildBedrockClient()` for new paths; `invokeModelDirect()` inline construction is pre-existing and out of scope for this window |
| QA permissions registry | N/A — no dungeoncrawler routes |
| `_csrf_request_header_mode` on dungeoncrawler POST routes | N/A — forseti-only release |

---

## Findings

### Finding 1 — HIGH: CSRF seed uses route NAME instead of route PATH — `company_interest_save`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (approx. line 3500)
**Introduced in:** `207f397f5` feat(job-hunter): add company interest tracker (2026-04-12)
**Problem:** CSRF token is generated with seed `'job_hunter.company_interest_save'` (the Drupal route machine name). `CsrfAccessCheck::access()` validates against `$request->query->get('token')` by comparing with the **route path** seed, not the route name. The actual route path is `/jobhunter/companies/{company_id}/interest/save`. Every POST to this endpoint will return 403 for all users.
**Evidence:** `CsrfAccessCheck.php` line 59: `$this->csrfToken->validate($request->query->get('token', ''), $path)` where `$path` is the rendered route path without leading slash. KB ref: `org-chart/agents/instructions/agent-code-review.instructions.md` checklist item "CSRF route-path seed" (FR-RB-01 lesson).
**Recommended fix:** Replace `\Drupal::csrfToken()->get('job_hunter.company_interest_save')` with `\Drupal::csrfToken()->get('jobhunter/companies/' . $company_id . '/interest/save')`.

---

### Finding 2 — HIGH: CSRF seed is literal `'session'` — `company_research_save`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (approx. line 3747)
**Introduced in:** `0b14be6a9` feat(job-hunter): add company research tracker (2026-04-12)
**Problem:** CSRF token generated with seed `'session'`. Route path is `/jobhunter/companies/{company_id}/research/save`. The seed has no relationship to the route path. Every POST will return 403.
**Evidence:** Same `CsrfAccessCheck` path validation as Finding 1. KB ref: checklist "CSRF route-path seed".
**Recommended fix:** Replace `\Drupal::csrfToken()->get('session')` with `\Drupal::csrfToken()->get('jobhunter/companies/' . $company_id . '/research/save')`. Update `$form_action` to use `Url::fromRoute('job_hunter.company_research_save', ['company_id' => $company_id])->toString()` instead of hardcoded string.

---

### Finding 3 — HIGH: CSRF seed uses route NAME — `contacts_save`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (approx. line 4125)
**Introduced in:** `a39967c83` feat(job-hunter): add contact tracker schema and routes (2026-04-12)
**Problem:** Seed `'job_hunter.contacts_save'` (route name). Route path is `/jobhunter/contacts/save`. Every contact create/update POST returns 403.
**Evidence:** Same as Finding 1.
**Recommended fix:** Replace with `\Drupal::csrfToken()->get('jobhunter/contacts/save')`.

---

### Finding 4 — HIGH: CSRF seed uses wrong format — `contacts_delete`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (approx. line 4022)
**Introduced in:** `a39967c83` feat(job-hunter): add contact tracker schema and routes (2026-04-12)
**Problem:** Seed `'job_hunter.contacts_delete.' . $ct->id` (route name + dot-id). Route path is `/jobhunter/contacts/{contact_id}/delete`. CsrfAccessCheck renders the path as `'jobhunter/contacts/' . $contact_id . '/delete'`. The dot-separator format will never match. Every contact delete POST returns 403.
**Evidence:** Same `CsrfAccessCheck` path validation. KB ref: checklist "CSRF route-path seed".
**Recommended fix:** Replace with `\Drupal::csrfToken()->get('jobhunter/contacts/' . $ct->id . '/delete')`.

---

### Finding 5 — HIGH: CSRF seed uses route NAME — `contact_job_link_save`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (approx. line 4164)
**Introduced in:** `a39967c83` feat(job-hunter): add contact tracker schema and routes (2026-04-12)
**Problem:** Seed `'job_hunter.contact_job_link_save'` (route name). Route path is `/jobhunter/contacts/{contact_id}/link-job`. Every contact-job link save POST returns 403.
**Evidence:** Same as Finding 1.
**Recommended fix:** Replace with `\Drupal::csrfToken()->get('jobhunter/contacts/' . $contact_id . '/link-job')`.

---

### Finding 6 — HIGH: CSRF token sent in `X-CSRF-Token` header instead of `?token=` query param — `resume_source_save`

**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php` (approx. line 2118)
**Introduced in:** `05ebf6273` feat(job_hunter): resume version labeling (2026-04-12)
**Problem:** The inline JS `fetch()` call sends the CSRF token as `"X-CSRF-Token": csrf_token` in the request headers. The route `job_hunter.resume_source_save` uses `_csrf_token: 'TRUE'`, which means Drupal's `CsrfAccessCheck` will validate against `$request->query->get('token')` only — it does not read from headers. Every POST to this endpoint returns 403. Note: the seed itself (`'jobhunter/jobs/' . $job_id . '/resume-source/save'`) is correct; only the delivery channel is wrong.
**Evidence:** `CsrfAccessCheck.php` line 59. KB ref: `knowledgebase/lessons/20260405-drupal-csrf-split-route-pattern.md` and checklist "JS fetch/XHR check (HIGH risk)".
**Recommended fix:** Append token to the URL instead of the header: `fetch(saveUrl + '?token=' + encodeURIComponent(csrfToken), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: ... })`. The `X-CSRF-Token` header line should be removed.

---

### Finding 7 — MEDIUM: New job_hunter tables missing from `hook_install()` — fresh-install gap

**File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.install` (hook_install block, approx. lines 39–88)
**Introduced across:** `207f397f5` (company interest), `0b14be6a9` (company research), `a39967c83` (contacts), `b70d7148a` (offers)
**Problem:** Four new tables — `jobhunter_company_interest`, `jobhunter_company_research`, `jobhunter_contacts`, `jobhunter_offers` — are created only via `hook_update_N()` (9048, 9049, 9050, 9058 respectively) and are not added to `job_hunter_install()`. Per the job_hunter module exception documented in the checklist: new tables must be covered by BOTH `hook_install()` (for fresh installs) AND `hook_update_N()` (for upgrades). The production site is not affected by this gap (it upgrades via `drush updb`), but staging/dev environments re-installed from scratch would be missing these tables, causing all four features to fail on fresh install.
**Evidence:** `grep -n "_create_company_interest_table\|_create_company_research_table\|_create_contacts_table\|_create_offers_table" job_hunter.install` — none of these are called from `job_hunter_install()`. KB ref: checklist "schema hook pairing" job_hunter exception.
**Recommended fix:** Add `_job_hunter_create_company_interest_table()`, `_job_hunter_create_company_research_table()` calls to `job_hunter_install()`. For contacts and offers (which have no standalone `_create_` helper), extract the `createTable` block from `job_hunter_update_9050` and `job_hunter_update_9058` into `_job_hunter_create_contacts_table()` / `_job_hunter_create_offers_table()` helpers and call both from `job_hunter_install()`.

---

## Checklist items explicitly N/A for this release

- Data-only fast-path: not applicable (routing, controller, schema, and install files all changed)
- `EquipmentCatalogService VALID_TYPES` pairing: dungeoncrawler-only
- QA permissions registry: dungeoncrawler-only
- `_method: 'POST'` in requirements: verified all new routes use `methods:` at route level correctly

---

## KB references

- `knowledgebase/lessons/20260405-drupal-csrf-split-route-pattern.md` — CSRF seed and delivery channel patterns
- `knowledgebase/lessons/20260227-jobhunter-e2e-csrf-token-empty-save-job.md` — prior CSRF seed fix history for job_hunter
- Checklist item: "CSRF route-path seed (FR-RB-01)" in `org-chart/agents/instructions/agent-code-review.instructions.md`

---

- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260418-code-review-forseti.life-20260412-forseti-release-m
- Generated: 2026-04-18T17:45:00+00:00
