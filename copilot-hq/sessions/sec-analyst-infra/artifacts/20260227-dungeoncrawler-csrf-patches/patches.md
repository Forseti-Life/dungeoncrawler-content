# Ready-to-Apply Security Patches: dungeoncrawler ai_conversation CSRF (2026-02-27)

Analyst: sec-analyst-infra (WRAITH)
Date: 2026-02-27
Source: Pre-flight checklist `20260227-dungeoncrawler-release-preflight/preflight.md`
Target file: `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
Owner for apply: dev-infra (or delegated dev seat)

---

## STATUS UPDATE: FLAG-2 severity upgraded from Low/Medium → HIGH

The `updateSystemPrompt` controller (`AdminController.php:44`) mutates state on every invocation — it calls `promptManager->saveSystemPrompt()` unconditionally with no POST body required. The route accepts GET by default (no `methods:` restriction). This means the system prompt can be reset/overwritten by any admin who loads a page containing an `<img src="/admin/ai-conversation/update-prompt">` tag — no JavaScript, no interaction, no form submission required.

**Severity: HIGH** (was Low/Medium in pre-flight)
**Affected:** System prompt integrity for all dungeoncrawler AI conversations

---

## Patch 1 of 3 — FLAG-1a: genai_debug_delete (Medium)

**File:** `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Lines:** 42–49

```diff
 ai_conversation.genai_debug_delete:
   path: '/admin/reports/genai-debug/{id}/delete'
   defaults:
     _controller: '\Drupal\ai_conversation\Controller\GenAiDebugController::deleteCall'
   methods: [POST]
   requirements:
     _permission: 'administer site configuration'
+    _csrf_token: 'TRUE'
     id: \d+
```

**Verification:** `POST /admin/reports/genai-debug/1/delete` without valid CSRF token while admin-authenticated → expect 403.

---

## Patch 2 of 3 — FLAG-1b: genai_debug_delete_all (Medium)

**File:** `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Lines:** 51–57

```diff
 ai_conversation.genai_debug_delete_all:
   path: '/admin/reports/genai-debug/delete-all'
   defaults:
     _controller: '\Drupal\ai_conversation\Controller\GenAiDebugController::deleteAllFiltered'
   methods: [POST]
   requirements:
     _permission: 'administer site configuration'
+    _csrf_token: 'TRUE'
```

**Verification:** `POST /admin/reports/genai-debug/delete-all` without valid CSRF token while admin-authenticated → expect 403.

---

## Patch 3 of 3 — FLAG-2 UPGRADED: update_prompt GET-based state mutation (HIGH)

**File:** `sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml`
**Lines:** 59–65

```diff
 ai_conversation.update_prompt:
   path: '/admin/ai-conversation/update-prompt'
   defaults:
     _controller: '\Drupal\ai_conversation\Controller\AdminController::updateSystemPrompt'
     _title: 'Update System Prompt'
+  methods: [POST]
   requirements:
     _permission: 'administer site configuration'
+    _csrf_token: 'TRUE'
```

**Why:** `AdminController::updateSystemPrompt()` calls `promptManager->saveSystemPrompt()` unconditionally — no POST body needed. Route has no `methods:` restriction, meaning a GET request triggers the write. An `<img src="/admin/ai-conversation/update-prompt">` in any page an admin visits will silently overwrite the system prompt.

**Note for dev-infra:** After adding `methods: [POST]`, ensure any UI element that triggers this endpoint sends a POST with a valid Drupal CSRF token (X-CSRF-Token header or `_token` in form). If this route is triggered by a button (not a background call), consider migrating to a Drupal form with proper `buildForm`/`submitForm` lifecycle instead.

**Verification:** 
1. GET `/admin/ai-conversation/update-prompt` while admin-authenticated → expect 405 Method Not Allowed (or 403).
2. POST without valid CSRF token → expect 403.
3. POST with valid CSRF token → expect 200 with `{"success":true,...}`.

---

## Summary

| Patch | Route | Severity | Change |
|---|---|---|---|
| 1 | genai_debug_delete | Medium | Add `_csrf_token: 'TRUE'` |
| 2 | genai_debug_delete_all | Medium | Add `_csrf_token: 'TRUE'` |
| 3 | update_prompt | HIGH (upgraded) | Add `methods: [POST]` + `_csrf_token: 'TRUE'` |

All three patches are minimal, non-breaking routing changes. They do not affect response format, permissions, or controller logic. Requires `drush cr` after apply to rebuild route cache.
