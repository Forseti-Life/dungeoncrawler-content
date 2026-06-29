# Grooming Complete: dc-cr-hazards

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:44:03+00:00  
**Feature:** dc-cr-hazards

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-hazards/feature.md` ✓
- `features/dc-cr-hazards/01-acceptance-criteria.md` ✓
- `features/dc-cr-hazards/03-test-plan.md` ✓

## QA summary

29 TCs covering hazard detection (min proficiency/auto-roll/Detect Magic), passive/active triggers, simple/complex hazard types, disable rules (2-action/min proficiency/crit fail triggers), stat block fields (AC/Hardness/HP/BT), magical hazard counteract, and XP table. PM notes: hazard XP table values need BA extraction; counteract TCs conditional on dc-cr-spells-ch07.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-hazards/03-test-plan.md
