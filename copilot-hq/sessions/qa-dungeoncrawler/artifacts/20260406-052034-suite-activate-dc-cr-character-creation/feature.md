# Feature Brief: Character Creation Workflow

- Work item id: dc-cr-character-creation
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: high (first end-to-end player journey; onboarding experience for every new dungeoncrawler player; depends on ancestry, background, and class all being implemented first)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement the end-to-end character creation flow that guides a player from blank sheet to a fully built 1st-level character. The workflow sequences: ancestry selection → background selection → class selection → ability score assignment → skill/feat selection → equipment choice → final calculations. This is the onboarding experience for every new dungeoncrawler player.

## Source reference

> "This section also covers how to build a character, as well as how to level up your character after adventuring."
> "The new Pathfinder rules are easier to learn and faster to play, and they offer deeper customization than ever before!"

## Implementation hint

Multi-step wizard UI or API endpoint sequence. Each step calls the relevant content type (ancestry, class, background) and accumulates choices into a character entity. Final step computes derived stats (AC, HP, saves, perception). Consider a character-draft state that persists partial creation.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; no raw free-text user input stored without sanitization
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only
