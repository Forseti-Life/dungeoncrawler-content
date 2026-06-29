# Feature Brief: APG Focus Spells

- Work item id: dc-apg-focus-spells
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260408-dungeoncrawler-release-h
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch05
- Category: spells
- Created: 2026-04-06
- DB sections: apg/ch05/Focus Spells (APG)
- Depends on: dc-cr-focus-spells, dc-apg-class-oracle, dc-apg-class-witch

## Goal

Extend the focus spell catalog with APG Oracle Revelation Spells, Witch Hexes, and new Champion/Druid/Cleric focus spells — while enforcing the focus point maximum cap (max 3) across all combined focus spell sources.

## Source reference

> "Your focus pool's maximum number of Focus Points is equal to the number of focus spell sources you have, to a maximum of 3; you regain 1 Focus Point each time you Refocus."

## Implementation hint

APG focus spells use the same `FocusSpell` entity schema as CRB focus spells; add `source_book: apg` tag. `FocusPoolService.computeMax(character)` counts distinct focus spell sources (class, archetype, dedication, etc.) and caps at 3; this must be re-evaluated each time a new focus spell source is added. Implement a `FocusSpellSource` join table (character_id, source_description, granted_spell_id) replacing any inline count. APG Revelation Spells integrate with `CurseboundConditionManager` (dc-apg-class-oracle); APG hexes integrate with the Witch's `FamiliarSpellStorage`. Add all new focus spells to the focus spell bulk import pipeline.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; focus pool maximum enforced server-side; focus point expenditure server-authoritative.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Focus spell IDs must reference valid focus spell entities; focus pool maximum validated against source count (capped at 3); Refocus action validates ≥ 0 focus points before increment.
- PII/logging constraints: no PII logged; log character_id, focus_spell_cast, focus_points_before, focus_points_after; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
