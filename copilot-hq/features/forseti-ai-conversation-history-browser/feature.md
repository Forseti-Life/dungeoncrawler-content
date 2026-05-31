# Feature Brief

- Work item id: forseti-ai-conversation-history-browser
- Website: forseti.life
- Module: ai_conversation
- Project: PROJ-005
- Group Order: 2
- Group: history-export
- Group Title: History & Export
- Group Sort: 1
- Status: shipped
- Release: 20260409-forseti-release-g
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (AI Conversation context persistence track), BA grooming 2026-04-09

## Summary

The `ai_conversation` module has a `user-ai-conversations.html.twig` template and a `GET /api/ai-conversation/conversations` endpoint that returns the current user's conversation list. The `/forseti/chat` route (shipped release-f) always starts a new or resumes the most-recent conversation but provides no way to browse and resume older ones. This feature adds a conversation history browser at `/forseti/conversations` listing all past conversations, with a "Resume" link for each that opens the chat at that conversation's context.

## Goal

- Users can browse all their past AI conversations in a paginated list.
- Users can resume any prior conversation (continue the thread rather than starting fresh).
- Users can delete a conversation they no longer need.

## Acceptance criteria

- AC-1: Route `/forseti/conversations` renders a paginated list of the current user's conversations. Each item shows: conversation title (or date if untitled), last-message preview, and timestamp. Anonymous access → 403.
- AC-2: "Resume" link — each conversation entry links to `/forseti/chat?conversation_id={id}`. The chat page (`forseti.chat`) must accept `conversation_id` as a GET param and load that conversation's history on render.
- AC-3: Pagination — 20 conversations per page; `/forseti/conversations?page=2` loads the next 20.
- AC-4: Delete conversation — a "Delete" button per conversation POSTs to a CSRF-guarded endpoint; conversation is soft-deleted or removed; page reloads showing the conversation removed.
- AC-5: Empty state — if no conversations exist: a link to `/forseti/chat` is shown ("Start your first conversation").
- AC-6: `user-ai-conversations.html.twig` is used (or extended) for the conversation list rendering.

## Non-goals

- Conversation search / filter by keyword (deferred).
- Pinning / starring conversations (deferred).
- Shared/public conversations (out of scope per mission — no surveillance features).

## Security acceptance criteria

- Authentication/permission surface: Route requires `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`. Controller scopes ALL conversation queries to `uid == current_user->id()`. A user must never see another user's conversations.
- CSRF expectations: Delete action is POST-only with `_csrf_token: 'TRUE'` (split-route pattern). List and resume are GET — no CSRF needed.
- Input validation requirements: `conversation_id` GET param on `/forseti/chat` must be an integer. Controller verifies the conversation belongs to the current user before loading; invalid/foreign IDs → 403/404. `page` param must be a positive integer; non-integer defaults to 1.
- PII/logging constraints: Conversation titles and message previews must NOT be written to watchdog. Delete action may log `conversation_id` (integer) at debug level only.

## Implementation notes (to be authored by dev-forseti)

- New route: `forseti.conversations` (GET `/forseti/conversations`).
- Extend `ChatController` (or add `ConversationHistoryController`) to serve the list using `ApiController::getUserConversations` logic.
- Modify `ChatController` (or `/forseti/chat` page controller) to accept and respect `?conversation_id=N` GET param — load that conversation's history instead of starting a new one.
- New DELETE route: `forseti.conversation_delete` (POST-only + CSRF). Reuse or call `ApiController::deleteConversation` logic.
- Template: `user-ai-conversations.html.twig` (already scaffolded; extend for pagination and delete button).

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous GET `/forseti/conversations` → 403.
- TC-2: Authenticated page load → list renders with past conversations.
- TC-3: Resume link → opens `/forseti/chat?conversation_id={id}`; prior messages loaded in chat.
- TC-4: `?conversation_id=` belonging to another user → 403/404 in chat.
- TC-5: Non-integer `conversation_id` → 404, no PHP fatal.
- TC-6: Delete with valid CSRF → conversation removed from list.
- TC-7: Delete with invalid CSRF → 403.
- TC-8: No conversations → empty state with "Start your first conversation" link shown.
- TC-9: Pagination: > 20 conversations → second page accessible at `?page=2`.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (pm-forseti dispatch, release-g grooming). `user-ai-conversations.html.twig` template already exists. `forseti-chat.html.twig` template also observed in ai_conversation/templates/.
