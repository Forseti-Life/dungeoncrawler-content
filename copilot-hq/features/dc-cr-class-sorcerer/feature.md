# Feature Brief: Sorcerer Class Mechanics

- Work item id: dc-cr-class-sorcerer
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
- DB sections: core/ch03/Sorcerer
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Goal

Implement Sorcerer class mechanics — Bloodline (8 options), bloodline-determined spell list/tradition, spontaneous casting with Spell Slots, Signature Spells, and Bloodline Powers — so players can explore diverse magical heritages with flexible spell heightening.

## Source reference

> "A sorcerer's bloodline determines their spellcasting tradition and adds spells to their spell repertoire; Signature Spells can be freely heightened to any level for which the sorcerer has spell slots."

## Implementation hint

`Bloodline` is an enum (Aberrant/Angelic/Diabolic/Draconic/Fey/Hag/Imperial/Undead) stored on the Sorcerer entity; each bloodline maps to a tradition (arcane/divine/occult/primal), a list of bloodline spells added automatically, and 2 focus spells (bloodline powers). `SpontaneousCastingService` manages the spell repertoire and slot expenditure; Signature Spells are flagged on repertoire entries and bypass the normal heightening restriction. Bloodline spells are added to the repertoire at specific levels automatically during level-up processing; implement as a bloodline-keyed static spell table.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; bloodline selection immutable post creation; repertoire additions during level-up validated against allowed spell lists.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Bloodline enum restricted to valid 8 values; spell IDs for repertoire must belong to the bloodline's tradition; Signature Spell count capped server-side.
- PII/logging constraints: no PII logged; log character_id, bloodline, spell_cast_id, heightened_rank; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
