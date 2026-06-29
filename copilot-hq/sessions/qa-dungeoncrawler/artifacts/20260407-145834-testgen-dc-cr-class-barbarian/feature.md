# Feature Brief: Barbarian Class Mechanics

- Work item id: dc-cr-class-barbarian
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Barbarian
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-conditions

## Description
Implement the Barbarian class mechanics: Rage action (temp HP, damage bonus, AC penalty, concentrate restriction), Instinct selection (Animal/Spirit/personal), anathema tracking, and all class feature unlocks per advancement table (Brutality, Juggernaut, Weapon Specialization, etc.). Covers core/ch03 Barbarian section (~120 requirements). No spellcasting dependency.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
