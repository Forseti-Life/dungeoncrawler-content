# Feature Brief: Core Book Chapter 7 — Spellcasting Rules

- Work item id: dc-cr-spells-ch07
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch07
- Category: spells
- Created: 2026-04-07
- DB sections: core/ch07/Arcane Spell List (Summary), core/ch07/Casting Spells, core/ch07/Divine Spell List (Summary), core/ch07/Focus Spells by Class, core/ch07/Occult Spell List (Summary), core/ch07/Primal Spell List (Summary), core/ch07/Special Spell Types, core/ch07/Spell Slots and Spellcasting Types, core/ch07/Spell Stat Block Format, core/ch07/Traditions and Schools
- Depends on: dc-cr-spellcasting, dc-cr-focus-spells, dc-cr-rituals

## Goal

Implement the spell catalog — covering all CRB spells (levels 1–10) with full metadata (traditions, cast time, components, range, area, targets, save, duration, heightened effects) and cantrip auto-heightening — as the foundational spell database for all spellcasting features.

## Source reference

> "Cantrips are spells that don't expend spell slots; a cantrip is automatically heightened to half your level rounded up, so a 5th-level caster casts cantrips as 3rd-rank spells."

## Implementation hint

Define a `Spell` entity with fields: id, name, rank (1-10 for spells, 0 for cantrips), traditions[], cast_time, components[] (verbal/somatic/material/focus), range, area, targets, duration, save_type, effect_text, heightened_entries[]. `HeightenedEntry` is a sub-entity with rank_delta and modified_fields (JSON diff). `CantripHeighteningService` auto-computes the effective rank as `ceil(caster_level / 2)`. Implement a bulk import for ~400 CRB spells from structured JSON; ensure traditions is an array to support multi-tradition spells.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Spell catalog is read-only for all users; spell casting actions scoped to owning character's turn.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Spell ID must reference a valid spell; heightened rank must not exceed caster's max rank; traditions array must contain only valid tradition values.
- PII/logging constraints: no PII logged; log character_id, spell_id, heightened_rank, target_ids; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
