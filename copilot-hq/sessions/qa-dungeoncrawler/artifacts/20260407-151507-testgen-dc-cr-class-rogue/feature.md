# Feature Brief: Rogue Class Mechanics

- Work item id: dc-cr-class-rogue
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Rogue
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-skill-system

## Description
Implement the Rogue class mechanics: Sneak Attack (precision damage), racket selection (Ruffian/Scoundrel/Thief), surprise attack, and all class feature unlocks (Deny Advantage, Debilitating Strike, Master Tricks, etc.). Covers core/ch03 Rogue section (~66 requirements). No spellcasting dependency.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
