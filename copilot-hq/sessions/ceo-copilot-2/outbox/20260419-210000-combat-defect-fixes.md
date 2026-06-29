# CEO Session Outbox — Combat Defect Fixes
**Date:** 2026-04-19  
**Session:** ceo-copilot-2  
**Commit:** 028d9be75  

## Work Completed This Session

### DEF-2151 — HP stored as negative (FIXED)
- **File:** `HPManager.php` line 82
- **Fix:** `$new_hp = max(0, $base_hp - $remaining_damage);`
- HP was stored negative in DB when damage exceeded current HP. Broke downstream dying/healing checks.

### DEF-2154 — Crit kills applied dying 1 not dying 2 (FIXED)
- **File:** `HPManager.php` — added `bool $is_critical = FALSE` param to `applyDamage()`
- **Fix:** Routes through `applyDyingCondition($pid, 1, $enc, $is_critical)` which promotes to dying 2 on crits
- PF2E rule: crit hit → dying 2 before wounded adjustment
- **CombatEngine.php:** Updated to pass `$degree === 'critical_success'` to `applyDamage()` and removed redundant workaround
- **ActionProcessor.php:** Updated strike path similarly

### DEF-2155 — Normal kills bypassed wounded adjustment (FIXED)
- **File:** `HPManager.php` line 139
- **Fix:** Replaced direct `applyCondition('dying', 1)` with `applyDyingCondition($pid, 1, $enc, $is_critical)`
- Old code ignored wounded value entirely. New code: dying = 1 + wounded (or 2 + wounded for crits).

### DEF-2230 — AoO decremented attacks_this_turn (FIXED)
- **File:** `EncounterPhaseHandler.php` AoO case
- **Fix:** Removed the decrement that contradicted its own comment "Do NOT decrement attacks_this_turn"
- Fighter MAP counter was corrupted (-1 per AoO), causing wrong MAP penalties on subsequent turn actions

## Tests Added
- `HPManagerDefectFixTest.php` — 9 tests / 14 assertions, all pass
  - TC-DEF-2151-A/B/C (HP clamping: overflow, exact zero, temp HP absorption)
  - TC-DEF-2155-A/B/C (normal kill + wounded routing)
  - TC-DEF-2154-A/B (crit dying 2 base, crit + wounded 1)
  - TC-REQ-2156 (nonlethal hit → unconscious, not dying)

## Feature Status Updates
- dc-cr-half-elf-heritage → **done**
- dc-cr-spells-ch07 → **done**
- dc-cr-gnome-heritage-chameleon → **done**
- dc-cr-skills-survival-track-direction → **done**
- dc-cr-snares → **done**
- dc-b3-bestiary3 → **done**

## Remaining Open Defects (not this session)
- GAP-2166: doomed instant-death not checked at applyDyingCondition call site
- GAP-2178: regeneration_bypassed not auto-set for fire/acid damage
- GAP-2220: avert_gaze +2 circumstance bonus never applied in CombatEngine
- GAP-2227: raise_shield AC bonus in entity_ref but CombatEngine reads flat ac column
- GAP-2225: Mount size (>=1 larger) and willing checks

## QA Checklist
- Added: `20260419-fix-def-2151-2154-2155-2230` checklist entry
- Open items: 48 total (47 + 1 new)

## Push
- Commit 028d9be75 → main (`e584bdf99..028d9be75`)
