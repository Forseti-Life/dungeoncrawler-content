# Grooming Complete: dc-cr-spells-ch07

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:30:45+00:00  
**Feature:** dc-cr-spells-ch07

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-spells-ch07/feature.md` ✓
- `features/dc-cr-spells-ch07/01-acceptance-criteria.md` ✓
- `features/dc-cr-spells-ch07/03-test-plan.md` ✓

## QA summary

82 TCs (TC-SP-01–82): traditions/schools, spell slots (prepared/spontaneous/signature), heightening, cantrips, focus pool (cap=3/Refocus/daily-prep restore), innate spells (Cha-mod/once-daily block), casting mechanics (actions/components/disruption/metamagic), spell attacks/DCs (MAP applies/weapon-spec absent), area/range/targeting (invalid-target partial-fizzle), durations (round/sustained/dismiss), special types (illusion/counteract/polymorph/summoning), spell stat block data model, spell list content gates (4 traditions), focus spells by class (8 TCs), edge cases, failure modes. 46 immediately activatable; 8 conditional on dc-cr-focus-spells (TC-SP-21–28/66–73); 1 on dc-cr-rituals (TC-SP-57).

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-spells-ch07/03-test-plan.md
