# Feature Brief: Druid Class Mechanics

- Work item id: dc-cr-class-druid
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
- DB sections: core/ch03/Druid
- Depends on: dc-cr-character-class, dc-cr-spellcasting, dc-cr-animal-companion

## Description
Implement the Druid class mechanics: order selection (Animal/Leaf/Stone/Storm/Wild), anathema, primal spontaneous spellcasting, wild shape, and all class feature unlocks. Covers core/ch03 Druid section (~56 requirements). Requires spellcasting system.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
