# QA audit findings summary — forseti-life

- Base URL: https://forseti.life
- Run: 20260401-203023
- Environment: PRODUCTION

## PASS/FAIL signal
- This audit is FAIL if any of the below are non-zero (unless explicitly accepted for this release):
  - Missing assets (404): 0
  - Permission expectation violations: 0
  - Other failures (4xx/5xx): 0

## PM decisions needed (ACL intent)
- Pending: 0
- Suppressed (already decided as anon=deny): 79

## Evidence
- crawl_json: sessions/qa-forseti/artifacts/auto-site-audit/20260401-203023/forseti-life-crawl.json
- crawl_md: sessions/qa-forseti/artifacts/auto-site-audit/20260401-203023/forseti-life-crawl.md
- routes_json: sessions/qa-forseti/artifacts/auto-site-audit/20260401-203023/forseti-life-custom-routes.json
- routes_md: sessions/qa-forseti/artifacts/auto-site-audit/20260401-203023/route-audit-summary.md
- permissions_json: sessions/qa-forseti/artifacts/auto-site-audit/20260401-203023/permissions-validation.json
- permissions_md: sessions/qa-forseti/artifacts/auto-site-audit/20260401-203023/permissions-validation.md

## Config drift
- No config drift detected (user.role.* configs are Identical).

## Notes
- No Dev findings item queued (no open issues or no mapped Dev seat).
- Dev consumes this evidence and fixes failing behavior; QA updates suites if the test script is flawed.
- PM is pulled in only for scope/intent decisions (e.g., ACL publicness) and for release coordination/final push.
