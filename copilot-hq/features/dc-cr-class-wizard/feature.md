# Feature Brief: Wizard Class Mechanics

- Work item id: dc-cr-class-wizard
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release:
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Wizard
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Goal

Implement Wizard class mechanics — Arcane Spellbook, Arcane School (with extra slot and school spells), Arcane Thesis (4 options), and Drain Bonded Item — so players can experience the broadest arcane spell access with deep customization of magical specialization.

## Source reference

> "A wizard prepares spells each morning from their spellbook; they can add new spells to the spellbook by using Learn a Spell, spending 10 gp × the spell's rank in materials."

## Implementation hint

The Spellbook is a `SpellbookEntity` linked to the Wizard with a many-to-many to known spells; `PrepareSpellsService` populates daily prepared slots from the spellbook (one entry per slot except cantrips). Arcane School grants an extra spell slot at the school's rank and adds 2 school spells (focus spells) to the focus pool; implement school as an enum strategy. Arcane Thesis (Spell Blending, Spell Substitution, Improved Familiar Attunement, Experimental Spellshaping) each modify how prepared slots work; implement as plug-in modifiers on `PrepareSpellsService`. `DrainBondedItem` is a free action that recovers one expended spell slot per day; track `bonded_item_drained` as a boolean reset on daily prep.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; spellbook entries only modifiable by owning player or GM; Learn a Spell deducts gold server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Spell IDs must be from the arcane tradition list; school enum restricted to 8 valid schools; Arcane Thesis enum restricted to 4 valid options; gold deduction validated against character wealth.
- PII/logging constraints: no PII logged; log character_id, spell_learned, gold_spent, school; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
