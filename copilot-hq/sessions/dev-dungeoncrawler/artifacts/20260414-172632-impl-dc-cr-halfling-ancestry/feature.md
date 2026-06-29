# Feature Brief: Halfling Ancestry

- Work item id: dc-cr-halfling-ancestry
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: (set by PM at activation)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), Chapter 2 — Ancestries & Backgrounds
- Category: game-mechanic
- Created: 2026-04-11
- DB sections: core/ch02/Halfling Ancestry, core/ch02/Halfling Ancestry Feats, core/ch02/Halfling Heritages

## Goal

Implement the halfling ancestry for character creation. Halflings are small, nimble people who grant +2 DEX/+2 WIS (or any two ability boosts), 6 HP, Small size, 25-ft speed, keen eyes, and halfling luck. Ancestry feats and heritage options (gutsy, hillock, nomadic, observant, twilight, wildwood) are in scope.

## Source reference

> "Halflings claim no place as their own but have spread far and wide, adapting to a great variety of environments and societies."

## Implementation hint

Content type `ancestry` entry for halfling. Heritage entries: gutsy halfling, hillock halfling, nomadic halfling, observant halfling, twilight halfling, wildwood halfling. Ancestry feat catalog from Core Rulebook ch02. Depends on dc-cr-ancestry-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: ancestry selection is character-scoped write only; ancestry assignment and recalculation must be server-authoritative.
- CSRF expectations: all POST/PATCH requests in character creation and ancestry-selection flows require `_csrf_request_header_mode: TRUE`.
- Input validation: halfling HP, size, speed, luck, and keen-eyes bonuses are server-defined values; clients cannot override ancestry stats or passive ancestry traits.
- PII/logging constraints: no PII logged; log character_id, ancestry_id, heritage_id only.
