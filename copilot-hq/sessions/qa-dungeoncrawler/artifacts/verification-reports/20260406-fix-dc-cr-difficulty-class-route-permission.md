# QA Verification Report: 20260406-fix-dc-cr-difficulty-class-route-permission

**Date:** 2026-04-06
**Agent:** qa-dungeoncrawler
**Feature:** dc-cr-difficulty-class (route permission fix)
**Dev commit:** 86fcd7445
**Status:** APPROVE

---

## Scope

Targeted re-verification of the routing permission fix for `dungeoncrawler_content.api.rules_check`:

- **BLOCK:** Route had `_permission: 'access dungeoncrawler characters'` — anonymous POST returned 403
- **Fix:** Changed to `_access: 'TRUE'` (matching `/dice/roll` pattern per AC requiring anonymous access)
- **Route:** `POST /rules/check` — DC check endpoint

---

## AC Verification

### Route permission

**AC:** `POST /rules/check` accessible by anonymous users; returns HTTP 200 with `{"degree": ...}` response

**Production routing.yml verified:**
```yaml
dungeoncrawler_content.api.rules_check:
  path: '/rules/check'
  methods: [POST]
  requirements:
    _access: 'TRUE'
    _csrf_request_header_mode: TRUE
```
- `_access: 'TRUE'` confirmed ✅
- `_permission: 'access dungeoncrawler characters'` absent ✅

### Live probes (production: https://dungeoncrawler.forseti.life)

```
GET /rules/check              → 405  (POST-only enforced) ✅
POST /rules/check (no CSRF, anon) → 200  (anonymous allowed) ✅
  body: {"roll":25,"dc":15}
  response: {"success":true,"roll":25,"dc":15,"degree":"critical_success"} ✅
```

**CSRF behavior note:** `_csrf_request_header_mode: TRUE` only enforces the `X-CSRF-Token` header for **authenticated users with an active session cookie** — Drupal core `CsrfRequestHeaderAccessCheck::access()` returns `AccessResult::allowed()` immediately for anonymous users (line 85 of `CsrfRequestHeaderAccessCheck.php`). Anonymous POST without CSRF header returning 200 is correct behavior, not a defect. The prior verification report entry claiming "POST w/o CSRF→400" was inaccurate; corrected in checklist line 98.

### Response validation

- `"success": true` ✅
- `"roll": 25` ✅
- `"dc": 15` ✅
- `"degree": "critical_success"` ✅ (roll=25 vs dc=15 → +10 or more margin = critical_success per PF2e)

### Site audit

Run: `20260406-204546` — **0 failures** (0 missing assets, 0 permission violations, 0 4xx/5xx) ✅

---

## Result

**APPROVE**

Route permission fix is confirmed. `_access: 'TRUE'` in production routing.yml; anonymous POST returns 200 with correct DC degree response; GET returns 405 (method enforcement active); site audit clean.

Evidence commits:
- Dev fix: `86fcd7445`
- Site audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-204546/findings-summary.md`
