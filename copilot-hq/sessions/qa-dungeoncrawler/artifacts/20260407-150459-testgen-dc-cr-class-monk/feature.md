# Feature Brief: Monk Class Mechanics

- Work item id: dc-cr-class-monk
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
- DB sections: core/ch03/Monk
- Depends on: dc-cr-character-class, dc-cr-focus-spells

## Description
Implement the Monk class mechanics: Flurry of Blows, ki spells (focus spells), stance system, unarmed strikes, and all class feature unlocks. Covers core/ch03 Monk section (~68 requirements). Partial focus spell dependency.

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
