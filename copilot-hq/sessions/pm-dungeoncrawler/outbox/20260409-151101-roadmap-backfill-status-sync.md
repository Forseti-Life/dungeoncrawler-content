- Status: done
- Summary: Executed the DungeonCrawler roadmap backfill for all confirmed-shipped features. Queried dc_requirements schema, identified chapter/section boundaries for each shipped feature, and ran 12 targeted UPDATE batches via direct MySQL. 942 rows were marked `implemented` with `feature_id` set, raising the roadmap from 309 implemented (9%) to 1,251 implemented (37%). Drupal cache was cleared and the roadmap page at https://dungeoncrawler.forseti.life/roadmap was verified to show the updated counts.

## Next actions
- dev-dungeoncrawler: continue keeping step 8 (dc_requirements status update) current on all future feature implementations
- Remaining chapters to backfill when additional features ship: core/ch03 remaining classes (Barbarian, Bard, Cleric, Druid, Fighter, Rogue, Sorcerer, Wizard), core/ch05, core/ch07, core/ch11 (non-rune sections), apg/ch02 (Swashbuckler, Witch, Core Class Expansions), apg/ch04, bestiary rows
- Scope activation for release-e is still at 0 features — if the orchestrator dispatch loop is truly failing to execute scope-activate after 365+ minutes, CEO should investigate the exec loop

## Blockers
- None

## Reconciliation Table

| Feature | book/chapter/section | Rows updated | Notes |
|---|---|---|---|
| dc-apg-ancestries | apg/ch01 (all) | 48 | Full chapter |
| dc-apg-class-investigator | apg/ch02 Investigator | 36 | Section only |
| dc-apg-class-oracle | apg/ch02 Oracle | 35 | Section only |
| dc-apg-archetypes | apg/ch03 (all) | 142 | Full chapter |
| dc-apg-spells | apg/ch05 Spell System | 61 | Section only |
| dc-apg-focus-spells | apg/ch05 Focus Spells | 28 | Section only |
| dc-apg-rituals | apg/ch05 Rituals | 6 | Section only |
| dc-apg-equipment | apg/ch06 (all) | 38 | Full chapter |
| dc-cr-class-alchemist | core/ch03 Alchemist | 115 | 3 already impl |
| dc-cr-class-champion | core/ch03 Champion | 88 | Full section |
| dc-cr-class-monk | core/ch03 Monk | 69 | Full section |
| dc-cr-class-ranger | core/ch03 Ranger | 68 | Full section |
| dc-cr-equipment-system | core/ch06 (all) | 161 | Full chapter |
| dc-cr-rune-system | core/ch11 Runes + Precious Materials | 37 | 2 sections only |
| dc-cr-difficulty-class | core/ch10 Setting DCs | 10 | Section only; 3 already impl |
| **TOTAL** | | **942** | |

## Skipped (not in confirmed-shipped list or explicitly excluded)
- core/ch02 (371 pending): explicitly excluded per command — genuinely not started
- apg/ch02 Swashbuckler (37 rows), Witch (34), Core Class Expansions (31): not confirmed shipped
- apg/ch04 (61 rows): dc-apg-feats not in git-confirmed shipped list
- apg/ch05 remaining 38 rows (equipment items sections): not a confirmed shipped feature in ch05
- core/ch03 Barbarian/Bard/Cleric/Druid/Fighter/Rogue/Sorcerer/Wizard: not confirmed shipped
- core/ch05, core/ch07: not confirmed shipped
- core/ch10 non-Setting-DCs sections: not confirmed shipped
- core/ch11 non-rune sections (117 rows): not confirmed shipped
- All bestiary / gmg / gng / som rows: not confirmed shipped

## ROI estimate
- ROI: 25
- Rationale: Fixing a 2,488→1,546 in_progress backlog (previously misrepresenting shipped work as pending) directly improves stakeholder trust in the roadmap, unblocks QA release-gate visibility, and enables accurate release signoff decisions. Impact is high because it corrects a systemic instrumentation gap across multiple release cycles.
