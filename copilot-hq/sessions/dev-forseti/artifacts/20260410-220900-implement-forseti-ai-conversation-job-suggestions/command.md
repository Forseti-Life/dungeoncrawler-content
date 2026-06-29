- Status: done
- Completed: 2026-04-11T00:15:48Z

# Implement: forseti-ai-conversation-job-suggestions

- Agent: dev-forseti
- Release: 20260410-forseti-release-f
- Feature: forseti-ai-conversation-job-suggestions
- Module: ai_conversation
- Date: 2026-04-10

## Task

Implement `forseti-ai-conversation-job-suggestions` per the acceptance criteria in `features/forseti-ai-conversation-job-suggestions/01-acceptance-criteria.md`.

## Summary

Extend `ChatController` (and/or `AIApiService`) so that when a logged-in user's chat message contains job-search intent keywords, the assistant response includes up to 3 saved-job cards from `jobhunter_saved_jobs` for that user. Graceful fallback if user has no saved jobs.

## Acceptance criteria (key items)

- **AC-1**: Trigger phrases (e.g. "show my saved jobs") → response JSON includes `job_suggestions` array with up to 3 entries, each with `title`, `company`, `link` keys.
- **AC-2**: Suggestions scoped to current user only (no cross-user data leak).
- **AC-3**: Fallback message when user has 0 saved jobs.
- **AC-4**: No suggestions when message has no trigger phrase.
- **Full AC**: `features/forseti-ai-conversation-job-suggestions/01-acceptance-criteria.md`

## Verification

```bash
# Authenticated POST to /api/chat
curl -s -X POST -b "$FORSETI_COOKIE_AUTHENTICATED" \
  -H "Content-Type: application/json" \
  -d '{"message":"show my saved jobs"}' \
  https://forseti.life/api/chat | python3 -m json.tool
# Expect: job_suggestions array with 1-3 entries (or fallback if no saved jobs)
```

## Scope

- Site: forseti.life
- Repo root: /home/ubuntu/forseti.life
- Module dir: sites/forseti/web/modules/custom/ai_conversation/
- Do NOT modify files outside ai_conversation or job_hunter modules unless strictly required.

## Done when

All AC items pass; code committed with unit/functional verification evidence in outbox.
- Status: pending
