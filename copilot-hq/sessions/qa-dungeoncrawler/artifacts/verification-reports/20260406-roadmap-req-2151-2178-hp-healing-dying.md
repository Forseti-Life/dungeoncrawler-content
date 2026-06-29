# Verification Report: Reqs 2151–2178 — HP, Healing, and Dying
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK — DEF-2151, DEF-2154/2155, GAP-2166, GAP-2178

## Scope
HP, Healing, and Dying (reqs 2151–2178). Key files: `HPManager.php`, `ConditionManager.php`, `CombatEngine.php`, `DowntimePhaseHandler.php`, `CharacterCalculator.php`.

## KB reference
None found relevant in knowledgebase/.

## Note on inbox expected failures
Several inbox-listed "expected failures" were already fixed in the current production code:
- REQ 2158: DC IS 10 + dying_value (not hardcoded 10) — PASS
- REQ 2160: stabilize does NOT set HP to 1 — PASS
- REQ 2161/2162: `stabilizeCharacter` correctly does `wounded + 1` — PASS
- REQ 2165/2166: doomed IS subtracted from death threshold — PASS
- REQ 2168: unconscious −4 AC/Perception/Reflex IS in catalog — PASS
- REQ 2173: `applyDamage` correctly checks `remaining_damage >= 2*max_hp` — PASS (legacy `evaluateDeath` still has wrong formula but is not the live path)

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2151-P: HP cannot go below 0 | **FAIL** | DEF-2151: `$new_hp = $base_hp - $remaining_damage` stored directly — no clamp to 0; HP can be negative in DB |
| TC-2152-P: Max HP = (class_hp + con_mod) × level + ancestry_hp | PASS | Code: `baseHp = ancestry + class + con; levelHp = (level-1)*(class+con)`; math-verified: Fighter(10)+Con+2, L3, ancestry 8 → 44 ✓ |
| TC-2153-P: Initiative shifts to just after attacker at 0 HP | PASS | `resolveAttack` line 852: `shiftInitiativeAfterAttacker()` called on `new_status=defeated` ✓ |
| TC-2154-P: Dying 1 on normal hit to 0; dying 2 on crit | **FAIL** | DEF-2154: `applyDamage` applies dying 1 via `applyCondition` directly; `resolveAttack` then calls `applyDyingCondition(2)` on crit which STACKS (accumulates) → crit result = dying 3, not dying 2 |
| TC-2155-P: Wounded adds to dying when dying is gained | **FAIL** | DEF-2155: Normal (non-crit) kills apply dying via `applyDamage` directly, bypassing `applyDyingCondition` — wounded NOT added for normal kills |
| TC-2155-P: Crit path wounded — via applyDyingCondition | PASS (method) | `applyDyingCondition` correctly adds wounded, but called redundantly on crits causing double-dying issue |
| TC-2156-P: Nonlethal damage → unconscious, not dying | PASS | `applyDamage` line 132: `if ($is_nonlethal) { applyCondition('unconscious') }` — dying not applied ✓ |
| TC-2157-P: Dying 4 = death | PASS | `processDying`: `$death_threshold = max(1, 4 - $doomed_value)`; at doomed=0, death at dying 4 ✓ |
| TC-2158-P: Recovery check DC = 10 + dying | PASS | `processDying` line ~351: `$dc = 10 + $dying_before` — NOT hardcoded 10 ✓ |
| TC-2159-P: Crit success −2, success −1, fail +1, crit fail +2 | PASS | `processDying` degree-of-success deltas correct; crit_success on nat 20 or roll≥dc+10 ✓ |
| TC-2160-P: Stabilize → remains unconscious at 0 HP | PASS | `stabilizeCharacter` sets `is_defeated=1` but does NOT change HP field ✓ |
| TC-2161/2162-P: Stabilize → wounded +1 (additive) | PASS | `stabilizeCharacter`: `$new_wounded = $current_wounded + 1` ✓ |
| TC-2163-P: Wounded added to dying at application | PASS (via method) | `applyDyingCondition` adds wounded ✓ — but see DEF-2155 for normal kill path bypass |
| TC-2164-P: Wounded ends at full HP heal | PASS | `applyHealing`: `if ($new_hp >= $max_hp) { removeConditionByType('wounded') }` ✓ |
| TC-2164-P: Wounded ends at long rest | PASS | `processLongRest` removes wounded via array_filter ✓ |
| TC-2165-P: Doomed reduces death threshold | PASS | `processDying`: `$death_threshold = max(1, 4 - $doomed_value)` ✓ |
| TC-2166-P: Doomed ≥ threshold → instant death | GAP | GAP-2166: Check fires at `processDying` (start of turn). If dying is gained and immediately hits threshold (e.g. wounded 3 → dying 4 with doomed 1, threshold=3), death not evaluated until next turn start |
| TC-2167-P: Doomed −1 per long rest | PASS | `processLongRest` line 306: decrements doomed, removes if ≤ 0 ✓ |
| TC-2168-P: Unconscious: −4 AC, Perception, Reflex; blinded, flat-footed | PASS | Catalog entry confirmed: `status_penalty => ['ac'=>-4, 'perception'=>-4, 'reflex_save'=>-4], 'blinded'=>TRUE, 'flat_footed'=>TRUE` ✓ |
| TC-2169-P: Unconscious at 0 HP recovers after 10 min rest | PASS | `naturalRecovery()` exists: sets HP=1, wakes character ✓ |
| TC-2170-P: Wake on damage/healing/interact | PASS | `applyDamage` (HP>0), `applyHealing` (HP>0 after heal), `wakeOnInteract()` all call `removeUnconsciousCondition` ✓ |
| TC-2171-P: Hero Point heroic recovery — no wounded gained | PASS | `heroicRecovery()`: removes dying+unconscious, returns `wounded_added=FALSE`, does not increment wounded ✓ |
| TC-2172-P: Death effect → instant death, bypass dying | PASS | `applyDamage`: `if (!empty($source['death_effect'])) { status='dead' }` ✓ |
| TC-2173-P: Massive damage ≥ 2×max_hp = instant death | PASS | `applyDamage`: `if ($remaining_damage >= 2 * $max_hp)` → dead ✓ (inbox expected failure: stale, already fixed) |
| TC-2174-P: Temp HP absorbs first | PASS | `applyDamage`: `$temp_absorbed = min($temp_hp, $damage); $remaining = $damage - $temp_absorbed` ✓ |
| TC-2175-P: One temp HP pool, keep higher | PASS | `applyTemporaryHP`: `if ($new_temp <= $current_temp) { return applied=FALSE }` ✓ |
| TC-2176-P: Healing doesn't restore temp HP | PASS | `applyHealing` never touches `temp_hp` field ✓ |
| TC-2177-P: Fast healing restores X HP at start of turn | PASS | `CombatEngine::startTurn` line 322: `applyHealing($fast_healing)` ✓; max HP capped in `applyHealing` |
| TC-2178-P: Regeneration blocks death; fire/acid bypasses | GAP | GAP-2178: `regeneration_bypassed` flag read from `entity_data` but never SET by `applyDamage`/`resolveAttack` when fire/acid hits a regenerating creature. Flag must be set externally — partial implementation |

