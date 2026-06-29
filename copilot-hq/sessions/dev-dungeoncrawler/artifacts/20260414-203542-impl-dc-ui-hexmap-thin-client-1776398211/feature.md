# Feature Brief: Hexmap Thin Client Runtime

- Work item id: dc-ui-hexmap-thin-client
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: unscoped
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-session-structure, dc-cr-npc-system
- Source: 2026-04-13 Hexmap backendization review
- Category: ui-system
- Created: 2026-04-13

## Goal

Refactor `hexmap.js` into a thin client so it behaves as a display/chat surface and intent sender, while gameplay authority moves to backend services. The browser should render scene state, capture user intent, and display server responses; combat, movement validity, interaction mutation, room transition state, and character state ownership should be backend-owned.

## Source reference

The current stack already has strong backend coverage for combat lifecycle, room chat, session chat, inventory, quests, entity movement, and server current-state sync. The cleanup target is the client runtime that still behaves like a partial game engine: ECS combat/movement orchestration, local world mutation, local room materialization, and mixed character/entity UI state.

## Implementation hint

Keep Pixi rendering and chat UX in the browser, but draw a hard line around authority:

1. Client sends intent (`move`, `attack`, `interact`, `talk`, `navigate`, `open panel`).
2. Backend validates, mutates state, and returns authoritative scene/participant deltas.
3. Client reconciles only from backend state and keeps local logic to presentation, selection, camera, and transient animation.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: all gameplay mutations continue to require server-side campaign/session/entity authorization
- CSRF expectations: client cleanup must not introduce any mutation path that bypasses existing protected POST/PATCH endpoints
- Input validation: movement, interaction, combat, and room-transition validation must be server-owned; client-produced coordinates or targets are advisory only
- PII/logging constraints: backendized state flows continue to log gameplay metadata only; no new personal data is introduced

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
