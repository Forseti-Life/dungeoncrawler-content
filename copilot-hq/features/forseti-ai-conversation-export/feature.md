# Feature Brief

- Work item id: forseti-ai-conversation-export
- Website: forseti.life
- Module: ai_conversation
- Project: PROJ-005
- Group Order: 2
- Group: history-export
- Group Title: History & Export
- Group Sort: 2
- Status: shipped
- Release: 20260409-forseti-release-g
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO direction 2026-04-09 (conversation export track), BA grooming 2026-04-09

## Summary

Users have no way to take their AI conversation history out of the platform. This feature adds an export action on the conversation page (`/forseti/chat`) and on the conversation history browser (`/forseti/conversations`): download a conversation as plain text (`.txt`) or a formatted HTML file. No PDF library dependency is required — plain text and HTML export are implementable using only Drupal's response primitives. A PDF option can be added in a later release if a PDF library is already available.

## Goal

- Users can download any of their conversations as a text file for offline reference.
- The export includes: conversation title, date, and all message turns labeled by role (User / Assistant).

## Acceptance criteria

- AC-1: Export button on `/forseti/chat` (active conversation) — a "Download conversation" link/button is present when the conversation has at least one message.
- AC-2: Export button on `/forseti/conversations` history list — each conversation entry has a "Download" link.
- AC-3: Export endpoint — `GET /forseti/conversation/{conversation_id}/export` returns a `Content-Disposition: attachment; filename="conversation-{id}-{date}.txt"` response with plain text content.
- AC-4: Plain text format — exported file contains: conversation title on line 1, date on line 2, blank line, then each message as `[User]: {message}\n` or `[Assistant]: {message}\n`.
- AC-5: Access control — users can only export their own conversations. Attempting to export another user's conversation → 403.
- AC-6: Empty conversation (0 messages) — export returns a 404 or a file with only the header lines (no blank export of another user's data).

## Non-goals

- PDF export (deferred; depends on PDF library availability).
- Sharing exports to a public URL (out of scope — contradicts privacy posture).
- Bulk export of all conversations at once (deferred).

## Security acceptance criteria

- Authentication/permission surface: Export route requires `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`. Controller verifies `conversation.uid == current_user->id()` before generating the response. Anonymous access → 403.
- CSRF expectations: Export is a GET request (file download) — no CSRF token required. No state is mutated by this action.
- Input validation requirements: `{conversation_id}` must be an integer. Non-integer → 404. Controller must verify ownership before any content is included in the response.
- PII/logging constraints: Conversation message content must NOT be written to watchdog at any level during export. The export response itself is the content delivery mechanism — no intermediate logging of message text. Error conditions (invalid ID, access denied) may log the conversation_id and error code only.

## Implementation notes (to be authored by dev-forseti)

- New route: `forseti.conversation_export` (`GET /forseti/conversation/{conversation_id}/export`).
- Controller method fetches all messages for the conversation (reuse `ApiController::getHistory` logic), then builds and returns a `\Symfony\Component\HttpFoundation\Response` with `Content-Type: text/plain` and `Content-Disposition: attachment`.
- Add "Download" button to `forseti-chat.html.twig` (check `conversation_id` is available in the template vars).
- Add "Download" link to `user-ai-conversations.html.twig` entries.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous GET `/forseti/conversation/1/export` → 403.
- TC-2: Authenticated export of own conversation → `Content-Disposition: attachment` response, plain text content with correct format.
- TC-3: Export of another user's conversation → 403.
- TC-4: Non-integer conversation_id → 404.
- TC-5: Export of empty conversation (0 messages) → 404 or minimal header-only file; no error.
- TC-6: Export button visible on `/forseti/chat` when conversation has messages.
- TC-7: Export link present on `/forseti/conversations` list entries.
- TC-8: File content is correctly formatted: title, date, then labeled turns.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (pm-forseti dispatch, release-g grooming). Note: if a PDF library (e.g., dompdf) is already present in Drupal composer dependencies, dev-forseti may optionally add a PDF export option in the same release. BA recommendation: start with plain text only to keep scope bounded.
