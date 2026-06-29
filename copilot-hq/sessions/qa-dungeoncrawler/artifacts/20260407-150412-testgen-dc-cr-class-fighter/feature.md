# Feature Brief: Fighter Class Mechanics

- Work item id: dc-cr-class-fighter
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-06
- DB sections: core/ch03/Fighter
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-encounter-rules

## Description
Implement the Fighter class mechanics: Attack of Opportunity reaction, weapon mastery, fighter class feats, and all class feature unlocks (Bravery, Shield Warden, Weapon Legend, etc.). Covers core/ch03 Fighter section (~84 requirements). No spellcasting dependency — highest priority martial class.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
