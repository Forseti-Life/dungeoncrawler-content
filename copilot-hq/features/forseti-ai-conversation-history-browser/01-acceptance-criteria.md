# Acceptance Criteria: forseti-ai-conversation-history-browser

- Feature: forseti-ai-conversation-history-browser
- Module: ai_conversation
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Paginated conversation history at `/forseti/conversations`. Each entry has a Resume link and a Delete action. Extends `user-ai-conversations.html.twig`. The `/forseti/chat` page must accept `?conversation_id=N` to load a specific conversation.

## Acceptance criteria

### AC-1: Route and access
- `GET /forseti/conversations` returns 200 for users with `use ai conversation` permission.
- Anonymous access → 403.

### AC-2: Conversation list
- Page renders a list of the current user's conversations, each showing: title (or "Conversation {date}" if untitled), last-message preview, and last-updated timestamp.
- Rendered using `user-ai-conversations.html.twig` (or an extension of it).

### AC-3: Pagination
- 20 conversations per page.
- Page 2+ accessible via `?page=2`.

### AC-4: Resume link
- Each conversation entry has a "Resume" link → `/forseti/chat?conversation_id={id}`.
- The `/forseti/chat` controller must accept `?conversation_id=N` GET param and load that conversation's history instead of starting a new one.
- `conversation_id` belonging to another user → 403/404 in the chat controller.

### AC-5: Delete action
- Each entry has a "Delete" button.
- POST to `forseti.conversation_delete` (POST-only, CSRF-guarded).
- On success: page reloads with conversation removed from list.
- Missing/invalid CSRF → 403.
- Verify: `grep -A5 "forseti.conversation_delete" ai_conversation.routing.yml` shows `_csrf_token: 'TRUE'`.

### AC-6: Empty state
- When no conversations exist: empty-state message shown with a "Start your first conversation" link to `/forseti/chat`.

### AC-7: Existing chat works unchanged
- `/forseti/chat` without `?conversation_id` continues to work as before (new or most-recent conversation).

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'use ai conversation'` and `_user_is_logged_in: 'TRUE'`.
- ALL conversation queries scoped to `uid == current_user->id()`. No cross-user data.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversations` → 403 (anonymous).

### CSRF expectations
- Delete: POST-only, `_csrf_token: 'TRUE'` (split-route pattern).
- List and Resume: GET only, no CSRF.

### Input validation requirements
- `conversation_id` GET param on `/forseti/chat`: integer only; non-integer → 404.
- `page` param: positive integer; non-integer defaults to 1.
- Controller verifies conversation ownership before loading any messages.

### PII/logging constraints
- Conversation titles and message previews must NOT be logged.
- Delete: may log `conversation_id` (integer) at debug level only.

## Verification commands
```bash
# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/forseti/conversations
# Expected: 403

# CSRF on delete route
grep -A8 "forseti.conversation_delete" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml

# Route registered
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush router:debug | grep forseti.conversations

# Template exists
ls /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/templates/user-ai-conversations.html.twig
```
