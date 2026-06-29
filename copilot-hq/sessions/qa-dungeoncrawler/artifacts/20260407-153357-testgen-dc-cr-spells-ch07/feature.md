# Feature Brief: Core Book Chapter 7 — Spellcasting Rules

- Work item id: dc-cr-spells-ch07
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch07
- Category: spells
- Created: 2026-04-07
- DB sections: core/ch07/Arcane Spell List (Summary), core/ch07/Casting Spells, core/ch07/Divine Spell List (Summary), core/ch07/Focus Spells by Class, core/ch07/Occult Spell List (Summary), core/ch07/Primal Spell List (Summary), core/ch07/Special Spell Types, core/ch07/Spell Slots and Spellcasting Types, core/ch07/Spell Stat Block Format, core/ch07/Traditions and Schools
- Depends on: dc-cr-spellcasting, dc-cr-focus-spells, dc-cr-rituals

## Description
Implement full spellcasting system: casting mechanics (actions for spells, verbal/somatic/material components), spell slots, prepared vs. spontaneous, all four traditions (arcane/divine/occult/primal), spell list summaries per tradition, focus spells by class, special spell types (cantrips, rituals, heightening). Covers core/ch07 (~135 reqs). This is a high-complexity foundation feature.

## Security acceptance criteria

- Security AC exemption: game-mechanic spellcasting system; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
