# Grooming Complete: dc-cr-environment-terrain

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:41:27+00:00  
**Feature:** dc-cr-environment-terrain

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-environment-terrain/feature.md` ✓
- `features/dc-cr-environment-terrain/01-acceptance-criteria.md` ✓
- `features/dc-cr-environment-terrain/03-test-plan.md` ✓

## QA summary

31 TCs covering terrain types (bog/ice/snow/sand/rubble/undergrowth/slope/narrow/uneven), environmental damage, temperature, collapse/burial, wind, and underwater. Conditional on dc-cr-conditions for flat-footed/restrained/prone/suffocation TCs. PM note: current speed threshold for swim-against-current (TC-ENV-26) needs clarification.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-environment-terrain/03-test-plan.md
