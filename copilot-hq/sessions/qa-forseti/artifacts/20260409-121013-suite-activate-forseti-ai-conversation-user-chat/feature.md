# Feature Brief

- Work item id: forseti-ai-conversation-user-chat
- Website: forseti.life
- Module: ai_conversation
- Status: ready
- Release: 20260409-forseti-release-f
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (AI Conversation user-facing track 2.1)

## Summary

The `ai_conversation` module has a full admin/API surface and a `/ai-chat` route that redirects to a node-based chat (`/node/{node}/chat`). The user-facing experience is fragmented. This feature adds a dedicated user-facing AI chat page at `/forseti/chat` for authenticated users, with message history persisted per session, basic job-seeker context injection (user's name and active profile summary), and clean routing separate from the admin and node-specific routes.

## Goal

- Authenticated users can access a clean AI chat interface at `/forseti/chat` without navigating via a node.
- Conversation history persists within the session so the user can scroll back.
- The AI receives basic job-seeker context (name, active job title, profile summary) to give relevant advice.

## Acceptance criteria

- AC-1: Route exists — `GET /forseti/chat` renders for any user with `use ai conversation` permission; anonymous returns 403.
- AC-2: Message history — previous messages in the current session are rendered in the chat thread on page load (uses existing conversation history API: `ai_conversation.api_get_history`).
- AC-3: Send message — submitting a message calls `ai_conversation.send_message` (`POST /ai-conversation/send-message`) with CSRF header; response appended to thread without full page reload.
- AC-4: Context injection — the first system message for a new conversation includes: user's display name, active job title (from job seeker profile if available), and a brief profile summary (≤ 200 chars). Falls back gracefully if profile is incomplete.
- AC-5: Empty state — a fresh session with no history renders a welcome message and prompt hint ("Ask me anything about your job search").
- AC-6: Error state — if the API call fails, a user-facing error message is shown inline ("Could not reach the assistant. Please try again."); no blank or broken UI.

## Non-goals

- Streaming/real-time token output (deferred).
- Multi-conversation management / conversation list (deferred to forseti-ai-conversation-job-context).
- Mobile app integration (handled by existing REST API routes).

## Security acceptance criteria

- Authentication/permission surface: New route `forseti.chat` requires `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`. Anonymous users must receive 403. Users can only access their own conversation history (enforced by existing `ApiController` which scopes to `current_user`).
- CSRF expectations: Message send uses `ai_conversation.send_message` which already has `_csrf_token: 'TRUE'`. The new page JS must send the Drupal CSRF token in the `X-CSRF-Token` request header (standard Drupal AJAX pattern). No new CSRF surface is introduced beyond what already exists.
- Input validation requirements: User message input must be trimmed and length-limited (≤ 4000 chars) client-side and server-side. HTML tags in user input must be stripped before sending to the AI API.
- PII/logging constraints: Conversation content (user messages and AI responses) must NOT be written to Drupal watchdog. Only errors (API failure codes, exception type) may be logged, never message content.

## Implementation notes (to be authored by dev-forseti)

- New route: `forseti.chat` in `ai_conversation.routing.yml` (or a new routing file if the module is extended).
- Controller: extend `ChatController` or add `UserChatController` in `src/Controller/`.
- Template: reuse or extend `ai-conversation-chat.html.twig`.
- Context injection: call job_hunter's profile service (check `src/Service/` for `UserProfileService` or equivalent) from the AI controller to build the system context. Use a service dependency injection pattern.
- JS: standard Drupal AJAX (`Drupal.ajax` or `fetch` with `drupalSettings.path.baseUrl` + CSRF token from `/session/token`).

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous GET `/forseti/chat` → 403.
- TC-2: Authenticated GET `/forseti/chat` → 200, chat UI renders.
- TC-3: Send message with valid CSRF → response appears in thread.
- TC-4: Send message with missing/invalid CSRF → 403.
- TC-5: New session → welcome/empty state shown.
- TC-6: Existing session → prior messages rendered on page load.
- TC-7: AI API unavailable → user-facing error shown, no white screen.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (CEO dispatch, release-f grooming). Note: `/ai-chat` route already exists in `ai_conversation.routing.yml` (`start_chat`); new route should use `/forseti/chat` to match CEO-specified path. Confirm with pm-forseti whether `start_chat` route should be deprecated or redirected.
