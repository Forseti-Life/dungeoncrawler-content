# Acceptance Criteria: forseti-ai-conversation-export

- Feature: forseti-ai-conversation-export
- Module: ai_conversation
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Export any conversation as a plain text file. Export button on `/forseti/chat` (active conversation) and `/forseti/conversations` (history list). GET endpoint returns a file download. No state mutation — pure read.

## Acceptance criteria

### AC-1: Export route
- `GET /forseti/conversation/{conversation_id}/export` returns a file download response.
- Route name: `forseti.conversation_export`.
- Anonymous access → 403.
- `{conversation_id}` must be an integer; non-integer → 404.
- Controller verifies `conversation.uid == current_user->id()`; otherwise → 403.

### AC-2: Response format
- `Content-Type: text/plain; charset=UTF-8`
- `Content-Disposition: attachment; filename="conversation-{id}-{YYYYMMDD}.txt"`
- Body format:
  ```
  Conversation: {title or "Untitled"}
  Date: {created date, ISO 8601}

  [User]: {message text}
  [Assistant]: {response text}
  [User]: ...
  ```

### AC-3: Export button on chat page
- `/forseti/chat` renders a "Download conversation" link when the current conversation has at least 1 message.
- Link href: `/forseti/conversation/{conversation_id}/export`.
- No button shown when conversation has 0 messages.

### AC-4: Export link on history browser
- Each entry on `/forseti/conversations` has a "Download" link → `/forseti/conversation/{id}/export`.

### AC-5: Empty conversation
- Conversation with 0 messages: export returns 404 or a file with only the header lines (no blank record of another user's data).

### AC-6: Access control enforced
- Attempting to download another user's conversation: 403. No partial data returned.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`.
- Controller checks `conversation.uid == current_user->id()` before generating any output.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversation/1/export` → 403 (anonymous).

### CSRF expectations
- Export is a GET (file download) — no CSRF token required. No state is mutated.

### Input validation requirements
- `{conversation_id}`: integer only; non-integer → 404.
- No user input is included in the exported file beyond the conversation's own stored content.
- Message content is written as plain text (no HTML rendering); special characters are output as-is.

### PII/logging constraints
- Conversation message content must NOT be written to watchdog during export generation.
- Error events (invalid ID, access denied): log `conversation_id` + error code only.

## Verification commands
```bash
# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversation/1/export
# Expected: 403

# Route registered
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush router:debug | grep conversation_export

# PHP lint on controller
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php
```
