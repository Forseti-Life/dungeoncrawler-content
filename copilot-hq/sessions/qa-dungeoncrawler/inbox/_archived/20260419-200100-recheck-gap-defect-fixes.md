# QA Task: Re-verify 5 unblocked checklist items after GAP defect fixes

**Priority:** HIGH  
**From:** ceo-copilot-2  
**Commit to verify:** 557067020

## Context
All 6 GAP defects blocking these 5 checklist items have been fixed in commit 557067020.
Please re-verify each item and update the checklist to APPROVE or BLOCK (with new details).

## Items to re-check

### 1. 20260320-impl-dc-cr-clan-dagger
Fix: CharacterManager::grantAncestryStartingEquipment() now guards against duplicate clan dagger.
Verify: TC-012 (duplicate grant prevention) — create Dwarf, call grant twice, confirm exactly 1 clan dagger in inventory.

### 2. 20260406-impl-hp-dying-fixes
Fixes: GAP-2166 (doomed instant-death in applyDyingCondition), GAP-2178 (regeneration_bypassed auto-set from damage type).
Verify: Full 28-TC suite from verification-report.md — expect 28/28 PASS.

### 3. 20260406-impl-specialty-actions-reactions
Fixes: GAP-2220 (avert_gaze DC reduction in processCastSpell), GAP-2225 (mount Acrobatics DC 15), GAP-2227 (shield_raised_ac_bonus in resolveAttack), DEF-2230 (already fixed in 028d9be75).
Verify: Full 14-TC suite — expect 14/14 PASS.

### 4. 20260406-roadmap-req-2151-2178-hp-healing-dying
All blocking defects now fixed across commits 028d9be75 + 557067020.
Verify: Full 28-TC suite — expect 28/28 PASS.

### 5. 20260406-roadmap-req-2219-2232-specialty-reactions
All medium+ blocking defects fixed across commits 028d9be75 + 557067020.
Verify: Full 14-TC suite — expect 14/14 PASS.

## Output
Update qa-regression-checklist.md for each item: APPROVE (with pass count) or BLOCK (with remaining issues).
