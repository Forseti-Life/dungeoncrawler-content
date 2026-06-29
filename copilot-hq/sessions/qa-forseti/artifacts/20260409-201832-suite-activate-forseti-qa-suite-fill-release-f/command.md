# Suite Activation: forseti-qa-suite-fill-release-f

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T20:18:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-qa-suite-fill-release-f"`**  
   This links the test to the living requirements doc at `features/forseti-qa-suite-fill-release-f/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-qa-suite-fill-release-f-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-qa-suite-fill-release-f",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-qa-suite-fill-release-f"`**  
   Example:
   ```json
   {
     "id": "forseti-qa-suite-fill-release-f-<route-slug>",
     "feature_id": "forseti-qa-suite-fill-release-f",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-qa-suite-fill-release-f",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-qa-suite-fill-release-f

- Feature: forseti-qa-suite-fill-release-f
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

16 suite shells for 5 release-f shipped features. All 5 features confirmed deployed. Tests cover: static structural checks (grep/PHP lint/file existence), functional ACL checks (anonymous HTTP responses), and regression guards (site audit baseline).

## Prerequisites

- `FORSETI_BASE_URL` env var set (e.g., `https://forseti.life`)
- `ALLOW_PROD_QA=1` set when running site audit
- `python3 scripts/qa-suite-validate.py` available from HQ root

## Test cases

### TC-1: Suite manifest schema valid
- Suite: all
- Command: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0

### TC-2: bulk archive CSRF guard verified (DASH-static)
- Command: `grep -A8 "my_jobs_bulk_archive" sites/forseti/.../job_hunter.routing.yml | grep -q "_csrf_token"`
- Expected: exit 0

### TC-3: anon /jobhunter/my-jobs → 403 (DASH-functional)
- Command: `curl -s -w "%{http_code}" -o /dev/null ${FORSETI_BASE_URL}/jobhunter/my-jobs`
- Expected: 403

### TC-4: anon POST /jobhunter/my-jobs/bulk-archive → 403 (DASH-functional)
- Command: `curl -s -w "%{http_code}" -o /dev/null -X POST ${FORSETI_BASE_URL}/jobhunter/my-jobs/bulk-archive`
- Expected: 403

### TC-5: GoogleJobsSearchController PHP lint (GJ-static)
- Command: `php -l sites/forseti/.../Controller/GoogleJobsSearchController.php`
- Expected: exit 0

### TC-6: anon /jobhunter/googlejobssearch → 403 (GJ-functional)
- Command: `curl -s -w "%{http_code}" -o /dev/null ${FORSETI_BASE_URL}/jobhunter/googlejobssearch`
- Expected: 403

### TC-7: non-integer job_id /jobhunter/googlejobsdetail/notanid → 404 (GJ-functional)
- Command: `curl -s -w "%{http_code}" -o /dev/null ${FORSETI_BASE_URL}/jobhunter/googlejobsdetail/notanid`
- Expected: 404

### TC-8: ProfileCompletenessService PHP lint (PC-static)
- Command: `php -l sites/forseti/.../Service/ProfileCompletenessService.php`
- Expected: exit 0

### TC-9: anon /jobhunter → 403 (PC-functional)
- Command: `curl -s -w "%{http_code}" -o /dev/null ${FORSETI_BASE_URL}/jobhunter`
- Expected: 403 or 302

### TC-10: job_tailoring_save_resume has methods:[POST] (RT-static)
- Command: `grep -A6 "job_tailoring_save_resume:" .../job_hunter.routing.yml | grep -q "methods.*POST"`
- Expected: exit 0

### TC-11: job_tailoring_save_resume has _csrf_token (RT-static OQ-1)
- Command: `grep -A8 "job_tailoring_save_resume:" .../job_hunter.routing.yml | grep -q "_csrf_token"`
- Expected: exit 0
- Note: CURRENTLY FAILING — `_csrf_token: 'TRUE'` missing; dev-forseti must add before this passes

### TC-12: anon /jobhunter/jobtailoring/1 → 403 (RT-functional)
- Command: `curl -s -w "%{http_code}" -o /dev/null ${FORSETI_BASE_URL}/jobhunter/jobtailoring/1`
- Expected: 403

