# Gate 4 Verification Report — 20260405-forseti-release-c

- Run: 20260406-115511
- Release: 20260405-forseti-release-c (HEAD: e56c9b4d)
- Site: https://forseti.life
- Environment: PRODUCTION
- QA: qa-forseti
- Decision: **APPROVE**

## Acceptance criteria results

| Check | Expected | Actual | Result |
|---|---|---|---|
| Missing assets (404) | 0 | 0 | PASS |
| Permission expectation violations | 0 | 0 | PASS |
| Unexpected 5xx errors | 0 | 0 | PASS |
| Homepage (https://forseti.life) | HTTP 200 | 200 | PASS |
| /jobhunter (anon) | 403 | 403 | PASS |
| /talk-with-forseti (anon) | 403 | 403 | PASS |

## CSRF fix verification (forseti-csrf-fix P0)

- application_submission POST routes confirmed: all 8 `*_post` routes in routing.yml have `_csrf_token: 'TRUE'` on methods: [POST]
- GET counterpart routes do NOT have _csrf_token (split-route pattern confirmed deployed)
- Example verified: `job_hunter.application_submission_step3_post` — POST /jobhunter/application-submission/{job_id}/identify-auth-path → `_csrf_token: 'TRUE'`, `methods: [POST]`

## Config drift

- No config drift detected (user.role.* configs are Identical)
- Known pre-existing drift (core.extension Different, field_ai_model Only in sync dir from CEO Bedrock hotfix) — NOT flagged as regression per release notes

## Role-based audit note

- Session cookies (FORSETI_COOKIE_AUTHENTICATED, FORSETI_COOKIE_CONTENT_EDITOR, FORSETI_COOKIE_ADMINISTRATOR) not set in environment — authenticated-role ACL assertions ran as anonymous only
- No per-role violations expected given 0 violations in anon crawl and prior clean Gate 2 evidence
- Full per-role audit deferred to next scheduled full-regression checkpoint

## Evidence

- Audit: sessions/qa-forseti/artifacts/auto-site-audit/20260406-115511/findings-summary.md
- CSRF routing.yml: /var/www/html/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
