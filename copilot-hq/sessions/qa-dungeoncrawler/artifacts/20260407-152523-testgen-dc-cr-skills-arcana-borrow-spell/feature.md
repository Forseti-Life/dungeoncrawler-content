# Feature Brief: Arcana — Borrow an Arcane Spell

- Work item id: dc-cr-skills-arcana-borrow-spell
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Arcana (Int)
- Depends on: dc-cr-skill-system, dc-cr-spellcasting

## Description
Implement Borrow an Arcane Spell action (REQs 1616–1618). Requires access to a
spellbook containing the spell; Arcana trained; exploration activity (10 min).
DC = spell's level via spell-level DC table. On success, add spell to daily
prepared list for that preparation. Dependencies on spellcasting system.

Also covers Recall Knowledge for Arcana-applicable creature types
(Constructs, Dragons, Elementals, Magical Beasts) — coordinated with
dc-cr-creature-identification.

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1616, 1617, 1618
- See `runbooks/roadmap-audit.md` for audit process.
