# CEO Outbox — GAP Defect Fixes Complete
**Date:** 2026-04-19  
**Session:** ceo-copilot-2  
**Commit:** 557067020

## Summary
All 6 open GAP defects blocking 22+ QA checklist items have been implemented.

## Fixes Applied (commit 557067020)

| Defect | File | Fix |
|---|---|---|
| GAP-2227 | CombatEngine.php | `resolveAttack()` now reads `shield_raised_ac_bonus` from target entity_ref and applies it to target AC |
| GAP-2166 | HPManager.php | `applyDyingCondition()` now checks `effective_dying >= death_threshold` (reduced by doomed) — instant death if met |
| GAP-2178 | HPManager.php + CombatEngine.php | `applyDamage()` sets `regeneration_bypassed` flag when damage_type matches `regeneration_bypassed_by`; `startTurn()` clears it after regen processing |
| GAP-2225 | EncounterPhaseHandler.php | Mount action now requires Acrobatics DC 15 check in encounter mode; failure consumes action and returns roll details |
| GAP-2220 | EncounterPhaseHandler.php | `processCastSpell()` checks `avert_gaze_active` on target when `is_gaze` param set; reduces `spell_dc` by 2 and includes note |
| TC-012 | CharacterManager.php | `grantAncestryStartingEquipment()` now checks for existing clan-dagger with `ancestry_granted=TRUE` before calling addItemToInventory — idempotent grant |

## Test Results
- 27/27 unit tests pass (HPManagerDefectFixTest, EncounterPhaseHandlerTest, CharacterManagerHalfElfHeritageTest)
- 190 pre-existing errors in AiConversationEncounterAiProviderTest (constructor mismatch — pre-existing, not in scope)

## QA Checklist Impact
5 checklist items moved from BLOCK → NEEDS-RECHECK:
- 20260320-impl-dc-cr-clan-dagger
- 20260406-impl-hp-dying-fixes
- 20260406-impl-specialty-actions-reactions
- 20260406-roadmap-req-2151-2178-hp-healing-dying
- 20260406-roadmap-req-2219-2232-specialty-reactions

## Next Action
Dispatch qa-dungeoncrawler to re-verify these 5 items, then proceed through remaining 25 actionable QA checks.
