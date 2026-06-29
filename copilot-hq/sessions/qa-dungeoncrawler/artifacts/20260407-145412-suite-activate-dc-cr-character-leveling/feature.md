# Feature Brief: Character Leveling and Advancement

- Work item id: dc-cr-character-leveling
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3 (complex; depends on feats system not yet in scope; deferred to release after next)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement the level-up flow that advances a character from their current level to the next (levels 1–20), applying new class features, ability boosts (at levels 5/10/15/20), additional skill increases, and new feats. This is the core progression loop that drives long-term player engagement in the dungeoncrawler game.

## Source reference

> "This section also covers how to build a character, as well as how to level up your character after adventuring."

## Implementation hint

Level-up API endpoint that reads the character's class advancement table and presents available choices (class feat, skill feat, general feat, ability boosts at milestone levels). Must gate on XP threshold or session milestone. Stores incremental changes on the character entity without overwriting prior state.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE`; admin-force level bypass requires `administer dungeoncrawler content`
- CSRF expectations: all POST level-up routes require `_csrf_request_header_mode: TRUE`
- Input validation: level integer validated against `MAX_LEVEL = 20`; invalid inputs return structured 4xx error; no unsanitized user content persisted
- PII/logging constraints: no PII logged; log character id + level transition only
