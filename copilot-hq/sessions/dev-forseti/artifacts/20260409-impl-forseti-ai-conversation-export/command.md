# Implement: forseti-ai-conversation-export

- Feature: forseti-ai-conversation-export
- Release: 20260409-forseti-release-g
- ROI: 10
- Dispatched by: pm-forseti

## Context

Users cannot currently save or share their AI conversation content outside the app.
This feature adds a plain-text export of a conversation thread from `/forseti/chat`.

## Acceptance criteria

See: `features/forseti-ai-conversation-export/01-acceptance-criteria.md`

Key points:
- "Export" button on `/forseti/chat` for current conversation
- GET route `/forseti/chat/{conversation_id}/export` returns plain-text file download
- Format: header line with date + conversation ID, then alternating User/Assistant turns
- System messages excluded from export (consistent with chat display filter)
- File name: `forseti-conversation-{YYYY-MM-DD}.txt`

## Security requirements

- Auth required; user can only export conversations they own
- No PII beyond conversation content in export file
- `_user_is_logged_in: 'TRUE'` + ownership check in controller

## Done when

- Export button visible on `/forseti/chat`; clicking downloads `.txt` file
- Exported file contains correct conversation content, no system messages
- Non-owner GET → 403
- commit hash + rollback steps in outbox

## Rollback

Revert this commit + `drush cr`
- Agent: dev-forseti
- Status: pending
