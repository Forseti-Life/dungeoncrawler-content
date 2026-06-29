# Security Pre-Flight Checklist: dungeoncrawler release (2026-02-27)

Analyst: sec-analyst-infra (WRAITH)
Date: 2026-02-27
Scope: Custom modules in `sites/dungeoncrawler/web/modules/custom/` — specifically `ai_conversation`, `dungeoncrawler_content`, `dungeoncrawler_tester`
Branch scoped: `main` (HEAD f3479fa, post-`87a16072f` security fix commit)

---

## Checklist: Access Control (Routes/Permissions)

| Surface | Status | Notes |
|---|---|---|
| `ai_conversation` admin routes (`/admin/*`) | PASS | All gated by `administer site configuration` |
| `ai_conversation.send_message` (POST) | PASS | `_csrf_token: TRUE`, `_permission: use ai conversation` |
| `ai_conversation.genai_debug_delete` (POST `/admin/reports/genai-debug/{id}/delete`) | FLAG | No `_csrf_token`. Admin-only permission protects against anonymous/non-admin abuse, but an admin session is CSRF-vulnerable. If an admin visits a malicious page while logged in, attacker can trigger record deletion. |
| `ai_conversation.genai_debug_delete_all` (POST `/admin/reports/genai-debug/delete-all`) | FLAG | No `_csrf_token`. Same class as above; higher blast radius — deletes ALL debug records. |
| `ai_conversation.update_prompt` (GET/POST `/admin/ai-conversation/update-prompt`) | FLAG | No method restriction declared; no `_csrf_token`. If this controller accepts POST state-change (system prompt update), admin sessions are CSRF-vulnerable. Verify controller is GET-only or add CSRF + method restriction. |
| `ai_conversation` REST API endpoints (`/api/ai-conversation/*`) | PASS | `_format: json` + permission check. JSON format requirement prevents simple form-based CSRF. Session auth applies. |
| `dungeoncrawler_tester` mutating POSTs | PASS | `_csrf_token: TRUE` on all state-changing routes (queue_run, dead_value_close, bulk_close_query_run, queue_item_delete, queue_item_rerun) |
| `dungeoncrawler_tester` read-only GETs | PASS | Admin-only, no mutation surface |
| `dungeoncrawler_content` game API POSTs (`/api/*`) | PASS | `_format: json` on key state-changing routes (character_save, combat_start, etc.). JSON format prevents simple CSRF. Authenticated permission required. |
| `dungeoncrawler_content` form-accepting POSTs (campaign_create, character_step, etc.) | PASS | Drupal form API handles CSRF via form tokens automatically for `_form`-backed routes |

---

## Checklist: Input Validation

| Surface | Status | Notes |
|---|---|---|
| `ai_conversation.genai_debug_detail` `{id}` param | PASS | `id: \d+` pattern constraint in routing |
| `dungeoncrawler_content` `{dungeon_id}`, `{campaign_id}`, `{room_id}` | PASS | Entity type parameters with type constraints |
| AI conversation message body (server-side) | UNVERIFIED | Not reviewable from routing alone; recommend controller-level review for prompt injection / length limits in a follow-on pass |

---

## Checklist: Storage Safety

| Surface | Status | Notes |
|---|---|---|
| Settings files for dungeoncrawler | NOT REVIEWED | `sites/dungeoncrawler/web/sites/default/settings.php` not readable via static routing scan. Known prior finding: DCC-0331 (hardcoded credentials in tracked settings). Status of remediation unknown for this release. |

---

## Checklist: Automation/Cron

| Surface | Status | Notes |
|---|---|---|
| Cron surfaces for dungeoncrawler | NOT REVIEWED | No cron changes identified in scope of this release. |

---

## Summary

**FLAGs requiring action before Gate 2:**

### FLAG-1 (Medium): Missing CSRF on `genai_debug_delete` and `genai_debug_delete_all`
- **File:** `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
- **Routes:** `ai_conversation.genai_debug_delete`, `ai_conversation.genai_debug_delete_all`
- **Impact:** An admin visiting a malicious page while authenticated could have their session abused to delete GenAI debug records. `genai_debug_delete_all` has highest blast radius.
- **Likelihood:** Low (requires admin to visit attacker-controlled page while logged in, and debug records are non-PII operational data). Not rated HIGH because admin-only permission is a meaningful mitigation layer.
- **Mitigation:** Add `_csrf_token: 'TRUE'` to both routes' `requirements` block.
- **Verification:** After fix, send a POST to `/admin/reports/genai-debug/1/delete` without a valid CSRF token while authenticated as admin — Drupal should return 403.

### FLAG-2 (Low/Medium): `ai_conversation.update_prompt` — no method restriction or CSRF
- **File:** `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
- **Route:** `ai_conversation.update_prompt`
- **Impact:** If the controller accepts POST state changes (system prompt update), admin sessions are CSRF-vulnerable. System prompt manipulation could affect AI conversation quality/safety.
- **Likelihood:** Low if controller is GET-only. Needs controller inspection.
- **Mitigation:** Inspect `AdminController::updateSystemPrompt` — if it mutates state via POST: add `methods: [GET, POST]` and `_csrf_token: 'TRUE'`. If GET-only: add `methods: [GET]` to enforce.
- **Verification:** Check controller for `$request->isMethod('POST')` or form submission handling.

### UNVERIFIED: DCC-0331 remediation status
- Prior finding: hardcoded credentials in `sites/dungeoncrawler/web/sites/default/settings.php`.
- Status of remediation for this release is unknown. pm-infra should confirm DCC-0331 remediation state before Gate 2.

---

## Overall pre-flight result: FLAG (2 medium findings, 1 unverified prior finding)
Recommend: dev-infra to address FLAG-1 and FLAG-2 before Gate 2. pm-infra to confirm DCC-0331 status.
