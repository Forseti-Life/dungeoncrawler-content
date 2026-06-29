# Feature Brief: Encounter and Party Rail

- Work item id: dc-ui-encounter-party-rail
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-m
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-ui-hexmap-thin-client, dc-cr-action-economy, dc-cr-encounter-rules, dc-cr-conditions
- Source: 2026-04-13 Dungeoncrawler UI architecture audit
- Category: ui-system
- Created: 2026-04-13

## Goal

Replace the current thin initiative list with a proper encounter/party rail that makes turn order, team membership, health, and combat readiness legible at a glance. This gives the map the standard tabletop affordance of an always-available roster tied directly to board state.

## Source reference

The current map already exposes turn HUD and initiative state, but the roster is minimal and does not show the status details players expect in encounter play. Standard VTT/TTRPG UX calls for participant cards, current-turn emphasis, and compact status cues without forcing the player to inspect each token manually.

## Implementation hint

Build on the existing initiative order and ECS entity state already surfaced through `TurnManagementSystem`, `CombatSystem`, and `UIManager`. The rail should render from normalized turn/character state and remain responsive: vertical sidebar on desktop, compact strip on smaller screens.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: only encounter data already visible to session participants is rendered in the rail
- CSRF expectations: rail interactions that trigger mutations continue using existing combat endpoint protections
- Input validation: client-side selection/reordering affordances must not become authority for combat state; server remains authoritative
- PII/logging constraints: rail state contains gameplay metadata only; no new PII added to logs

## Roadmap section

- Roadmap: Dungeoncrawler UI modernization
