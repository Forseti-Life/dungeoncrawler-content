# Grooming Complete: dc-cr-encounter-creature-xp-table

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:37:58+00:00  
**Feature:** dc-cr-encounter-creature-xp-table

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-encounter-creature-xp-table/feature.md` ✓
- `features/dc-cr-encounter-creature-xp-table/01-acceptance-criteria.md` ✓
- `features/dc-cr-encounter-creature-xp-table/03-test-plan.md` ✓

## QA summary

14 TCs (TC-XPT-01–14): threat tiers (Trivial/Low/Moderate/Severe/Extreme), 4-PC baseline + Character Adjustment, creature XP cost table (9 level-delta rows −4→+4), out-of-range handling (>+4 no entry, <−4 trivial), double-XP catch-up rule, hazard XP table reference gate, party-size 1–3 and 5+ edge cases, multi-creature additive budget, failure modes (>+4 null not error / trivial 0 XP not error). All 14 conditional on dc-cr-xp-award-system.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-encounter-creature-xp-table/03-test-plan.md