### TC-13: forseti-chat.html.twig exists (AI-static)
- Command: `test -f sites/forseti/.../ai_conversation/templates/forseti-chat.html.twig && echo PASS`
- Expected: PASS

### TC-14: anon GET /forseti/chat → 403 (AI-acl)
- Command: `curl -s -w "%{http_code}" -o /dev/null ${FORSETI_BASE_URL}/forseti/chat`
- Expected: 403

### TC-15: anon POST /ai-conversation/send-message without CSRF → 403 (AI-csrf-post)
- Command: `curl -s -w "%{http_code}" -o /dev/null -X POST ${FORSETI_BASE_URL}/ai-conversation/send-message`
- Expected: 403

### TC-16: send_message has _csrf_token: 'TRUE' (AI-static)
- Command: `grep -A8 "^ai_conversation.send_message:" .../ai_conversation.routing.yml | grep -q "_csrf_token"`
- Expected: exit 0

## Regression notes
- All 5 features are shipped; site audit baseline is `20260409-055417` (0 failures, 0 violations)
- `STAGE 0 PENDING` notes in suite.json run_notes are outdated; qa-forseti should update them to `STAGE 1 READY`
- OQ-1: `job_tailoring_save_resume` missing `_csrf_token: 'TRUE'` — TC-11 will fail until dev-forseti patches routing.yml
- E2E suites (`-e2e`) are not in scope for this feature; they are tracked separately (SKIPPED via Node unavailable policy)

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-qa-suite-fill-release-f

- Feature: forseti-qa-suite-fill-release-f
- Module: qa_suites
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Acceptance criteria for 16 suite shells covering 5 release-f shipped features. All 5 features are confirmed deployed to production. ACs are grounded in the live codebase: routes, controller PHP lint status, template existence, CSRF state, and permission requirements verified at time of writing.

## Codebase state at time of writing (verified)

| Feature | Key structural facts |
|---|---|
| application-status-dashboard | `my_jobs` → `ApplicationSubmissionController::myJobs`; `my_jobs_bulk_archive` POST route ✓ with `_csrf_token: 'TRUE'`; `my-jobs.html.twig` ✓ |
| google-jobs-ux | `google_jobs_search` at `/jobhunter/googlejobssearch` (`access job hunter`); `google_jobs_search_detail` at `/jobhunter/googlejobsdetail/{job_id}` (`job_id:\d+`); `GoogleJobsSearchController` lints clean; 4 Google-related templates exist |
| profile-completeness | `ProfileCompletenessService.php` exists and lints clean; `profile-completeness.html.twig` exists; no new routes added (widget embeds on existing routes) |
| resume-tailoring-display | `job_tailoring` GET route with `job_id:\d+`; `job_tailoring_save_resume` POST route (`methods:[POST]`) — **missing `_csrf_token: 'TRUE'`** (security finding, see OQ-1); `job-tailoring-combined.html.twig` exists |
| ai-conversation-user-chat | `ai_conversation.forseti_chat` at `/forseti/chat` with `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`; `send_message` at `/ai-conversation/send-message` with `_csrf_token: 'TRUE'`; `forseti-chat.html.twig` exists; `ChatController.php` lints clean |

---

## Feature 1: application-status-dashboard (3 suites)

### Suite: forseti-jobhunter-application-status-dashboard-static

**Purpose:** Grep/file checks verifying the bulk archive route is registered with correct CSRF and POST constraints.

#### AC-STATIC-DASH-01: bulk archive route exists in routing.yml
- Check: `grep -q "my_jobs_bulk_archive" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: exit 0

#### AC-STATIC-DASH-02: bulk archive route has methods:[POST]
- Check: `grep -A8 "my_jobs_bulk_archive" .../job_hunter.routing.yml | grep -q "methods.*POST"`
- Expected: exit 0

#### AC-STATIC-DASH-03: bulk archive route has _csrf_token: 'TRUE'
- Check: `grep -A8 "my_jobs_bulk_archive" .../job_hunter.routing.yml | grep -q "_csrf_token"`
- Expected: exit 0

#### AC-STATIC-DASH-04: my-jobs.html.twig template exists
- Check: `test -f sites/forseti/web/modules/custom/job_hunter/templates/my-jobs.html.twig`
- Expected: exit 0

#### AC-STATIC-DASH-05: ApplicationSubmissionController PHP lint clean
- Check: `php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`
- Expected: "No syntax errors detected", exit 0

---

### Suite: forseti-jobhunter-application-status-dashboard-functional

**Purpose:** HTTP-level checks verifying ACL enforcement and CSRF enforcement on bulk archive.

#### AC-FUNC-DASH-01: anonymous GET /jobhunter/my-jobs returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/my-jobs`
- Expected: 403; not 200 or 500
- Verification note: Route requires `_permission: 'access job hunter'`; anonymous = no permission.

