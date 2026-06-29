# Feature Brief: Oracle Class Mechanics (APG)

- Work item id: dc-apg-class-oracle
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Oracle)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Oracle
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-spellcasting, dc-cr-focus-spells

## Goal

Implement Oracle class mechanics — Mystery (determining spell list and Revelation Spells), the Cursebound accumulation mechanic with escalating Minor/Moderate/Major/Extreme curse conditions, and spontaneous divine casting — enabling a high-risk, cursed-power spellcaster identity.

## Source reference

> "When an oracle casts a Revelation Spell, they gain the Cursebound condition; as the curse progresses through minor, moderate, major, and extreme stages, the oracle suffers increasingly severe penalties."

## Implementation hint

`Mystery` is a required selection (Bones/Flames/Life/Lore/Stone/Tempest/etc.) stored on the Oracle entity; each Mystery determines the divine spell list subset and unlocks 3 Revelation Spells (focus spells). `CurseboundConditionManager` tracks `curse_stage` (0=none, 1=minor, 2=moderate, 3=major, 4=extreme); casting any Revelation Spell increments `curse_stage` and applies the stage-specific condition effects. Refocusing decrements `curse_stage` by 1. Extreme curse (stage 4) triggers an `OracleCurseOverwhelm` event with class-specific consequences. Spontaneous divine casting uses `SpontaneousCastingService` with the Mystery-determined spell list.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Cursebound stage managed server-side; Mystery selection immutable post creation.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Mystery enum restricted to valid Oracle Mystery options; Revelation Spell IDs validated against Mystery-specific spell list; Cursebound stage increments only via server-authoritative Revelation Spell cast events.
- PII/logging constraints: no PII logged; log character_id, revelation_spell_cast, curse_stage_before, curse_stage_after; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
