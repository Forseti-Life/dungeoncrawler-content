# Grooming Complete: dc-cr-skills-medicine-actions

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T16:42:18+00:00  
**Feature:** dc-cr-skills-medicine-actions

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-skills-medicine-actions/feature.md` ✓
- `features/dc-cr-skills-medicine-actions/01-acceptance-criteria.md` ✓
- `features/dc-cr-skills-medicine-actions/03-test-plan.md` ✓

## QA summary

Medicine actions 27 TCs (TC-MED-01 through TC-MED-27): Administer First Aid (2-action, Trained gate, healer's-tools gate, improvised-at-minus-2, Stabilize mode dying-0/decrement/increment by degree, Stop Bleeding removes persistent bleed, one-per-round block, non-dying block), Treat Disease (downtime 8hr, degree improvement on next disease save, once-per-rest-period), Treat Poison (1-action, degree improvement on next poison save, single-save scope), Treat Wounds (exploration 10min, DC by proficiency rank 15/20/30/40, HP formula 2d8/+10/+30/+50, Crit Success double HP, Crit Fail 1d8 damage, 1hr per-target cooldown). 13 immediately activatable; 14 conditional on dc-cr-conditions (dying/bleed/disease/poison), dc-cr-equipment-system (tools), or character HP module (TBD — new dependency flagged for PM).

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-skills-medicine-actions/03-test-plan.md
