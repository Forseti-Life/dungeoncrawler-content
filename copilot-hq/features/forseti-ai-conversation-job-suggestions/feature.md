# Feature Brief

- Work item id: forseti-ai-conversation-job-suggestions
- Website: forseti.life
- Module: ai_conversation
- Project: PROJ-005
- Group Order: 1
- Group: core-chat
- Group Title: Core Chat
- Group Sort: 2
- Status: shipped
- Release: 20260410-forseti-release-f
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: release-d backlog (dispatched 2026-04-10)

## Summary

The Forseti AI assistant at `/talk-with-forseti` currently responds to general queries but does not proactively surface the user's saved jobs when job-search intent is detected. This feature adds intent detection in `ChatController` (or `AIApiService`) that, when a user message contains job-search keywords (e.g., "find me a job", "what jobs match", "show my saved jobs"), fetches the top-3 matching saved jobs from `jobhunter_saved_jobs` for that user and includes them as structured cards in the assistant's response.

The `ChatController` already queries `jobhunter_job_seeker` and `jobhunter_job_history` for context — this extends that pattern to include saved-job retrieval and surfacing.

## Goal

When a logged-in user's chat message triggers job-suggestion intent, the assistant response includes:
- A natural-language intro ("Here are your top saved jobs that might match…")
- Up to 3 saved-job cards (job title, company, link to detail view)
- Graceful fallback when user has no saved jobs ("You haven't saved any jobs yet. Visit /jobhunter/discover to find jobs.")

## Non-goals

- Full semantic job-matching against all Google Jobs (separate scope)
- Unauthenticated job suggestion (no saved-job DB access possible)
- Re-ranking/ML scoring (future)

## Security acceptance criteria

See `01-acceptance-criteria.md` § Security acceptance criteria.
