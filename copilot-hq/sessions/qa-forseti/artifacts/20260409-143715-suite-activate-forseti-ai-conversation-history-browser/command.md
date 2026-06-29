# Suite Activation: forseti-ai-conversation-history-browser

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T14:37:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-conversation-history-browser"`**  
   This links the test to the living requirements doc at `features/forseti-ai-conversation-history-browser/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-conversation-history-browser-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-conversation-history-browser",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-conversation-history-browser"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-conversation-history-browser-<route-slug>",
     "feature_id": "forseti-ai-conversation-history-browser",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-conversation-history-browser",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