#### AC-FUNC-DASH-02: anonymous POST /jobhunter/my-jobs/bulk-archive without CSRF returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" -X POST ${FORSETI_BASE_URL}/jobhunter/my-jobs/bulk-archive`
- Expected: 403; not 200 or 500

#### AC-FUNC-DASH-03: anonymous GET /jobhunter/my-jobs/archive returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/my-jobs/archive`
- Expected: 403

---

### Suite: forseti-jobhunter-application-status-dashboard-regression

**Purpose:** Site audit regression guard — no new 4xx/5xx regressions from dashboard feature.

#### AC-REGR-DASH-01: site audit exits 0 after dashboard feature deployment
- Command: `cd /home/ubuntu/forseti.life/copilot-hq && ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`
- Expected: 0 failures, 0 violations; exit 0
- Baseline: audit `20260409-055417` — 0 failures, 0 violations

#### AC-REGR-DASH-02: /jobhunter/my-jobs route still registered
- Command: `grep -q "job_hunter.my_jobs:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: exit 0 (route not accidentally removed)

---

## Feature 2: google-jobs-ux (3 suites)

### Suite: forseti-jobhunter-google-jobs-ux-static

**Purpose:** Static file checks verifying controller lints and templates exist.

#### AC-STATIC-GJ-01: GoogleJobsSearchController PHP lint clean
- Command: `php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/GoogleJobsSearchController.php`
- Expected: exit 0

#### AC-STATIC-GJ-02: google-jobs-search.html.twig exists
- Command: `test -f sites/forseti/web/modules/custom/job_hunter/templates/google-jobs-search.html.twig`
- Expected: exit 0

#### AC-STATIC-GJ-03: google-jobs-job-detail.html.twig exists
- Command: `test -f sites/forseti/web/modules/custom/job_hunter/templates/google-jobs-job-detail.html.twig`
- Expected: exit 0

#### AC-STATIC-GJ-04: google_jobs_search route is GET-only (no CSRF — GET feature)
- Command: `grep -A5 "^job_hunter.google_jobs_search:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep -q "methods.*GET"`
- Expected: exit 0; confirm no CSRF token required on this feature's routes

#### AC-STATIC-GJ-05: google_jobs_search_detail route enforces integer job_id
- Command: `grep -A8 "^job_hunter.google_jobs_search_detail:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep -q "job_id.*\\\\d+"`
- Expected: exit 0 (ensures non-integer job_id returns 404 at router level)

---

### Suite: forseti-jobhunter-google-jobs-ux-functional

**Purpose:** HTTP-level checks verifying ACL enforcement on search and detail routes.

#### AC-FUNC-GJ-01: anonymous GET /jobhunter/googlejobssearch returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/googlejobssearch`
- Expected: 403; not 200 or 500
- Verification note: Route requires `_permission: 'access job hunter'`.

#### AC-FUNC-GJ-02: anonymous GET /jobhunter/googlejobsdetail/1 returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/googlejobsdetail/1`
- Expected: 403

#### AC-FUNC-GJ-03: non-integer job_id /jobhunter/googlejobsdetail/notanid returns 404
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/googlejobsdetail/notanid`
- Expected: 404 (routing regex `\d+` rejects non-integer, anon path)
- Verification note: 403 is also acceptable (Drupal evaluates access before route parameters on some versions).

---

### Suite: forseti-jobhunter-google-jobs-ux-regression

**Purpose:** Regression guard ensuring search routes remain registered after UX refactor.

#### AC-REGR-GJ-01: google_jobs_search route registered
- Command: `grep -q "^job_hunter.google_jobs_search:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: exit 0

