# Test Plan: forseti-ai-conversation-export

- Feature: forseti-ai-conversation-export
- Module: ai_conversation
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify plain text file download, correct format, export button on chat and history pages, access control, and empty conversation handling.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversation/1/export`
- Expected: `403`

### TC-2: Authenticated export — file download response
- Steps: As authenticated user with a conversation, GET `/forseti/conversation/{own_id}/export`.
- Expected: HTTP 200; `Content-Disposition: attachment; filename="conversation-{id}-{date}.txt"`; `Content-Type: text/plain`.

### TC-3: File content format
- Steps: Download a conversation with 2+ message turns; inspect file.
- Expected: Line 1 = "Conversation: {title}"; Line 2 = "Date: {ISO date}"; blank line; then alternating `[User]:` and `[Assistant]:` lines.

### TC-4: Export another user's conversation → 403
- Steps: As User A, GET `/forseti/conversation/{id_owned_by_B}/export`.
- Expected: 403. No content returned.

### TC-5: Non-integer conversation_id → 404
- Steps: GET `/forseti/conversation/notanid/export`
- Expected: 404.

### TC-6: Empty conversation
- Steps: Export a conversation with 0 messages.
- Expected: 404 or minimal header-only file. No error. No other user's data.

### TC-7: Export button on chat page
- Steps: Open `/forseti/chat` with an active conversation (≥1 message).
- Expected: "Download conversation" link visible.

### TC-8: No export button — empty chat
- Steps: Open `/forseti/chat` with a new conversation (0 messages).
- Expected: No export link shown.

### TC-9: Export link on history browser
- Steps: View `/forseti/conversations` list.
- Expected: Each conversation entry has a "Download" link.

### TC-10: Route registered
- Steps: `./vendor/bin/drush router:debug | grep conversation_export`
- Expected: Route listed.

## Regression notes
- Chat and conversation history pages must remain functional after export link is added.
- Existing `ai_conversation` API routes must not be broken.
