# Feature Brief: Cleric Class Mechanics

- Work item id: dc-cr-class-cleric
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Cleric
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Description
Implement the Cleric class mechanics: deity selection, doctrine (Cloistered/Warpriest), divine prepared spellcasting, divine font (heal/harm), channel energy, and all class feature unlocks. Covers core/ch03 Cleric section (~64 requirements). Requires full spellcasting system.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