#### AC-REGR-GJ-02: google_jobs_search_detail route registered
- Command: `grep -q "^job_hunter.google_jobs_search_detail:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: exit 0

#### AC-REGR-GJ-03: site audit exits 0 after google-jobs-ux deployment
- Command: `cd /home/ubuntu/forseti.life/copilot-hq && ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`
- Expected: 0 failures, 0 violations
- Baseline: audit `20260409-055417`

---

## Feature 3: profile-completeness (3 suites)

### Suite: forseti-jobhunter-profile-completeness-static

**Purpose:** Static checks verifying ProfileCompletenessService file exists, lints clean, and template exists.

#### AC-STATIC-PC-01: ProfileCompletenessService.php exists
- Command: `test -f sites/forseti/web/modules/custom/job_hunter/src/Service/ProfileCompletenessService.php`
- Expected: exit 0

#### AC-STATIC-PC-02: ProfileCompletenessService PHP lint clean
- Command: `php -l sites/forseti/web/modules/custom/job_hunter/src/Service/ProfileCompletenessService.php`
- Expected: exit 0

#### AC-STATIC-PC-03: profile-completeness.html.twig exists
- Command: `test -f sites/forseti/web/modules/custom/job_hunter/templates/profile-completeness.html.twig`
- Expected: exit 0

#### AC-STATIC-PC-04: no new routes added (CSRF baseline unchanged by this feature)
- Verification note: Profile completeness is a read-only widget embedded on existing routes; no new routing entries needed.
- Command: `grep -c "profile.completeness\|profile_completeness" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: 0 (no new route entries for this feature — widget renders on existing `/jobhunter` and `/jobhunter/profile` routes)

---

### Suite: forseti-jobhunter-profile-completeness-functional

