# Feature Brief: Witch Class Mechanics (APG)

- Work item id: dc-apg-class-witch
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Witch)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Witch
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-spellcasting, dc-cr-focus-spells

## Goal

Implement Witch class mechanics — Patron (determining spell list), mandatory Familiar (delivers touch spells), Hex Cantrips and Lessons (expanding focus spell pool), and prepared spellcasting through familiar teachings — enabling a familiar-dependent, hex-focused arcane/occult/primal caster.

## Source reference

> "A witch's patron teaches spells through their familiar; during daily preparations the witch can prepare any spell in their familiar's spell storage, which is determined by the patron."

## Implementation hint

`Patron` is a required selection stored on the Witch entity; each Patron maps to a spell tradition and list of lesson-granted hexes. Familiar is mandatory (not optional as for Wizards); `FamiliarEntity` stores the witch's spell storage — on daily prep, `PrepareSpellsService` reads from the familiar's storage rather than a spellbook. Hexes are focus spells gained via Lessons (feat selection); `LessonSystem` grants a hex cantrip plus a non-hex spell added to familiar storage. `HexCantrip` casting uses the focus pool; `RefocusAction` restores 1 Focus Point. Familiar must be alive and accessible for daily spell prep; if familiar is dead, implement familiar re-summoning rules (1 week ritual).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Patron and familiar selections immutable post creation; familiar HP managed server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Patron enum restricted to valid Witch Patron options; Lesson feat IDs validated against Witch feat list; familiar re-summoning ritual deducts materials server-side.
- PII/logging constraints: no PII logged; log character_id, patron, lesson_feats[], hex_cast_id, focus_points_remaining; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
