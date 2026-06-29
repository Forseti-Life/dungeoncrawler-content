# Feature Brief: Spellcasting Rules System

- Work item id: dc-cr-spellcasting
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release:
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement the core spellcasting rules: spell slots by level, casting traditions (arcane, divine, occult, primal), prepared vs. spontaneous casting, spell attack rolls, spell DCs, and heightening spells. This subsystem is required before any individual spells can function and is used by at least six of the twelve core classes.

## Source reference

> "Learn to kindle magic in the palm of your hand. This section includes the rules for spellcasting, hundreds of spell descriptions, focus spells used by certain classes, and rituals." (Chapter 7: Spells)

## Implementation hint

Character entity fields: `spell_slots{}` keyed by level (1–10), `spellcasting_tradition`, `casting_type` (prepared|spontaneous), `spell_attack_modifier`, `spell_dc`. API endpoints: prepare spells (for prepared casters), cast spell (deducts slot, applies effect). Spell save DCs = 10 + proficiency + key ability modifier.

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
