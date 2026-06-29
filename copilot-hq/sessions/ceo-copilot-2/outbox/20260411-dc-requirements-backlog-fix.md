---
id: 20260411-dc-requirements-backlog-fix
title: DungeonCrawler Roadmap Backlog Fix — Requirements Linked to Features
status: done
priority: high
---

## Summary

Fixed 1,545 orphaned `dc_requirements` rows that had `status=in_progress` but no `feature_id`. All requirements are now properly linked to backlog features in the release cycle.

## Root Cause

Previous backfill (2026-04-09) promoted rows from `pending` → `in_progress` but did not set `feature_id`. Column has NOT NULL constraint (defaults to empty string), so NULL-check in drush update missed the deferred-book rows. Fixed via direct MySQL.

## Actions Taken

### Batch 1 — Marked `implemented` (covering feature is done/shipped):
- core/ch03: Barbarian, Bard, Cleric, Druid, Fighter, Rogue, Sorcerer, Wizard
- apg/ch02: Swashbuckler, Witch, Core Class Expansions
- core/ch04: Athletics, Medicine, Occultism, Religion, Stealth, Thievery
- core/ch10: Environment, Experience Points and Advancement, Encounter Building, Creature Identification
- b1/* (all 18 rows — Bestiary 1 is done)

### Batch 2 — Linked `feature_id`, kept `in_progress` (feature is ready/active in backlog):
- core/ch07/* → dc-cr-spells-ch07 (135 rows)
- core/ch11/* → dc-cr-magic-ch11 (117 rows)
- gmg/ch01/* → dc-gmg-running-guide (132 rows)
- gam/* → dc-gam-gods-magic (36 rows)
- gmg/ch02/* → dc-gmg-hazards (6 rows)
- gmg/ch03-04/* → dc-gmg-subsystems (12 rows)
- apg/ch04/* → dc-apg-feats (61 rows)
- apg/ch05/* → dc-apg-equipment (38 rows)
- core/ch05/* → dc-cr-feats-ch05 (24 rows)
- core/ch10: Hazards, NPC Social Mechanics, Resting/Daily Prep, Treasure
- core/ch04: Acrobatics, Arcana, Crafting, Deception, Diplomacy, Intimidation, Lore, Nature, Performance, Society, Survival

### Batch 3 — Reset to `pending` (covering feature is deferred):
- b2/* (12 rows) → dc-b2-bestiary2 (deferred)
- b3/* (18 rows) → dc-b3-bestiary3 (deferred)
- gng/* (30 rows) → dc-gng-guns-gears (deferred)
- som/* (30 rows) → dc-som-secrets-of-magic (deferred)

## Final State

| Book | Implemented | In Progress | Pending | Total | % Done |
|---|---|---|---|---|---|
| core | 1,519 | 389 | 608 | 2,516 | 60.4% |
| apg | 496 | 99 | 0 | 595 | 83.4% |
| b1 | 18 | 0 | 0 | 18 | 100% |
| gmg | 0 | 150 | 0 | 150 | 0% (in backlog) |
| gam | 0 | 36 | 0 | 36 | 0% (in backlog) |
| b2/b3/gng/som | 0 | 0 | 60-90 | — | deferred |

**Orphaned in_progress:** 0 (was 1,545)

## Lesson Learned

`feature_id` column has NOT NULL constraint — drush `sql-query` silently fails when trying to SET NULL.
Use direct MySQL (`mysql -u root -p...`) for mass resets on constrained columns.
Added to knowledgebase.
