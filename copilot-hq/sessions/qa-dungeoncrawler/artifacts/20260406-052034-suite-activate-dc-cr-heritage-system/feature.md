# Feature Brief: Heritage Selection System

- Work item id: dc-cr-heritage-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260406-dungeoncrawler-release-b
- Priority: P1 (character creation dependency; heritage selection step immediately follows ancestry in creation wizard)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5284–5583
- Category: game-mechanic
- Created: 2026-02-28

## Goal

Allow players to choose exactly one heritage for their character at 1st level during character creation. Heritages are subgroups within an ancestry that grant unique abilities reflecting the environment or lineage of that ancestry's population. Once chosen, a heritage cannot be changed. This system enables finer differentiation within a single ancestry (e.g., different dwarven heritages yield different ability sets) and is a required character creation step after ancestry selection.

## Source reference

> "You select a heritage at 1st level to reflect abilities passed down to you from your ancestors or common among those of your ancestry in the environment where you were born or grew up. You have only one heritage and can't change it later. A heritage is not the same as a culture or ethnicity, though some cultures or ethnicities might have more or fewer members from a particular heritage."

## Implementation hint

Add a `heritage` content type with fields: `parent_ancestry` (entity reference), `granted_abilities` (multi-value text or structured trait fields), `description`. Character creation flow must enforce: exactly one heritage selected, heritage's parent_ancestry must match chosen ancestry. Heritage selection step appears immediately after ancestry selection in the creation wizard. Depends on dc-cr-ancestry-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; no raw free-text user input stored without sanitization
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only