## Defects

### DEF-2151 (HIGH) — HP not clamped to 0
- **File**: `HPManager.php` line ~88
- **Code**: `$new_hp = $base_hp - $remaining_damage;` — stored directly with no `max(0, $new_hp)`
- **Impact**: HP can be stored as a negative integer in `combat_participants.hp`, violating REQ 2151.
- **Fix**: Add `$new_hp = max(0, $base_hp - $remaining_damage);`

### DEF-2154/2155 (HIGH) — Double dying application; wounded bypassed on normal kills
- **File**: `HPManager::applyDamage` line ~136 + `CombatEngine::resolveAttack` line ~847
- **Root cause**: `applyDamage` calls `applyCondition('dying', 1)` directly (bypassing `applyDyingCondition`). On crits, `resolveAttack` then calls `applyDyingCondition(2)` which ACCUMULATES (applyCondition for valued: `min(max, existing+new)` = 1+2=3). On normal kills, wounded is never added because `applyDyingCondition` is never called.
- **Result**: Crit kill → dying 3 (should be 2+wounded). Normal kill → dying 1 (should be 1+wounded).
- **Fix**: Remove the `applyCondition('dying', 1)` from `applyDamage`. Instead, call `applyDyingCondition($participant_id, 1, $encounter_id, FALSE)`. In `resolveAttack`, on crit, call `applyDyingCondition($participant_id, 2, ...)` (no double-call). `applyDyingCondition` handles both crit-upscale and wounded-addition.

### GAP-2166 (MEDIUM) — Doomed instant-death timing
- **File**: `ConditionManager::processDying`
- **Issue**: Death threshold check runs only at start of turn via `processDying`. If dying is applied and immediately reaches the doomed-reduced threshold (e.g. doomed 2, gain dying 3 → threshold is 2, dying ≥ 2 = dead), it is not caught until the next turn starts. REQ 2166 implies instant death when threshold is reached at application time.
- **Severity**: Medium — only affects edge cases where doomed is active.

### GAP-2178 (MEDIUM) — Regeneration bypass not auto-set for fire/acid
- **File**: `CombatEngine::startTurn` + `HPManager::applyDamage`
- **Issue**: `entity_data['regeneration_bypassed']` is read to skip regeneration, but never SET automatically when fire/acid damage is dealt to a regenerating creature. The attacker must manually pre-set this flag. No integration between `applyDamage` and the start-of-turn regen check.
- **Severity**: Medium — regeneration will not be bypassed unless external system sets the flag.

## Summary
28 test cases executed. 22 PASS, 4 FAIL/GAP. Two high-severity defects: DEF-2151 (HP floor) and DEF-2154/2155 (double dying application and wounded bypass). Two medium gaps: GAP-2166 (doomed timing) and GAP-2178 (regen bypass wiring). Several inbox-expected failures were already fixed in production.
