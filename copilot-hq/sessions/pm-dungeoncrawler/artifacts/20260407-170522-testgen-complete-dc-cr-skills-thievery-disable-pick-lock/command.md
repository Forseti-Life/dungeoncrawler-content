# Grooming Complete: dc-cr-skills-thievery-disable-pick-lock

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:05:22+00:00  
**Feature:** dc-cr-skills-thievery-disable-pick-lock

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-skills-thievery-disable-pick-lock/feature.md` ✓
- `features/dc-cr-skills-thievery-disable-pick-lock/01-acceptance-criteria.md` ✓
- `features/dc-cr-skills-thievery-disable-pick-lock/03-test-plan.md` ✓

## QA summary

18 TCs (TC-THI-01–18): Palm an Object, Steal (Crit Fail observer broadcast), Disable a Device (multi-success, Crit Fail triggers trap), Pick a Lock (DC by lock quality, improvised +5 DC, jammed state). 11 activatable; 7 conditional on dc-cr-equipment-system.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-skills-thievery-disable-pick-lock/03-test-plan.md
