# Grooming Complete: dc-cr-treasure-by-level

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:48:52+00:00  
**Feature:** dc-cr-treasure-by-level

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-treasure-by-level/feature.md` ✓
- `features/dc-cr-treasure-by-level/01-acceptance-criteria.md` ✓
- `features/dc-cr-treasure-by-level/03-test-plan.md` ✓

## QA summary

13 TCs covering treasure-by-level table (4-PC baseline, currency composition, party size adjustments), selling rules (standard=half price, gems/art/materials=full price, downtime restriction soft-flag), starting wealth by level. PM notes: 3 table value sets need BA extraction (treasure/per-PC-adj/starting-wealth); sell-phase enforcement needs PM decision (hard-block vs soft-flag). Conditional on dc-cr-economy.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-treasure-by-level/03-test-plan.md
