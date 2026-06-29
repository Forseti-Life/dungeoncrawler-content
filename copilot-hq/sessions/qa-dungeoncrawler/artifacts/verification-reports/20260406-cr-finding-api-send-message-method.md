# Verification Report: cr-finding-api-send-message-method

- **Inbox item:** 20260406-unit-test-20260406-cr-finding-api-send-message-method
- **Dev commit:** b6f0d8c1082d524fb050eb3ba502861ca5723fdc
- **QA verdict:** APPROVE
- **Date:** 2026-04-06

## Acceptance Criteria Verification

### AC1 — GET requests to `/api/ai-conversation/{id}/message` return 405
- **Fix:** `_method: 'POST'` (unenforced `requirements:` key) replaced with `methods: [POST]` at route level in `ai_conversation.routing.yml`.
- **Live probe:** `curl -sk -o /dev/null -w "%{http_code}" https://dungeoncrawler.forseti.life/api/ai-conversation/1/message?_format=json`
  - Returns: **405** ✅ (GET request correctly blocked)
- **Result:** PASS ✅

### AC2 — POST without CSRF header returns 403
- **Fix:** `_csrf_request_header_mode: TRUE` added to route requirements.
- **Live probe:** `curl -X POST https://dungeoncrawler.forseti.life/api/ai-conversation/1/message?_format=json` (no CSRF header)
  - Returns: **403** ✅ (CSRF enforcement working)
- **Result:** PASS ✅

### AC3 — Debug `logInfo()` flood blocks removed from `invokeModelDirect()`
- **Fix:** Two `logInfo()` DEBUG blocks removed from `AIApiService.php::invokeModelDirect()`:
  - Block 1: `📤 Sending to Bedrock: max_tokens=@max_tokens, model=@model, prompt_chars=@prompt_chars`
  - Block 2: `📥 Bedrock Response: input_tokens_actual=@input, output_tokens_actual=@output, stop_reason=@stop, duration_ms=@duration`
- **Evidence:** `grep -c 'Sending to Bedrock: max_tokens\|Bedrock Response: input_tokens_actual' AIApiService.php` → **0** ✅
- **Result:** PASS ✅

## File verification

- `ai_conversation.routing.yml` line 152: `methods: [POST]` — confirmed
- `ai_conversation.routing.yml` line 155: `_csrf_request_header_mode: TRUE` — confirmed
- `ai_conversation.routing.yml` line 145/184: legacy `_method:` keys remain on non-API routes (form-based, not security-critical — pre-existing, non-blocking)
- Prod and dev share the same inode (same filesystem symlink/mount) — fix deployed to production at commit time.

## Advisories

- **Pre-existing:** Lines 113 and 145 still use `_method:` requirement key on non-API routes (`ai_conversation.send_message` form path). These are lower-risk form routes handled by Drupal Form API CSRF, not a security gap, but should be cleaned up in a future maintenance pass.

## Verdict

**APPROVE** — Both security findings resolved: POST method enforcement active (GET → 405), CSRF header enforcement active (no-header POST → 403), debug log flood eliminated.
