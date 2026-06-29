# Verification Report — Core Ch09: Attack Rolls and Core Check Mechanics

**Inbox item:** `20260407-roadmap-req-core-ch09-combat-checks`
**REQ range:** 2079, 2082, 2091, 2093 (4 REQs)
**Verifier:** qa-dungeoncrawler
**Date:** 2026-04-07
**Verdict:** PARTIAL BLOCK (3 PASS, 1 PARTIAL)

---

## Summary

| Section | REQs | PASS | PARTIAL | BLOCK |
|---|---|---|---|---|
| Core Check Mechanics | 2079, 2082 | 2 | 0 | 0 |
| Attack Rolls | 2091, 2093 | 1 | 1 | 0 |
| **TOTAL** | **4** | **3** | **1** | **0** |

---

## Section: Core Check Mechanics

### REQ 2079 — Bonus types (circumstance, item, status): only highest of same type applies
**Verdict: PASS**

`BonusResolver` (BonusResolver.php) fully implements this:
- `const TYPED_BONUS_TYPES = ['circumstance', 'item', 'status']` (line 28)
- `BonusResolver::resolve(array $bonuses)` (line 45): for each typed bonus entry, keeps only the highest value of that type; untyped/unknown entries all sum.
- REQ 2079 explicitly cited in class docblock.

Consumed by:
- `CombatCalculator::calculateAttackRoll()` line 125: `BonusResolver::resolve($other_raw)` for attack bonuses
- `CombatCalculator::calculateSpellSaveDC()` line 148: same resolver for caster item bonuses
- `Calculator.php` lines 62, 140–141, 411, 524–525: general check/save/skill resolution

### REQ 2082 — Penalty types: same type → worst applies; different types + untyped → all stack
**Verdict: PASS**

`BonusResolver::resolvePenalties(array $penalties)` (BonusResolver.php line 86):
- Same typed penalty: keeps only the most negative value per type (`$value < $by_type[$type]`)
- Untyped and cross-type penalties: all summed
- Normalises values to negative (both positive and negative inputs handled)
- REQ 2082 explicitly cited in class docblock

Consumed by `Calculator.php` lines 140–141 and 524–525 (check resolution).

---

## Section: Attack Rolls

### REQ 2091 — Attacks outside own turn (AoO) do NOT incur MAP and do NOT increase it
**Verdict: PASS**

`CombatEngine::resolveAttack()` (lines 618–624):
```php
$skip_map = !empty($weapon['skip_map']);
if ($skip_map) {
    $attacks_this_turn = (int) ($attacker['attacks_this_turn'] ?? 0); // no increment
    $map_penalty = 0;
}
else {
    $attacks_this_turn = (int) ($attacker['attacks_this_turn'] ?? 0) + 1;
    $map_penalty = $this->combatCalculator->calculateMultipleAttackPenalty(...);
}
```

`updateParticipant()` at line 820 stores the un-incremented `attacks_this_turn` when `skip_map=TRUE`.

`EncounterPhaseHandler` AoO handler (lines 1235–1241):
- Sets `$aoo_weapon['skip_map_count'] = TRUE` and `skip_map => TRUE`
- After AoO resolves, decrements `attacks_this_turn` by 1 to compensate for any pre-increment: `max(0, attacks_this_turn - 1)`
- Comment: "REQ 2230: AoO does NOT count toward or apply MAP; pass skip_map flag."

**Fully compliant.** AoO neither reads a MAP penalty nor increments the attack counter.

### REQ 2093 — Maximum effective range = 6× the range increment
**Verdict: PARTIAL**

`RulesEngine.php` (lines 420–437) implements range validation:
- Checks `distance > weapon_range` → returns `is_valid: FALSE` (absolute max range cap)
- Applies `−2` penalty per range increment beyond the first for ranged weapons

**Gap:** The 6× cap is not programmatically derived from `range_increment`. The code relies on `weapon['range']` as the hard max range. If a weapon has `range_increment` set separately from `range`, there is no guard enforcing `weapon_range ≤ 6 × range_increment`. A misconfigured weapon with `range_increment=10` and `range=120` (12 increments) would not be rejected.

**Risk level:** LOW — the weapon data in `EquipmentCatalogService` appears to set fixed `range` values directly (not derived from increment). In practice the cap is effectively honored through data, but not through code enforcement.

**Suggested fix:** In `RulesEngine.php`, after calculating `$base_range = (int) ($weapon['range_increment'] ?? $weapon_range)`, add:
```php
$max_effective_range = $base_range * 6;
if ($distance > $max_effective_range) {
    return ['is_valid' => FALSE, 'reason' => "Target exceeds maximum effective range ({$max_effective_range} ft).", ...];
}
```

---

## PASS/PARTIAL/BLOCK Summary by REQ

| REQ | Section | Verdict | Service / Method |
|---|---|---|---|
| 2079 | Core Check Mechanics | **PASS** | BonusResolver::resolve() — typed bonuses; highest wins |
| 2082 | Core Check Mechanics | **PASS** | BonusResolver::resolvePenalties() — typed penalties; worst wins; cross-type stacks |
| 2091 | Attack Rolls | **PASS** | CombatEngine::resolveAttack() skip_map + EncounterPhaseHandler AoO handler |
| 2093 | Attack Rolls | **PARTIAL** | RulesEngine range validation present; 6× derivation from range_increment not enforced |

---

## Suggested Fix (not a feature — a hardening patch)

**Gap ID:** GAP-2093 (LOW)
**File:** `RulesEngine.php` line ~432
**Fix:** After computing `$base_range`, add a `$max_effective_range = $base_range * 6` guard before accepting distance.
**Owner:** dev-dungeoncrawler (small change, single file, owned scope)

No new feature pipeline item required — this is a hardening fix within `dc-cr-encounter-rules`.

---

## Codebase Evidence

| File | Relevant Lines | REQs |
|---|---|---|
| `BonusResolver.php` | 9–18 (docblock), 28 (TYPED_BONUS_TYPES), 45–71 (resolve), 86–120 (resolvePenalties) | 2079, 2082 |
| `CombatCalculator.php` | 125, 148 (BonusResolver::resolve calls) | 2079 |
| `Calculator.php` | 62, 140–141, 411, 524–525 (BonusResolver calls) | 2079, 2082 |
| `CombatEngine.php` | 618–624 (skip_map logic), 820 (updateParticipant with non-incremented count) | 2091 |
| `EncounterPhaseHandler.php` | 1235–1241 (AoO handler: skip_map + decrement guard) | 2091 |
| `RulesEngine.php` | 420–437 (range validation + increment penalty; no 6× cap) | 2093 |
