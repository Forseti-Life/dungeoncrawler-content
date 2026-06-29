# Feature Brief: Ancestry Traits System

- Work item id: dc-cr-ancestry-traits
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release: 20260408-dungeoncrawler-release-e
- Priority: P2 (spell/ability targeting prerequisite; deferred from current release — no spellcasting in scope yet)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5284–5583
- Category: rule-system
- Created: 2026-02-28

## Goal

Implement ancestry-granted creature traits (descriptors/tags) that attach to a character at character creation and govern how certain spells, effects, and abilities interact with them. For example, a dwarf character has the traits `Dwarf` and `Humanoid`. These traits have no passive mechanical benefit by themselves but are referenced by spells and abilities that say "affects Humanoids" or "targets a Dwarf." This system is a prerequisite for correct spell and ability targeting logic across the whole game.

## Source reference

> "Traits — These descriptors have no mechanical benefit, but they're important for determining how certain spells, effects, and other aspects of the game interact with your character."
> (Dwarf sidebar) "Dwarf, Humanoid"

## Implementation hint

Add a `traits` multi-value field to the character entity (populated from chosen ancestry at character creation). Create a `trait` taxonomy with entries such as `humanoid`, `dwarf`, `elf`, `gnome`, `goblin`, `halfling`, `human`. Spell/ability AI prompt engine and targeting logic must check `character.traits` when evaluating effect conditions (e.g., "if target has trait 'humanoid', apply X"). Traits also appear on creature/NPC entities for the same purpose. Depends on dc-cr-ancestry-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; no raw free-text user input stored without sanitization
- PII/logging constraints: no PII logged; gameplay action logs (character id, trait type) only
