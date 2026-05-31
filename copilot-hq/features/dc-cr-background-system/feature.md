# Feature Brief: Background System

- Work item id: dc-cr-background-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260406-dungeoncrawler-release-next
- Priority: high (required prerequisite for character creation workflow; provides ability boosts, skill training, and skill feat to character)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Allow players to select a background during character creation that represents their character's history before adventuring. Each background grants two ability boosts (one fixed, one flexible), training in a specific skill, a specific Lore skill, and a skill feat. Backgrounds add narrative flavor and mechanical differentiation without restricting class choice.

## Source reference

> "pick a background that fleshes out what your character did before becoming an adventurer."

## Implementation hint

Content type: `background` with fields for ability boosts (fixed + free), skill training, lore skill, and granted skill feat. Character creation step must apply these to the character entity. Core Rulebook defines backgrounds such as Acolyte, Acrobat, Animal Whisperer, Artisan, Artist, Barkeep, etc.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; no raw free-text user input stored without sanitization
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only
