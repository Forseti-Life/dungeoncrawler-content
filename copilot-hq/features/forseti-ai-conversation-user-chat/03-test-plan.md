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
