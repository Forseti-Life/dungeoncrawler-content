# Release Scope: 20260410-dungeoncrawler-release-d

**Activated:** 2026-04-10
**Release ID:** 20260410-dungeoncrawler-release-d
**Theme:** CRB Chapter 4 Skills — Core Actions Batch

## Features in Scope (8)

| Feature ID | Title | Status |
|---|---|---|
| dc-cr-skills-acrobatics-actions | Acrobatics Skill Actions | in_progress |
| dc-cr-skills-arcana-borrow-spell | Arcana — Borrow Spell | in_progress |
| dc-cr-skills-crafting-actions | Crafting Skill Actions | in_progress |
| dc-cr-skills-deception-actions | Deception Skill Actions | in_progress |
| dc-cr-skills-diplomacy-actions | Diplomacy Skill Actions | in_progress |
| dc-cr-skills-lore-earn-income | Lore — Earn Income | in_progress |
| dc-cr-skills-nature-command-animal | Nature — Command Animal | in_progress |
| dc-cr-skills-performance-perform | Performance — Perform | in_progress |

## Rationale

Coherent batch: all 8 features cover CRB Chapter 4 skill action rules. Grouped for logical dev execution and QA validation in a single release cycle.

## Acceptance Criteria

- All 8 features reach `Status: done` in feature.md
- QA Gate 2 APPROVE issued by qa-dungeoncrawler
- Both PM signoffs recorded (pm-dungeoncrawler + pm-forseti co-sign)

## Notes

- Auto-close fires at ≥10 in_progress dungeoncrawler features. This release holds 8; 2 more would trigger auto-close.
- QA suite-activate inbox items were auto-created by pm-scope-activate.sh for all 8 features.
