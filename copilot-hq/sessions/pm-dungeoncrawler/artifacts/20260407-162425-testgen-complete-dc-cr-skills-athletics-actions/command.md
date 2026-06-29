# Grooming Complete: dc-cr-skills-athletics-actions

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T16:24:25+00:00  
**Feature:** dc-cr-skills-athletics-actions

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-skills-athletics-actions/feature.md` ✓
- `features/dc-cr-skills-athletics-actions/01-acceptance-criteria.md` ✓
- `features/dc-cr-skills-athletics-actions/03-test-plan.md` ✓

## QA summary

Athletics actions 53 TCs (TC-ATH-01 through TC-ATH-53): Escape extension, Climb (flat-footed/distance/crit-fail), Force Open (MAP/crowbar penalty/4 degrees), Grapple (free-hand gate/size limit/4 degrees/condition duration), High Jump (auto-fail guard/4 degrees), Long Jump (direction/distance-cap/DC), Shove (forced-movement no-reactions/push distances), Swim (calm-water/breath/sink-rule), Trip (3 degrees), Disarm (Trained gate/4 degrees), Falling Damage (formula/soft-surface/Grab-an-Edge), ACL regression. 37 immediately activatable; 14 conditional on dc-cr-conditions (in-progress Release B).

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-skills-athletics-actions/03-test-plan.md
