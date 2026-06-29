# Verification Report: Unit Test — 20260406-impl-hp-dying-fixes (Reqs 2151–2178)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: 85dedb946
- Verdict: BLOCK

## Scope
Targeted unit-test for completed dev item `20260406-impl-hp-dying-fixes`. Reqs 2151–2178 (HP/dying/healing system). Primary files: `HPManager.php`, `ConditionManager.php`, `CombatEngine.php`, `DowntimePhaseHandler.php`.

## Prior report reference
Roadmap verification `20260406-roadmap-req-2151-2178-hp-healing-dying` (QA BLOCK committed `c42dbb089` at 2026-04-06T23:20 UTC) was performed AFTER dev commit `85dedb946` (22:14 UTC). The roadmap QA already tested the post-dev-commit code and found defects. This unit-test confirms those findings remain open with targeted source inspection.

## KB reference
None found directly relevant in `knowledgebase/`.

## Confirmed defect status (verified 2026-04-07)

### DEF-2151 (HIGH) — HP not clamped to 0
- **File:** `HPManager.php` line 81
- **Code:** `$new_hp = $base_hp - $remaining_damage;` — no `max(0, ...)` clamp
- **Still open:** confirmed. Damage can produce negative HP stored to DB.
- **Fix:** `$new_hp = max(0, $base_hp - $remaining_damage);`

### DEF-2154/2155 (HIGH) — Double dying application; wounded bypassed on normal kills
- **File:** `HPManager::applyDamage` line 136
- **Code:** `$this->conditionManager->applyCondition($participant_id, 'dying', 1, ...)` called directly in `applyDamage`
- `applyDyingCondition()` exists at line 279 and correctly handles wounded addition + crit upscale
- **Still open:** Direct `applyCondition` call in `applyDamage` bypasses `applyDyingCondition`. On crits, `resolveAttack` additionally calls `applyDyingCondition(2)` which stacks to dying 3. On normal kills, wounded is never added.
- **Fix:** Replace line 136 `applyCondition('dying', 1)` with `applyDyingCondition($participant_id, 1, $encounter_id, FALSE)`. Remove the crit `applyDyingCondition(2)` call from `resolveAttack` (let the single call in `applyDamage` handle the base case; pass `is_critical=TRUE` for crit path).

### GAP-2166 (MEDIUM) — Doomed instant-death timing
- **File:** `ConditionManager::processDying` line 375
- **Code:** `$death_threshold = max(1, 4 - $doomed_value)` correctly computed
- **Gap:** `processDying()` is called at start-of-turn only. If `applyDyingCondition` applies dying that immediately meets the doomed-reduced threshold (e.g. doomed=2, gain dying=2 → threshold=2, death should be instant), it is not caught until the next `processDying` call.
- **Still open:** No post-applyDyingCondition death check found in `applyDyingCondition()`.

### GAP-2178 (MEDIUM) — Regeneration bypass not auto-set for fire/acid
- **File:** `CombatEngine::startTurn` reads `entity_data['regeneration_bypassed']` (line 326)
- **Gap:** `CombatEngine::resolveAttack` has fire damage path at line 837 (underwater fail case only). No code path in `resolveAttack` or `applyDamage` sets `regeneration_bypassed = TRUE` when fire/acid hits a regenerating creature.
- **Still open:** confirmed. Regeneration will not be bypassed by fire/acid unless manually pre-set externally.

## PASS summary (22/28 unchanged)
2152✓ 2153✓ 2156✓ 2158✓ 2159✓ 2160✓ 2161/2162✓ 2165✓ 2167✓ 2168✓ 2169✓ 2170✓ 2171✓ 2172✓ 2173✓ 2174/2175/2176✓ 2177✓

## Site audit
Not re-run (run 20260407-014054 already clean — 0 errors, 0 permission violations, 0 config drift).

## Verdict: BLOCK
Two high-severity defects (DEF-2151, DEF-2154/2155) affect every combat encounter — negative HP stored in DB and wrong dying track application for all kills. These must be fixed before any kill/death mechanic can be trusted.
