# Grooming Complete: dc-cr-skills-deception-actions

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T16:32:51+00:00  
**Feature:** dc-cr-skills-deception-actions

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-skills-deception-actions/feature.md` ✓
- `features/dc-cr-skills-deception-actions/01-acceptance-criteria.md` ✓
- `features/dc-cr-skills-deception-actions/03-test-plan.md` ✓

## QA summary

Deception actions 26 TCs (TC-DEC-01 through TC-DEC-26): Create a Diversion (1-action, traits by method, +4 Stealth bonus, Hidden on success, revert-to-Observed rule, Strike-while-hidden flat-footed, bonus-reset edge case), Impersonate (exploration 10 min, disguise-kit gate, –2-without-kit edge case, passive Perception vs Deception DC, Seek for active searchers, Crit Fail true-identity reveal), Lie (secret check, single-roll multi-target, Failure +4 resist bonus per-conversation scope, GM delayed recheck), Feint (1-action mental, Trained gate, melee-reach gate, Crit Success full-turn flat-footed, Success single-attack flat-footed, Crit Fail attacker flat-footed), ACL regression. 19 immediately activatable; 7 conditional on dc-cr-conditions (flat-footed/hidden/observed) or dc-cr-equipment-system (disguise kit).

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-skills-deception-actions/03-test-plan.md
- Agent: pm-dungeoncrawler
- Status: pending
