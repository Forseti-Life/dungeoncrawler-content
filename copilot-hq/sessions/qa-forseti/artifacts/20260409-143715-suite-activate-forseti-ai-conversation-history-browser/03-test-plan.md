# Test Plan: forseti-ai-conversation-history-browser

- Feature: forseti-ai-conversation-history-browser
- Module: ai_conversation
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify conversation list, pagination, resume link (conversation_id param), delete CSRF, empty state, and access control at `/forseti/conversations`.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversations`
- Expected: `403`

### TC-2: Authenticated page load — list renders
- Steps: Log in as user with conversations; GET `/forseti/conversations`.
- Expected: 200; conversation entries listed with title, preview, timestamp.

### TC-3: Pagination
- Steps: User has > 20 conversations; GET `/forseti/conversations?page=2`.
- Expected: Second page of conversations rendered.

### TC-4: Resume link — loads specific conversation
- Steps: Click "Resume" on a conversation entry.
- Expected: `/forseti/chat?conversation_id={id}` opens; that conversation's history rendered in chat.

### TC-5: Resume — foreign conversation_id → 403/404
- Steps: As User A, GET `/forseti/chat?conversation_id={id_owned_by_B}`.
- Expected: 403 or 404.

### TC-6: Resume — non-integer conversation_id → 404
- Steps: GET `/forseti/chat?conversation_id=abc`
- Expected: 404.

### TC-7: Delete — valid CSRF → conversation removed
- Steps: Click "Delete" on a conversation (valid CSRF).
- Expected: List reloads; conversation no longer present.

### TC-8: Delete — invalid CSRF → 403
- Steps: POST to `forseti.conversation_delete` without CSRF token.
- Expected: 403.

### TC-9: Empty state
- Steps: Log in as user with no conversations.
- Expected: Empty state message with "Start your first conversation" link shown.

### TC-10: /forseti/chat without conversation_id unchanged
- Steps: GET `/forseti/chat` without param.
- Expected: Works as before (new or most-recent conversation); no regression.

### TC-11: Route registered
- Steps: `./vendor/bin/drush router:debug | grep forseti.conversations`
- Expected: Route listed.

## Regression notes
- `/forseti/chat` route must continue to work without `?conversation_id`.
- Existing admin AI conversation routes must be unaffected.
