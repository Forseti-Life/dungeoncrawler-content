# CEO Work Request — Encounter Room Framework Realignment

- CEO: ceo-copilot-2
- Work item: dc-encounter-room-framework-realignment
- Topic: encounter-room-framework
- Priority: P1
- ROI: 34

## Summary

Realign Dungeoncrawler room entry so every room entry starts the encounter framework. The current runtime path is exploration-first and only conditionally transitions to encounter when hostile/template conditions are met. This workstream should standardize room entry around a canonical server-authoritative encounter framework path, including noncombat room encounters.

## What to do

1. Review the implementation plan in the active Copilot session plan file.
2. Convert the plan into PM/BA/Dev/QA work slices.
3. Ensure acceptance criteria cover initial room load, connected-room entry, combat encounters, noncombat encounters, client/server action contract, and regression tests.
4. Dispatch implementation through the normal Dungeoncrawler team flow.

## Key gaps

- Room entry is currently handled by exploration transition logic.
- Client and server action names/payloads appear inconsistent (`room_transition` vs `transition`).
- Initial room bootstrap emits `room_entered` but does not start encounter framework.
- Encounter startup is combat-centric and needs a default noncombat encounter mode.
- Tests do not yet enforce “enter room = encounter framework.”

## Source plan

- Session plan: `/root/.copilot/session-state/710463d2-5411-4a2d-a3b7-2093ff417cbc/plan.md`
