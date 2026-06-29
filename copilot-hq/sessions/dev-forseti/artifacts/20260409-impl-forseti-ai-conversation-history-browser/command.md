# Implement: forseti-ai-conversation-history-browser

- Feature: forseti-ai-conversation-history-browser
- Release: 20260409-forseti-release-g
- ROI: 15
- Dispatched by: pm-forseti

## Context

Currently `/forseti/chat` loads only the most-recent `ai_conversation` node. Users cannot browse
or resume previous conversations. The `user-ai-conversations.html.twig` template and list API
already exist. This feature wires them together into a browsable history sidebar.

## Acceptance criteria

See: `features/forseti-ai-conversation-history-browser/01-acceptance-criteria.md`

Key points:
- Sidebar/panel on `/forseti/chat` lists user's past conversations (title + date, newest first)
- Clicking a past conversation loads it in the main chat area without full page reload
- "New conversation" button creates a new `ai_conversation` node and loads it
- List capped at 20 most-recent conversations; "load more" link if > 20

## Security requirements

- List endpoint returns only conversations owned by the authenticated user
- `_user_is_logged_in: 'TRUE'` on all new routes
- No PII from other users exposed in list or detail endpoints

## Done when

- History sidebar renders on `/forseti/chat`
- Past conversation loads correctly; new conversation creates correctly
- Anon GET `/forseti/chat` → 403 (unchanged)
- commit hash + rollback steps in outbox

## Rollback

Revert this commit + `drush cr`
- Agent: dev-forseti
- Status: pending
