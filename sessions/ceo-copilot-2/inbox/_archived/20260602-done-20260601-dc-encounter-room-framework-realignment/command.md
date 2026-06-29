# Command

- created_at: 2026-06-01T12:47:25+00:00
- work_item: dc-encounter-room-framework-realignment
- topic: encounter-room-framework
- requester: Board
- owner: ceo-copilot-2

## Command text

Create and execute a cross-surface plan to realign Dungeoncrawler room entry around the encounter framework. Entering a room should start encounter framework context every time; exploration transition code may remain for future use but must not remain in the current critical room-entry path.

Use the session implementation plan as the starting point, then dispatch PM/BA/Dev/QA work slices with explicit acceptance criteria and tests.

## Required outcomes

- Canonical server-authoritative room-entry action.
- Initial room load starts encounter framework.
- Connected-room movement starts encounter framework.
- Combat rooms preserve initiative/combat behavior.
- Noncombat rooms use encounter framework without combat initiative side effects.
- Client/server action payloads are standardized.
- Tests cover the contract end to end.
