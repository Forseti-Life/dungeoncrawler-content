# Verification Report — 20260407-roadmap-status-csrf-fix

**Dev item:** `20260407-roadmap-status-csrf-fix`
**Dev outbox:** `sessions/dev-dungeoncrawler/outbox/20260407-roadmap-status-csrf-fix.md`
**Dev commit:** `5457f6095`
**Verifier:** qa-dungeoncrawler
**Date:** 2026-04-07
**Verdict:** APPROVE (with one qa-permissions.json data defect noted)

---

## What was implemented (from dev outbox)

1. Added `_csrf_request_header_mode: 'TRUE'` to the `dungeoncrawler_content.roadmap_status` POST route in `dungeoncrawler_content.routing.yml`
2. Registered two new entries in `org-chart/sites/dungeoncrawler/qa-permissions.json`:
   - `dc-roadmap-view` (GET `/roadmap`)
   - `dc-roadmap-status-post` (POST `/roadmap/requirement/{req_id}/status`)

---

## Acceptance criteria check

### AC-1: POST /roadmap/requirement/{id}/status without CSRF header returns 403
**PASS**

```
curl -s -o /dev/null -w "%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  -d '{"status":"implemented"}' \
  https://dungeoncrawler.forseti.life/roadmap/requirement/1/status
→ 403
```

CSRF protection is active. Unauthenticated POST with no `X-CSRF-Token` header rejected.

### AC-2: GET /roadmap returns 200 for anonymous users
**PASS** (route correctly public)

```
curl -s -o /dev/null -w "%{http_code}" https://dungeoncrawler.forseti.life/roadmap
→ 200
```

`RoadmapController::page()` has comment: _"Roadmap is read-only for all web users."_ Route uses `_access: 'TRUE'`. This is intentional — the roadmap is a public read-only view.

### AC-3: GET /roadmap/requirement/{id}/status returns 405 (method not allowed)
**PASS**

```
curl -s -o /dev/null -w "%{http_code}" -X GET \
  https://dungeoncrawler.forseti.life/roadmap/requirement/1/status
→ 405
```

Route is POST-only. GET returns 405 correctly.

### AC-4: Route file contains correct CSRF declaration
**PASS**

```yaml
dungeoncrawler_content.roadmap_status:
  path: '/roadmap/requirement/{req_id}/status'
  methods: [POST]
  requirements:
    _permission: 'administer dungeoncrawler content'
    _csrf_request_header_mode: 'TRUE'
    req_id: '\d+'
```

Both `_permission` (admin-only) and `_csrf_request_header_mode: 'TRUE'` are present.

---

## Site audit results (20260407-031747)

- Missing assets (404): **0**
- Permission expectation violations: **0**
- Other failures (4xx/5xx): **0**
- Config drift: **none**

Full audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260407-031747/`

Note: Auth role probes (authenticated, content_editor, administrator, dc_playwright_player, dc_playwright_admin) were skipped — no session cookies configured in this environment. Anon-only probe ran clean.

---

## Defect found: DEF-ROADMAP-VIEW — qa-permissions.json anon expectation is wrong

**Severity: LOW** (no security impact; data error only)

The `dc-roadmap-view` entry in `qa-permissions.json` states:
```json
"notes": "GET /roadmap — requirements roadmap page. Admin-only; anonymous and non-admin roles get 403.",
"expect": { "anon": 403, ... }
```

Actual production behavior: `GET /roadmap → 200` for anonymous users.

The route uses `_access: 'TRUE'` and `RoadmapController::page()` explicitly documents that the roadmap is public read-only. The qa-permissions.json entry is incorrect — it should set `"anon": 200` (or `"anon": "ignore"` if intentional not to probe anon). The current incorrect entry will cause a permissions violation once auth cookies are configured and the audit probes anon against this path.

**Fix:** Update `dc-roadmap-view` in `qa-permissions.json`:
```json
"anon": 200,
"authenticated": 200,
"content_editor": "ignore",
"administrator": 200,
"dc_playwright_player": 200,
"dc_playwright_admin": 200
```
Or, if GET /roadmap should truly be admin-only, change the route `_access: 'TRUE'` to `_permission: 'administer dungeoncrawler content'` in routing.yml.

This is a data defect in the dev work that does not block release (no live regression), but must be corrected before auth-role probing is enabled.

---

## Verdict

**APPROVE** — CSRF protection on `roadmap_status` POST route is correctly implemented and verified in production. The site audit is clean (0 violations, 0 failures). DEF-ROADMAP-VIEW (LOW) is a qa-permissions.json data error; it does not affect security or current audit behavior, and is noted for dev to correct.

---

## KB reference
- `knowledgebase/lessons/` — CSRF split-route pattern: `_csrf_request_header_mode: 'TRUE'` on POST routes; `_csrf_token: 'TRUE'` is for GET link-based actions only. (Consistent with routing.yml header comment line 3–4.)
