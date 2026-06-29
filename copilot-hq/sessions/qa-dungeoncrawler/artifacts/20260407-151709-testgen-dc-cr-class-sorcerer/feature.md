# Feature Brief: Sorcerer Class Mechanics

- Work item id: dc-cr-class-sorcerer
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
- DB sections: core/ch03/Sorcerer
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Description
Implement the Sorcerer class mechanics: bloodline selection (Imperial/Aberrant/Angelic/Demonic/Draconic/Elemental/Fey/Genie/Hag/Imperial/Nymph/Undead), bloodline spell, spontaneous spellcasting (tradition per bloodline), and all class feature unlocks. Covers core/ch03 Sorcerer section (~47 requirements). Requires full spellcasting.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
