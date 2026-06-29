# Feature Brief: APG Ancestries and Versatile Heritages

- Work item id: dc-apg-ancestries
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 1
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch01/Additional Ancestry Options (Existing Ancestries), apg/ch01/Ancestries Overview, apg/ch01/Backgrounds, apg/ch01/Catfolk (Amurrun), apg/ch01/Kobold, apg/ch01/Orc, apg/ch01/Ratfolk (Ysoki), apg/ch01/Tengu, apg/ch01/Versatile Heritages
- Depends on: dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-character-creation

## Description
Implement APG ancestry groups: Catfolk, Kobold, Orc, Ratfolk, Tengu (new ancestries), plus versatile heritages (Aasimar, Changeling, Dhampir, Duskwalker, Tiefling). Covers apg/ch02 ancestry sections.

## Security acceptance criteria

- Security AC exemption: game-mechanic character data logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
