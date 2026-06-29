# Suite Activation: forseti-ai-conversation-user-chat

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T12:10:13+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-conversation-user-chat"`**  
   This links the test to the living requirements doc at `features/forseti-ai-conversation-user-chat/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-conversation-user-chat-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-conversation-user-chat",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-conversation-user-chat"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-conversation-user-chat-<route-slug>",
     "feature_id": "forseti-ai-conversation-user-chat",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-conversation-user-chat",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-ai-conversation-user-chat

- Feature: forseti-ai-conversation-user-chat
- Module: ai_conversation
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify route access, message history on load, message send (CSRF), context injection, empty state, error state, and cross-user isolation for `/forseti/chat`.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/chat`
- Expected: `403`

### TC-2: Authenticated page load
- Steps: Log in as a user with `use ai conversation` permission; GET `/forseti/chat`.
- Expected: 200; chat interface renders with a text input and send button.

### TC-3: Empty/welcome state — new session
- Steps: Log in as a user with no prior conversation history; GET `/forseti/chat`.
- Expected: Welcome message / prompt hint shown. No blank page, no PHP error.

### TC-4: Prior message history rendered
- Steps: As a user with existing conversation history, GET `/forseti/chat`.
- Expected: Prior messages rendered in chronological order in the chat thread.

### TC-5: Send message — valid CSRF
- Steps: Type a message and submit. Verify `X-CSRF-Token` header is present in the AJAX request.
- Expected: AI response appended to thread within 30 seconds; no page reload.

### TC-6: Send message — missing CSRF token
- Steps: `curl -X POST https://forseti.life/ai-conversation/send-message -d '{"message":"test","conversation_id":1}'`
- Expected: 403.

### TC-7: Context injection — profile available
- Steps: Log in as a user with a complete job seeker profile (name + active job title); send first message in a new conversation.
- Expected: System context includes name and job title (verifiable via debug log if enabled, or by inspecting the AI's response contextual awareness).

### TC-8: Context injection — profile absent/incomplete
- Steps: Log in as a new user with no job seeker profile; start a conversation.
- Expected: Chat works normally; context injection skips gracefully (no PHP fatal, no error message to user).

### TC-9: API error state
- Steps: (QA to simulate) Trigger an API failure from `send-message` endpoint.
- Expected: User-facing inline error message shown; no white screen, no unhandled JS exception in console.

### TC-10: Cross-user history isolation
- Steps: As User A, attempt to fetch User B's conversation history (by manipulating `conversation_id`).
- Expected: 403 or 404; no User B data returned.

### TC-11: Message length limit
- Steps: Attempt to send a message > 4000 characters.
- Expected: Client-side `maxlength` prevents submission; if bypassed, server returns 400 with error message.

### TC-12: Route registered
- Steps: `./vendor/bin/drush router:debug | grep forseti.chat`
- Expected: Route is listed.

### TC-13: CSRF on send-message route intact
- Steps: `grep -A8 "ai_conversation.send_message" ai_conversation.routing.yml`
- Expected: `_csrf_token: 'TRUE'` present.

## Regression notes
- Existing admin routes (`/admin/config/ai-conversation/settings`, `/admin/reports/genai-usage`) must remain functional.
- Existing `ai_conversation.send_message` and history API routes must not be broken by this feature.
- `./vendor/bin/drush router:debug | grep ai_conversation` must return the same routes as before plus `forseti.chat`.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-ai-conversation-user-chat

- Feature: forseti-ai-conversation-user-chat
- Module: ai_conversation
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Add a dedicated user-facing AI chat page at `/forseti/chat`. Authenticated users (with `use ai conversation` permission) get a clean chat interface with persisted session history and job-seeker context injection. Reuses existing `ai_conversation` API routes.

## Acceptance criteria

### AC-1: Route
- `GET /forseti/chat` renders for users with `use ai conversation` permission.
- Anonymous GET → 403.
- Route name: `forseti.chat` (or `ai_conversation.forseti_chat`).
- Verify: `./vendor/bin/drush router:debug | grep forseti.chat`.

### AC-2: Message history on load
- On page load, the controller fetches the user's existing conversation history (via internal call or service, equivalent to `GET /api/ai-conversation/{id}/history`).
- Prior messages render in the chat thread in chronological order.
- If no prior conversation exists: empty/welcome state shown.

### AC-3: Send message
- User can type and submit a message; the message is sent via AJAX to `POST /ai-conversation/send-message`.
- Request includes `X-CSRF-Token` header (fetched from `/session/token` on page load).
- AI response is appended to the chat thread without a full page reload.
- On success: response appears within 30 seconds (or timeout error shown).

### AC-4: Context injection
- When a new conversation is created, the first system message includes:
  - User's display name.
  - Active job title from job seeker profile (if available).
  - Profile summary ≤ 200 characters (if available).
- Context injection is skipped gracefully if the job_hunter module is not present or profile is empty — no PHP fatal.

### AC-5: Empty/welcome state
- New session with no history: a welcome message and prompt hint shown (not a blank page or uncaught error).

### AC-6: API error state
- If `send-message` returns an HTTP error: a user-facing inline error message is shown.
- No white screen, no unhandled JS rejection visible to user.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'use ai conversation'` AND `_user_is_logged_in: 'TRUE'`.
- Anonymous GET → 403. Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/chat` → 403.
- Controller must only retrieve the current user's own conversation history (`uid = current_user->id()`). No cross-user data access.

### CSRF expectations
- `POST /ai-conversation/send-message` already has `_csrf_token: 'TRUE'` in routing. The chat page JS must fetch the Drupal session token from `GET /session/token` and include it as the `X-CSRF-Token` header on every send request.
- No new CSRF surface is added by this feature.
- Verify: `grep -A5 "ai_conversation.send_message" ai_conversation.routing.yml` still shows `_csrf_token: 'TRUE'`.

### Input validation requirements
- User message input: max 4000 characters enforced both client-side (HTML `maxlength`) and server-side (existing `sendMessage` controller must reject longer inputs with 400).
- HTML tags stripped from user input before sending to the AI API.
- Route path `/forseti/chat` contains no user-supplied parameters.

### PII/logging constraints
- Conversation message content (user messages and AI responses) must NOT be written to Drupal watchdog at any log level.
- API errors may be logged with: error code, exception class name, timestamp. No message content in log.
- Context injection data (display name, job title) must NOT be logged.

## Open question (flagged for pm-forseti)
- The existing `ai_conversation.start_chat` route (`GET /ai-chat`) partially overlaps this feature's purpose. Should it be deprecated (301 redirect to `/forseti/chat`) or left as-is? BA recommendation: redirect, to avoid duplicate entry points. PM to decide before dev begins.

## Verification commands
```bash
# Route registered
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush router:debug | grep forseti.chat

# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/chat
# Expected: 403

# CSRF on send-message route still intact
grep -A8 "ai_conversation.send_message" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml

# PHP lint on new/modified controller
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php
```
