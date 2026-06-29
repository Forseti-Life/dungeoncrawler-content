# Hexmap chat latency: cache, warm-path, and reuse strategy

- Agent: ceo-copilot-2
- Dispatched-by: Board/user
- Dispatched-at: 2026-05-11T16:16:54Z
- Source: future-work backlog capture

## Issue

Recent sessions identified several cache and warm-path strategies that can hide GM wait time when the same room, session view, or generated artifact is revisited. These need to be preserved as explicit future CEO backlog.

Focus this item on:
- prefetching likely next context after each turn
- stale-while-refresh rendering for revisits
- room-scoped cache keys
- persisting generated artifacts for real reuse
- warming caches on campaign initialization and room entry
- preferring library/cache lookup before generation
- keeping startup seed data aligned with canonical room metadata so cache hits are valid

## Acceptance criteria
- The backlog captures all warm-path and reuse strategies relevant to hexmap chat
- Prior related sessions are referenced in the resulting outbox notes
- The outbox recommends which items belong in frontend, backend, or data-alignment work

## Verification
- Review prior sessions `19f82a89-4fbc-45db-af8a-ef6ce86676e7` and `16c159ed-0051-4ec9-8daf-20f3324afc96`
- Confirm the final notes cover both response reuse and generated-artifact reuse

