# Feature Brief: APG New Spells

- Work item id: dc-apg-spells
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260408-dungeoncrawler-release-h
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, apg/ch05
- Category: spells
- Created: 2026-04-07
- DB sections: apg/ch05/Spell System (New Spells)
- Depends on: dc-cr-spellcasting

## Goal

Extend the spell catalog with 100+ APG non-focus spells across all four traditions — using the same spell schema as dc-cr-spells-ch07 — expanding the available spell pool for all spellcasting classes.

## Source reference

> "The Advanced Player's Guide adds over 100 new spells spanning all four magical traditions, with new options for all spellcasting classes."

## Implementation hint

APG spells use the same `Spell` entity schema as CRB spells (id, name, rank, traditions[], cast_time, components, range, area, targets, duration, save_type, effect_text, heightened_entries[]); add `source_book: apg` tag. Implement as a bulk data import extending the CRB spell catalog; validate all APG spells against the existing spell schema before insertion. Some APG spells are tradition-exclusive (e.g., divine-only or occult-only); ensure the traditions array is correctly populated and the spell list filter in character spell selection respects tradition exclusivity. After import, verify the spell selector surfaces APG spells when `source_filter` includes `apg`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Spell catalog is admin-write; spell casting actions scoped to owning character; spell selection gated by character tradition.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Traditions array values must be valid tradition enums; spell rank must be 1–10; source_book must be a valid value; heightened_entries rank deltas must be positive integers.
- PII/logging constraints: no PII logged; log import_batch_id, spells_imported, validation_errors[]; no PII logged; cast logging follows dc-cr-spells-ch07 pattern.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
