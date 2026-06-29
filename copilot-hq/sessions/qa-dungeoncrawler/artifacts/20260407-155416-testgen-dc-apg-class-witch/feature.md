# Feature Brief: Witch Class Mechanics (APG)

- Work item id: dc-apg-class-witch
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Witch)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Witch
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-spellcasting, dc-cr-focus-spells

## Description
Implement APG Witch class: patron selection, familiar as spell component, hexes (focus spells), and all class feature unlocks. Requires familiar system and spellcasting.

## Security acceptance criteria

- Security AC exemption: game-mechanic character data logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
