# Feature Brief: Encounter Creature XP Table

- Work item id: dc-cr-encounter-creature-xp-table
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Encounter Building
- Depends on: dc-cr-encounter-rules

## Description
Implement the PF2e level-difference XP table for creature encounter budgets. Currently `EncounterGeneratorService` hardcodes creature `xp_value` fields and does not compute XP dynamically from party_level − creature_level. Covers REQs 2314–2317.

Table to implement (party_level − creature_level → XP):
party-4=10, party-3=15, party-2=20, party-1=30, same=40, +1=60, +2=80, +3=120, +4=160.
Creatures >4 levels below = trivial (0 XP); >4 above = too dangerous (excluded).

Also covers: per-PC budget adjustment by Character Adjustment value (REQ 2312), and
level-variance guard in EncounterGeneratorService (REQ 2315).

Note: REQ 2317 (double XP for lagging PCs) deferred pending XP award system decision.

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2312, 2314, 2315, 2316, 2317
- See `runbooks/roadmap-audit.md` for audit process.
