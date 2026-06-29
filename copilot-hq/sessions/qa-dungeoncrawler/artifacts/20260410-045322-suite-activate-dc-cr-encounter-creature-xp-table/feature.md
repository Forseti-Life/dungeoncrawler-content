# Feature Brief: Encounter Creature XP Table

- Work item id: dc-cr-encounter-creature-xp-table
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
20260409-dungeoncrawler-release-h
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Encounter Building
- Depends on: dc-cr-encounter-rules

## Goal

Implement the dynamic creature XP table for encounter building — computing XP per creature from the party-level delta, supporting per-PC budget adjustments, and enforcing level-variance guards in EncounterGeneratorService.

## Source reference

> "The XP a creature is worth is based on the difference between its level and the party's level: a creature 4 levels below awards 10 XP, equal level awards 40 XP, and 4 levels above awards 160 XP."

## Implementation hint

Implement `CreatureXpCalculator.compute(creature_level, party_level)` using the canonical delta table (−4=10, −3=15, −2=20, −1=30, 0=40, +1=60, +2=80, +3=120, +4=160); return 0 for delta < −4 (trivial) and refuse encounters with delta > +4 (too dangerous). Replace all hardcoded `xp_value` fields in `EncounterGeneratorService` with calls to this calculator. Add `CharacterAdjustment` support: for each PC above/below 4, add/subtract a fixed XP amount from the budget. Implement level-variance guard: reject encounters where any single creature exceeds the per-encounter budget cap.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-scoped write for encounter creation; XP values computed server-side only, never submitted by client.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Creature level must be an integer 1–25; party level must be 1–20; PC count must be 1–8; XP budget cap enforced server-side.
- PII/logging constraints: no PII logged; log gm_id, encounter_id, creature_ids[], party_level, total_xp; no PII logged.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2312, 2314, 2315, 2316, 2317
- See `runbooks/roadmap-audit.md` for audit process.
