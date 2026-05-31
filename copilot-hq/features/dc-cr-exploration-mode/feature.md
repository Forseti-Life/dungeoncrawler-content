# Feature Brief: Exploration Mode

- Work item id: dc-cr-exploration-mode
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260410-dungeoncrawler-release-b
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement exploration mode as the between-encounter play mode where time is tracked in minutes/hours and characters perform exploration activities (Avoid Notice, Detect Magic, Search, Scout, etc.). Characters maintain their exploration activity between encounters; this activity can affect initiative and starting conditions when an encounter begins. Exploration mode drives the dungeon-crawling experience between fights.

## Source reference

> "This includes rules on encounters, exploration, and downtime, as well as everything you need for combat." (Chapter 9: Playing the Game)

## Implementation hint

Mode flag on session state: `mode` = encounter | exploration | downtime. In exploration mode, characters select an ongoing exploration activity. API: set/get exploration activity per character, apply activity bonuses to initiative rolls when transitioning to encounter mode. Activities reference the skill and action systems.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; mutations server-validated against allowed values
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only

## Roadmap section

- Roadmap: Core Rulebook
