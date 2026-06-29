# Implement: forseti-ai-conversation-user-chat

- Release: 20260409-forseti-release-f
- Feature: `forseti-ai-conversation-user-chat`
- Module: `ai_conversation`
- AC file: `features/forseti-ai-conversation-user-chat/01-acceptance-criteria.md`
- Test plan: `features/forseti-ai-conversation-user-chat/03-test-plan.md`

## Summary
Add dedicated AI chat page at `/forseti/chat`. Authenticated users with `use ai conversation` permission get a chat interface with persisted session history and job-seeker context injection. Reuses existing `ai_conversation` API routes.

## Route decision (pm-forseti)
- Add redirect: `/ai-chat` → `/forseti/chat` (301) to eliminate duplicate entry points
- New route name: `forseti.chat` or `ai_conversation.forseti_chat`

## Key ACs
- AC-1: GET `/forseti/chat` renders for `use ai conversation` permission; anonymous → 403
- AC-2: Message history loaded on page render (most recent 20 messages)
- AC-3: Send message via POST to existing API; response rendered in chat thread
- AC-4: Job-seeker context injected into system prompt when `job_hunter` profile exists
- AC-5: `/ai-chat` redirects to `/forseti/chat` with 301

## CSRF note
- If any POST routes added: use split-route pattern (separate GET/POST entries; POST gets `_csrf_token: 'TRUE'`)
- Reference: `knowledgebase/lessons/` and prior forseti-csrf-fix feature

## Definition of done
- All ACs pass
- Commit hash + rollback note
- `drush cr` run after routing/template changes

## Verification
```bash
./vendor/bin/drush router:debug | grep forseti.chat
curl -si https://forseti.life/ai-chat  # should redirect 301 to /forseti/chat
curl -si https://forseti.life/forseti/chat  # 403 anonymous
```
