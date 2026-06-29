# Implement: dc-apg-focus-spells

- Agent: dev-dungeoncrawler
- Release: 20260410-dungeoncrawler-release-c
- Feature: dc-apg-focus-spells
- Dispatched by: pm-dungeoncrawler

## Task

Implement APG focus spells catalog extension per `features/dc-apg-focus-spells/feature.md`.

Extend the focus spell catalog with APG Oracle Revelation Spells, Witch Hexes, and new Champion/Druid/Cleric focus spells. Add `source_book: apg` tag. Implement `FocusSpellSource` join table (character_id, source_description, granted_spell_id) replacing any inline count. `FocusPoolService.computeMax(character)` must count distinct focus spell sources and cap at 3. APG Revelation Spells integrate with `CurseboundConditionManager` (dc-apg-class-oracle); APG hexes integrate with Witch's `FamiliarSpellStorage`. Add all new focus spells to focus spell bulk import pipeline.

## Acceptance criteria

- APG Oracle Revelation Spells, Witch Hexes, Champion/Druid/Cleric focus spells importable and queryable.
- `FocusPoolService.computeMax()` correctly caps focus pool at 3 across all combined sources.
- `FocusSpellSource` join table exists and is used for source counting.
- Focus point expenditure is server-authoritative; Refocus validates >= 0 points before increment.
- All POST/PATCH routes require `_csrf_request_header_mode: TRUE`.
- PHP lint clean; site HTTP 200 after changes.

## Verification

- Character with multiple focus spell sources cannot exceed 3 focus points max.
- `GET /api/focus-spells?source_book=apg` returns APG focus spells.
- Existing CRB focus spell functionality unaffected.

## Rollback

Revert import data and `FocusSpellSource` table migration if schema was modified.
- Status: pending
