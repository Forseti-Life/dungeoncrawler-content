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
