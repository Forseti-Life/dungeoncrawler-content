# Feature Brief: Cleric Class Mechanics

- Work item id: dc-cr-class-cleric
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260409-dungeoncrawler-release-f
20260409-dungeoncrawler-release-f
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Cleric
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Goal

Implement Cleric class mechanics — Doctrine (Cloistered/Warpriest), Divine Font (Heal/Harm), prepared Divine spellcasting, Domain Spells, and Channel Energy — so players can fulfill healing or battle-priest roles gated by deity alignment.

## Source reference

> "A cleric's Divine Font grants a number of additional heal or harm spells per day equal to 1 plus the cleric's Charisma modifier; Cloistered Clerics add them to their spell slots, while Warpriests can cast them as a 3-action variant."

## Implementation hint

`DivineFontService` computes available Font casts as `1 + cha_mod` at daily prep and stores them as a separate pool from normal spell slots; Font spell type (Heal/Harm) is derived from deity alignment (good=Heal, evil=Harm, neutral=either). Doctrine selection (Cloistered/Warpriest) modifies spell slot tables and proficiency progressions; implement as a Doctrine strategy object on the Cleric entity. Domain Spells use the focus pool (2 Focus Points from 2 deity domains); load domain spell IDs from the deity's domain list. Anathema violations should trigger a warning/flag on the character sheet.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; deity and doctrine selections immutable post character creation without GM override.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Deity ID must reference a valid deity; Doctrine enum restricted to [Cloistered, Warpriest]; Font spell type server-derived from deity alignment (not client input).
- PII/logging constraints: no PII logged; log character_id, font_cast_type, domain_spell_id; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
