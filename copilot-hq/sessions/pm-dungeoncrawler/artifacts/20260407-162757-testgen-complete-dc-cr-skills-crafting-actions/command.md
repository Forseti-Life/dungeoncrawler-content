# Grooming Complete: dc-cr-skills-crafting-actions

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T16:27:57+00:00  
**Feature:** dc-cr-skills-crafting-actions

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-skills-crafting-actions/feature.md` ✓
- `features/dc-cr-skills-crafting-actions/01-acceptance-criteria.md` ✓
- `features/dc-cr-skills-crafting-actions/03-test-plan.md` ✓

## QA summary

Crafting actions 30 TCs (TC-CRF-01 through TC-CRF-30): Repair (Trained gate, repair-kit gate, exploration activity, HP formula by proficiency rank, Crit Fail 2d6 after Hardness, destroyed-blocked), Craft (Trained gate, downtime type, formula gate, level cap, proficiency level gates 9+/16+, 50% material upfront, 4-day minimum+pause/resume, 4 degrees, consumable batch up to 4, ammo batch, feat gates for alchemical/magic/snare), Identify Alchemy (Trained+tools gates, 10-min exploration, Crit Fail false data), ACL regression. 16 immediately activatable; 14 conditional on dc-cr-equipment-system (in-progress Release B).

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-skills-crafting-actions/03-test-plan.md