**Purpose:** HTTP checks verifying existing profile and dashboard routes still require auth (widget doesn't break ACL).

#### AC-FUNC-PC-01: anonymous GET /jobhunter/profile returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/profile`
- Expected: 403 or 302; not 200 or 500

#### AC-FUNC-PC-02: anonymous GET /jobhunter returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter`
- Expected: 403 or 302; not 200 or 500

#### AC-FUNC-PC-03: anonymous GET /jobhunter/profile/summary returns 403 (if route exists)
- Command: `STATUS=$(curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/profile/summary); [ "$STATUS" = "403" ] || [ "$STATUS" = "302" ] || [ "$STATUS" = "404" ] && exit 0 || (echo "FAIL: expected 403/302/404, got $STATUS" && exit 1)`
- Expected: 403, 302, or 404 (404 acceptable if route not registered); not 200 or 500

---

### Suite: forseti-jobhunter-profile-completeness-regression

**Purpose:** Regression guard confirming widget embed doesn't break existing profile/dashboard routes.

#### AC-REGR-PC-01: site audit exits 0 post-implementation
- Command: `cd /home/ubuntu/forseti.life/copilot-hq && ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`
- Expected: 0 failures, 0 violations
- Baseline: audit `20260409-055417`

#### AC-REGR-PC-02: ProfileCompletenessService registered in services.yml or discovered by Drupal
- Command: `grep -rq "ProfileCompletenessService\|profile_completeness_service\|job_hunter.profile_completeness" sites/forseti/web/modules/custom/job_hunter/`
- Expected: exit 0 (service is referenced in services.yml or wired via create())

---

## Feature 4: resume-tailoring-display (3 suites)

### Suite: forseti-jobhunter-resume-tailoring-display-static

**Purpose:** Static checks verifying CSRF on save route, integer routing on job_id, and template existence.

#### AC-STATIC-RT-01: job_tailoring GET route exists with integer job_id constraint
- Command: `grep -A8 "^job_hunter.job_tailoring:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep -q "job_id.*\\\\d+"`
- Expected: exit 0

#### AC-STATIC-RT-02: job-tailoring-combined.html.twig exists
- Command: `test -f sites/forseti/web/modules/custom/job_hunter/templates/job-tailoring-combined.html.twig`
- Expected: exit 0

#### AC-STATIC-RT-03: job_tailoring_save_resume route exists with methods:[POST]
- Command: `grep -A6 "^job_hunter.job_tailoring_save_resume:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep -q "methods.*POST"`
- Expected: exit 0

#### AC-STATIC-RT-04: job_tailoring_save_resume route has _csrf_token: 'TRUE' (OQ-1 — CURRENTLY FAILING)
- Command: `grep -A8 "^job_hunter.job_tailoring_save_resume:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep -q "_csrf_token"`
- Expected: exit 0
- **⚠️ FINDING:** At codebase inspection (2026-04-09), `job_tailoring_save_resume` route has `methods: [POST]` but is MISSING `_csrf_token: 'TRUE'` in requirements. This AC will FAIL until dev-forseti adds `_csrf_token: 'TRUE'` to that route. See OQ-1.

#### AC-STATIC-RT-05: CompanyController PHP lint clean (saveResume is in CompanyController)
- Command: `php -l sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
- Expected: exit 0

---

### Suite: forseti-jobhunter-resume-tailoring-display-functional

**Purpose:** HTTP checks verifying ACL on tailoring route and integer validation on job_id.

#### AC-FUNC-RT-01: anonymous GET /jobhunter/jobtailoring/1 returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/jobtailoring/1`
- Expected: 403; not 200 or 500

#### AC-FUNC-RT-02: non-integer job_id /jobhunter/jobtailoring/notanid returns 404
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/jobtailoring/notanid`
- Expected: 404 (routing regex `\d+` rejects non-integer path param)

#### AC-FUNC-RT-03: anonymous POST /jobhunter/jobtailoring/1/save-resume returns 403 (not 500)
- Command: `curl -o /dev/null -s -w "%{http_code}" -X POST ${FORSETI_BASE_URL}/jobhunter/jobtailoring/1/save-resume`
- Expected: 403; 000 (connection refused) or 404 are also acceptable; 500 is FAIL

---

### Suite: forseti-jobhunter-resume-tailoring-display-regression

**Purpose:** Regression guard ensuring tailoring routes and CSRF remain intact.

#### AC-REGR-RT-01: job_tailoring GET route still registered
- Command: `grep -q "^job_hunter.job_tailoring:" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: exit 0

#### AC-REGR-RT-02: site audit exits 0 post-implementation
- Command: `cd /home/ubuntu/forseti.life/copilot-hq && ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`
- Expected: 0 failures, 0 violations
- Baseline: audit `20260409-055417`

---

## Feature 5: ai-conversation-user-chat (4 suites)

### Suite: forseti-ai-conversation-user-chat-static

**Purpose:** Static checks verifying chat route, template, and CSRF on send_message.

#### AC-STATIC-AI-01: ai_conversation.forseti_chat route registered at /forseti/chat
- Command: `grep -q "ai_conversation.forseti_chat:" sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
- Expected: exit 0

#### AC-STATIC-AI-02: forseti-chat.html.twig template exists
- Command: `test -f sites/forseti/web/modules/custom/ai_conversation/templates/forseti-chat.html.twig`
- Expected: exit 0

#### AC-STATIC-AI-03: ChatController PHP lint clean
- Command: `php -l sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php`
- Expected: exit 0

#### AC-STATIC-AI-04: send_message route has _csrf_token: 'TRUE'
- Command: `grep -A8 "^ai_conversation.send_message:" sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml | grep -q "_csrf_token"`
- Expected: exit 0

#### AC-STATIC-AI-05: forseti_chat route requires 'use ai conversation' permission
- Command: `grep -A8 "^ai_conversation.forseti_chat:" sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml | grep -q "use ai conversation"`
- Expected: exit 0

---

### Suite: forseti-ai-conversation-user-chat-acl

**Purpose:** HTTP checks verifying anonymous access is blocked on chat and send-message endpoints.

#### AC-ACL-AI-01: anonymous GET /forseti/chat returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/forseti/chat`
- Expected: 403; not 200 or 500

#### AC-ACL-AI-02: anonymous POST /ai-conversation/send-message returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" -X POST ${FORSETI_BASE_URL}/ai-conversation/send-message`
- Expected: 403; not 200 or 500

#### AC-ACL-AI-03: anonymous GET /ai-chat (legacy start_chat) returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/ai-chat`
- Expected: 403; not 200 or 500
- Verification note: `ai_conversation.start_chat` at `/ai-chat` requires `_permission: 'use ai conversation'`.

---

### Suite: forseti-ai-conversation-user-chat-csrf-post

**Purpose:** Verify CSRF enforcement on the send-message endpoint.

#### AC-CSRF-AI-01: POST /ai-conversation/send-message without CSRF token returns 403
- Command: `curl -o /dev/null -s -w "%{http_code}" -X POST -H "Content-Type: application/json" -d '{"message":"test"}' ${FORSETI_BASE_URL}/ai-conversation/send-message`
- Expected: 403 (CSRF check fails before even processing auth)

#### AC-CSRF-AI-02: send_message route not accessible as GET (POST-only enforcement)
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/ai-conversation/send-message`
- Expected: 403 or 405; not 200 or 500
- Verification note: Route has `_csrf_token: 'TRUE'` and typically restricted to POST by the AJAX handler convention; 405 if methods:[POST] enforced.

---

### Suite: forseti-ai-conversation-user-chat-regression

**Purpose:** Regression guard ensuring existing AI conversation admin routes are unaffected.

#### AC-REGR-AI-01: admin AI settings route still registered
- Command: `grep -q "^ai_conversation.settings:" sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
- Expected: exit 0

#### AC-REGR-AI-02: anonymous GET /admin/config/ai-conversation/settings returns 403 (admin route ACL intact)
- Command: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/admin/config/ai-conversation/settings`
- Expected: 403; not 200 or 500

#### AC-REGR-AI-03: site audit exits 0 after ai-conversation-user-chat deployment
- Command: `cd /home/ubuntu/forseti.life/copilot-hq && ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`
- Expected: 0 failures, 0 violations
- Baseline: audit `20260409-055417`

---

## Open questions

| OQ | Suite | Finding | Recommendation |
|---|---|---|---|
| OQ-1 | forseti-jobhunter-resume-tailoring-display-static | `job_tailoring_save_resume` route has `methods: [POST]` but is MISSING `_csrf_token: 'TRUE'` in requirements. The original AC-3 and Security AC specified this should be present. AC-STATIC-RT-04 will FAIL until fixed. | dev-forseti should add `_csrf_token: 'TRUE'` to the `job_hunter.job_tailoring_save_resume` requirements block before this suite can go green. Escalate to pm-forseti. |
| OQ-2 | forseti-ai-conversation-user-chat-csrf-post | `ai_conversation.send_message` route has `_csrf_token: 'TRUE'` but no explicit `methods: [POST]` line. AC-CSRF-AI-02 expects 403 or 405; actual behavior depends on Drupal's default method handling for CSRF-protected routes. | qa-forseti should confirm actual behavior; if GET to send_message returns 200, it should be flagged as a bug. Recommend adding `methods: [POST]` explicitly. |
| OQ-3 | all regression suites | STAGE 0 PENDING notes in suite.json run_notes are outdated — all 5 features are now shipped. | qa-forseti should update `run_notes` to remove "STAGE 0 PENDING" language and mark suites as "STAGE 1 READY". |

## Definition of done

- [ ] All 16 suites have ≥2 concrete ACs with explicit PASS conditions
- [ ] Each AC is tied to a single bash command or PHP check
- [ ] OQ-1 (missing CSRF on resume-tailoring save route) escalated to dev-forseti/pm-forseti
- [ ] `01-acceptance-criteria.md` committed to HQ repo
- [ ] `feature.md` Status updated to `ready`
- [ ] `03-test-plan.md` created

## Verification commands

```bash
# Check all 5 features' key structural facts
# 1. Bulk archive CSRF
grep -A8 "my_jobs_bulk_archive" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# 2. Google jobs routes registered
grep -E "^job_hunter.google_jobs_search:|^job_hunter.google_jobs_search_detail:" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# 3. ProfileCompletenessService
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/ProfileCompletenessService.php

# 4. Resume tailoring CSRF gap (OQ-1)
grep -A10 "job_tailoring_save_resume:" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_csrf_token"

# 5. AI chat route and template
grep -A6 "ai_conversation.forseti_chat:" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml
test -f /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/templates/forseti-chat.html.twig && echo "template OK"
```
- Agent: qa-forseti
- Status: pending
