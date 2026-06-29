# Feature Brief: XP Award System

- Work item id: dc-cr-xp-award-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Experience Points and Advancement
- Depends on: dc-cr-encounter-creature-xp-table, dc-cr-character-leveling

## Goal

Implement the XP award system — awarding XP from creature defeats, quest objectives, exploration achievements, and narrative milestones — with party-wide distribution, level-up triggering at 1000 XP, and an optional milestone leveling mode.

## Source reference

> "At the end of a session or encounter, the GM awards XP to the entire party; when a character's XP total reaches 1,000, they level up and their XP resets to 0."

## Implementation hint

`XpAwardService.award(session, source_type, xp_amount)` distributes XP evenly across all participating characters; source types are: creature_defeat (uses XP table from `dc-cr-encounter-creature-xp-table`), quest_objective, exploration, narrative. Persist each award as a `XpAwardEvent` entity (character_id, source_type, amount, timestamp) for audit. On each award, check if any character's total ≥ 1000; if so trigger `LevelUpService.stageLevelUp(character_id)` and reset XP to 0. Milestone mode is a campaign-level flag; when enabled, bypass XP tracking and trigger level-up directly on GM command.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: GM-scoped write for XP awards and milestone level-ups; players read their own XP total.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: XP amount must be a positive integer; source_type must be a valid enum; character IDs in party must be valid active characters.
- PII/logging constraints: no PII logged; log gm_id, session_id, source_type, xp_amount, character_ids[], level_up_triggered; no PII logged.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2332–2335, 2337–2339
- See `runbooks/roadmap-audit.md` for audit process.
