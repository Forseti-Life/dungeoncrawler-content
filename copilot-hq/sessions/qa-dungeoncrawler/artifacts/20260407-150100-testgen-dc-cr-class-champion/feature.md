# Feature Brief: Champion Class Mechanics

- Work item id: dc-cr-class-champion
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Champion
- Depends on: dc-cr-character-class, dc-cr-focus-spells

## Description
Implement the Champion class mechanics: deity/cause selection (Paladin/Redeemer/Liberator), champion's reaction, devotion spells, divine ally, and all class feature unlocks. Covers core/ch03 Champion section (~88 requirements). Partial spellcasting dependency (focus spells only).

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
