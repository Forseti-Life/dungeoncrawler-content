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
