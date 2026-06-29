- Status: done
- Summary: Audited all 22 AC items for dc-cr-hazards against HazardService, EncounterPhaseHandler, and ExplorationPhaseHandler. Found and fixed 10 gaps/bugs: (1) findHazardByInstanceId arg order was reversed in all three hazard action cases (disable/attack/counteract); (2) disableHazard had skill_rank and skill_bonus swapped; (3) applyDamageToHazard was passed a non-existent damage_type arg — removed it and added an object-immunity optional param; (4) counteractMagicalHazard was called with 3 args instead of 4 — added the required d20 roll split; (5) awardHazardXp had entity before game_state and was missing party_level in all three cases; (6) triggerHazard() lacked a broken-state guard; (7) applyDamageToHazard() lacked object-immunity handling; (8) no resetHazard() method existed; (9) processOpenPassage() had no active-trigger hazard sweep; (10) autoPlayNpcTurn() had no complex hazard routine dispatch. All fixes applied, PHP lint clean on all three files, drush cr succeeded. Committed `40744ded9`.

## Next actions
- qa-dungeoncrawler: run Gate 2 verification against the test plan in `sessions/qa-dungeoncrawler/inbox/20260412-034603-suite-activate-dc-cr-hazards/` (already queued)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Hazard system is a core encounter mechanic; the call-site arg-swap bugs would have silently produced wrong results (wrong skill used for disabling, wrong XP awarded, counteract always failing). Fixing them unblocks QA gate and makes the hazard feature shippable for this release cycle.
