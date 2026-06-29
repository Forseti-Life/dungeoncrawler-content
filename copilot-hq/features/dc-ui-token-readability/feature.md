# Feature Brief: Token Readability and Status Badges

- Work item id: dc-ui-token-readability
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-n
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-ui-hexmap-thin-client, dc-ui-scene-layer-contract, dc-cr-conditions, dc-cr-encounter-rules
- Source: 2026-04-13 Dungeoncrawler UI architecture audit
- Category: ui-system
- Created: 2026-04-13

## Goal

Make on-map creatures and objects readable at a glance by adding token-level status presentation: health bars, team rings, conditions, selection/current-turn emphasis, and objective/interact markers. This reduces the need to click every token just to understand the scene.

## Source reference

Dungeoncrawler already exposes entity selection and inspector detail, but standard tabletop UX expects the board itself to carry more legible state. Players should be able to see the tactical picture directly from tokens before opening side panels.

## Implementation hint

Build the token presentation from ECS/render state and attach compact badges in a dedicated token-badge layer. Favor a restrained visual system that scales by zoom level and does not flood the board with text.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: only player-authorized token state is shown; hidden or GM-only data remains protected
- CSRF expectations: token badges are presentational and must not bypass secured mutation flows
- Input validation: badge state is derived from validated gameplay/entity state, not from client-authored values
- PII/logging constraints: token markers expose gameplay state only; no additional personal data retention

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
