# Feature Brief: Map-First Player Shell

- Work item id: dc-ui-map-first-player-shell
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-m
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-exploration-mode, dc-cr-encounter-rules
- Source: 2026-04-13 Dungeoncrawler UI architecture audit
- Category: ui-system
- Created: 2026-04-13

## Goal

Refactor the `/hexmap` experience into a map-first player shell so the board is the primary surface and debug-heavy supporting panels no longer dominate the play experience. The default player view should feel like a digital tabletop: board in the center, action controls anchored to the current mode, and supporting tools available without burying the scene.

## Source reference

The current product already exposes map, combat, inventory, quest, and chat systems, but the player-facing experience still presents developer/debug panels alongside the live game board. This feature hardens the shell so the default presentation prioritizes scene play instead of diagnostics.

## Implementation hint

Treat the existing Drupal + Pixi route as the stable platform boundary. Rework `hexmap-demo.html.twig` and `hexmap.js` so the default layout gives the board ~70-80% of desktop width, preserves existing action bars and chat hooks, and gates debug JSON/object-definition panels behind an explicit debug/admin mode instead of exposing them to all players.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: player shell visibility respects existing campaign ownership/session access checks; admin/debug controls are not exposed to unauthorized users
- CSRF expectations: UI-only shell changes do not weaken existing POST/PATCH protections on chat/combat/inventory endpoints
- Input validation: layout state persisted in browser storage is treated as presentation-only and never trusted for server mutations
- PII/logging constraints: no additional player-identifying data is logged; debug mode must not surface hidden/private session data to non-admin players

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
