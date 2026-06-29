# CEO Outbox: Deferred Features Grooming Complete

**Date:** 2026-04-07  
**Release:** 20260406-dungeoncrawler-release-b  
**Commit:** ae21164ec

## Summary

All 37 deferred DungeonCrawler features have been fully triaged, groomed, and submitted to the QA pipeline.

## Decisions Made

### PROMOTED to planned (11 features — full pipeline complete)
| Feature | Priority | Description |
|---|---|---|
| dc-cr-spellcasting | P1 | Spell slots, traditions, prepared/spontaneous, DCs |
| dc-cr-session-structure | P1 | Session/campaign data model, persistence |
| dc-cr-multiclass-archetype | P2 | Archetype feat system, dedication feats |
| dc-cr-npc-system | P2 | NPC content type, attitude, social mechanics |
| dc-cr-tactical-grid | P2 | 5-ft grid, movement, reach, flanking, cover |
| dc-cr-exploration-mode | P2 | Exploration activities, time scale, initiative |
| dc-cr-downtime-mode | P2 | Earn Income, Retraining, Crafting time |
| dc-cr-familiar | P2 | Familiar abilities, Witch patron vessel |
| dc-cr-animal-companion | P2 | Stat block, advancement, Command action |
| dc-cr-crafting | P3 | Prerequisites, time/cost model, Alchemist daily |
| dc-cr-gm-narrative-engine | P3 | AI GM context, NPC dialogue, session summaries |

All 11 promoted features: AC written, security AC patched, pm-qa-handoff.sh run ✅

### CONSOLIDATED (8 features — marked done, covered by chapter features)
- dc-cr-alchemical-items → dc-cr-equipment-ch06
- dc-cr-magic-items → dc-cr-magic-ch11
- dc-cr-focus-spells → dc-cr-spells-ch07
- dc-cr-rituals → dc-cr-spells-ch07
- dc-cr-skill-feats → dc-cr-feats-ch05
- dc-cr-general-feats → dc-cr-feats-ch05
- dc-cr-gm-tools → dc-gmg-running-guide
- dc-cr-xp-rewards → dc-cr-xp-award-system

### ARCHIVED (12 granular sub-stubs — covered by dc-cr-dwarf-ancestry bulk AC)
All 4 dwarf heritages, 6 dwarf ancestry feats, dc-cr-elf-heritage-arctic, dc-cr-ancestry-feat-schedule

### KEPT DEFERRED (6 other-book features — out of scope)
dc-b1/b2/b3-bestiary, dc-gam-gods-magic, dc-gng-guns-gears, dc-som-secrets-of-magic

## Dependency Stubs
All 7 previously-missing dependency stubs were confirmed to already exist in the repo:
dc-cr-equipment-system, dc-cr-heritage-system, dc-cr-elf-ancestry, dc-cr-character-leveling, dc-cr-conditions, dc-cr-ancestry-traits, dc-cr-encounter-rules

## Roadmap DB
No DB status changes needed — core/APG/GMG already at 0 pending; b1/b2/b3/gam/gng/som remain pending (appropriately deferred).

## Next Steps
QA agent has 11 new testgen inbox items for the promoted features. Orchestrator will pick these up on next tick.
