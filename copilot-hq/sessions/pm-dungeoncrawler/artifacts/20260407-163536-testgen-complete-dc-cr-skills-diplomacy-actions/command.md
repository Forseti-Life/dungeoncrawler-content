# Grooming Complete: dc-cr-skills-diplomacy-actions

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T16:35:36+00:00  
**Feature:** dc-cr-skills-diplomacy-actions

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-skills-diplomacy-actions/feature.md` ✓
- `features/dc-cr-skills-diplomacy-actions/01-acceptance-criteria.md` ✓
- `features/dc-cr-skills-diplomacy-actions/03-test-plan.md` ✓

## QA summary

Diplomacy+Intimidation actions 29 TCs (TC-DIP-01 through TC-DIP-29): Gather Information (downtime 2hr, DC tiers, Crit Fail community-awareness flag), Make an Impression (downtime 10min, 4 degrees shifting NPC attitude ±1/±2, clamp at Helpful/Hostile), Request (1-action, Friendly/Helpful gate, unreasonable-request penalty, 4 degrees including compliance+attitude-shift), Coerce (downtime 10min, compliance 1-week/1-month windows, post-coerce immunity 1-week), Demoralize (1-action, language-barrier hard-block, frightened 1/2, 10-min post-attempt immunity), ACL regression. 10 immediately activatable; 19 conditional on dc-cr-npc-system (NPC attitude model) or dc-cr-conditions (frightened/immunity). PM flags: DC tier offsets, NPC system stub availability, Request low-attitude behavior, unreasonableness tier definition, Coerce immunity start timing, language-check module ownership, Demoralize immunity intent, Intimidation grouping confirmation.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-skills-diplomacy-actions/03-test-plan.md
