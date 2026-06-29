# Feature Brief: Oracle Class Mechanics (APG)

- Work item id: dc-apg-class-oracle
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Oracle)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Oracle
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-spellcasting, dc-cr-focus-spells

## Description
Implement APG Oracle class: mystery selection (Ancestors/Battle/Bones/Flames/Lore/Lunar/Stone/Tempest/Time/Vitality/Void), divine curse, revelation spells, and all class feature unlocks. Requires focus spell and spellcasting systems.

## Security acceptance criteria

- Security AC exemption: game-mechanic character data logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
