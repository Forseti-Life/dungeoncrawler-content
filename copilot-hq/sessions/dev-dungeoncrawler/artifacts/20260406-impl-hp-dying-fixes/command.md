# Dev Task: Fix HP, Dying, and Healing System (Reqs 2153–2178)

**Type:** dev-impl  
**Section:** Ch9 — Hit Points, Healing, and Dying  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Hit Points, Healing, and Dying"  
**Requirements:** 2153, 2154, 2156, 2158, 2160, 2161, 2162, 2164, 2165, 2166, 2167, 2168, 2169, 2170, 2171, 2172, 2173, 2177, 2178

---

## Summary

Multiple bugs and missing features in the HP/dying/healing system. Fix existing bugs first, then implement missing mechanics.

---

## BUG FIXES (existing code, wrong behavior)

### BUG 1 — REQ 2158: Recovery check DC must be `10 + dying_value`
**File:** `HPManager.php` or `ConditionManager.php` — `processDying()` method  
**Current:** hardcoded `$dc = 10`  
**Fix:** `$dc = 10 + $dying_value;`  
PF2e ref: "The dying condition... you must attempt a flat check to see if you recover. The DC equals 10 + your current dying value."

### BUG 2 — REQ 2160: After recovery, character stays at 0 HP (unconscious)
**File:** `HPManager.php` — `stabilizeCharacter()`  
**Current:** Sets `hit_points.current = 1`  
**Fix:** Leave HP at 0. Character is stabilized and unconscious, NOT healed.  
PF2e ref: "You're no longer dying, but you remain unconscious. Your wounded condition... increases by 1."

### BUG 3 — REQ 2161/2162: Stabilizing increases wounded by 1 (additive)
**File:** `HPManager.php` — `stabilizeCharacter()`  
**Current:** `wounded = dying_value - 1` (completely wrong)  
**Fix:** `wounded = (current_wounded_value ?? 0) + 1`  
PF2e ref: "Each time you lose the dying condition, your wounded value increases by 1."

### BUG 4 — REQ 2165/2166: Doomed reduces dying death threshold
**File:** `ConditionManager.php` — `processDying()`  
**Current:** Hardcoded `if (dying_value >= 4) { die; }`  
**Fix:**
```php
$doomed = $this->getConditionValue($entity, 'doomed') ?? 0;
$death_threshold = 4 - $doomed;
if ($dying_value >= $death_threshold) {
    // character dies
}
```
Also: if doomed alone would reduce threshold to ≤ current dying, kill immediately.  
PF2e ref: "Your doomed value reduces your dying threshold: dying 4 − doomed = death."

### BUG 5 — REQ 2167: Doomed −1 per rest (code comment only, not implemented)
**File:** `DowntimePhaseHandler.php` — `processLongRest()`  
**Current:** Only a comment; no actual doomed decrement code  
**Fix:** Add loop to find doomed condition in entity state and decrement by 1 (remove if reaches 0)

### BUG 6 — REQ 2168: Unconscious −4 status penalty to AC, Perception, Reflex saves
**File:** `ConditionManager.php` — condition catalog (`unconscious` entry, line ~65)  
**Current:** Only `cannot_act: true, flat_footed: true`  
**Fix:**
```php
'unconscious' => [
    'is_valued' => FALSE,
    'effects' => [
        'cannot_act' => TRUE,
        'flat_footed' => TRUE,
        'blinded' => TRUE,
        'status_penalty' => [
            'ac' => -4,
            'perception' => -4,
            'reflex_save' => -4,
        ],
    ],
],
```
Verify `Calculator.calculateAC` and `Calculator.rollSavingThrow` consume `status_penalty` effects from active conditions.

### BUG 7 — REQ 2173: Massive damage check is wrong
**File:** `HPManager.php` — `evaluateDeath()`  
**Current:** `if ($hp <= -1 * $max_hp)` — checks final HP, not damage amount  
**Fix:** Pass damage amount separately:
```php
public function evaluateDeath(int $damage, int $max_hp): bool {
    return $damage >= (2 * $max_hp);
}
```
Call site in `applyDamage()` should pass the raw damage before reductions (or after — clarify per RAW).  
PF2e ref: "If you ever take damage in excess of double your maximum Hit Points from a single blow, you die."

---

## NEW FEATURES (missing systems)

### FEATURE 1 — REQ 2153: Initiative shift at 0 HP
**File:** `CombatEngine.php` — `resolveAttack()` and `applyDamage()` call chain  
When a character drops to 0 HP mid-combat, their initiative should be changed to just after the creature that dropped them, so they act just before the attacker on subsequent rounds.  
PF2e ref: "When you're reduced to 0 Hit Points... you move to just after the turn in the initiative order of the creature that reduced you to 0."

### FEATURE 2 — REQ 2154: Dying 2 on crit
**File:** `HPManager.php` — `applyDyingCondition()`  
Add parameter `bool $is_critical = false`. When true, apply dying 2 (+ wounded) instead of dying 1.  
Call site: `CombatEngine.resolveAttack()` — pass `is_critical` when crit.

### FEATURE 3 — REQ 2156: Nonlethal damage → unconscious, not dying
**File:** `HPManager.php` — `applyDamage()`  
When damage has `nonlethal: true` flag and reduces HP to 0:
- Apply `unconscious` condition
- Do NOT call `applyDyingCondition()`

### FEATURE 4 — REQ 2164: Wounded ends on Treat Wounds or full HP + 10-min rest
**File:** `HPManager.php` — `applyHealing()` and `DowntimePhaseHandler.php`  
- In `applyHealing()`: if new HP = max_hp AND a rest flag is set in context → remove wounded
- `DowntimePhaseHandler.processLongRest()`: restoring to full HP already removes wounded ✅ (verify flag)

### FEATURE 5 — REQ 2169/2170: Unconscious wake conditions
**File:** New or in `HPManager.php` / `CombatEngine.php`  
- REQ 2169: Character unconscious at 0 HP → after 10-min rest, gains 1 HP and wakes (natural recovery)
  - Add `processShortRest()` in `DowntimePhaseHandler` or a time-passage handler
- REQ 2170: Character unconscious with HP > 0 → wakes on:
  - Receiving damage
  - Receiving healing
  - Being shaken/slapped (Interact action)
  - Hearing a loud noise (Perception DC 20)
  - Add checks in `applyDamage()`, `applyHealing()`, and relevant action processors

### FEATURE 6 — REQ 2171: Hero Point heroic recovery
**File:** `HPManager.php` or new `HeroPointManager.php`  
`heroicRecovery(entity)`:
1. Spend 1 Hero Point
2. Remove dying condition entirely
3. Do NOT add wounded
4. Gain HP = Con modifier (if positive)  
PF2e ref: "You can spend a Hero Point to try to recover. You're stable, your dying value becomes 0, and you don't gain the wounded condition from this recovery."

### FEATURE 7 — REQ 2172: Death effects bypass dying track
**File:** `ActionProcessor.php` or `HPManager.php`  
If an action or damage source has `death_effect: true`, dropping to 0 HP → instant death (no dying track).

### FEATURE 8 — REQ 2177/2178: Fast Healing and Regeneration
**File:** `CombatEngine.php` — `startTurn()` or `processTurnStart()`  

**Fast Healing:** At start of entity's turn, restore `fast_healing` HP (up to max HP).

**Regeneration:** Same as fast healing, but entity cannot be permanently killed while at 0 HP UNLESS the bypassing damage type (fire/acid/etc.) dealt the final blow. Track `regeneration_bypassed_by` in entity data.

```php
if ($entity['state']['fast_healing'] ?? 0) {
    $this->hpManager->applyHealing($entity, $entity['state']['fast_healing']);
}
if ($entity['state']['regeneration'] ?? 0) {
    if (!$entity['state']['regeneration_bypassed']) {
        $this->hpManager->applyHealing($entity, $entity['state']['regeneration']);
        // Prevent death processing this turn
    }
}
```

---

## Implementation Order

1. Bug fixes 1–7 (modify existing methods, safest first)
2. Feature 2 (dying 2 on crit — extends existing crit path)
3. Feature 3 (nonlethal flag — extends existing applyDamage)
4. Feature 8 (fast healing/regeneration — self-contained loop addition)
5. Feature 4 (wounded removal — small conditional)
6. Feature 5 (wake conditions — new triggers in existing handlers)
7. Feature 6 (Hero Point recovery — new method)
8. Feature 7 (death effects — new flag check)
9. Feature 1 (initiative shift — complex, touches CombatEngine initiative ordering)

---

## DB Update

After each fix is verified by QA, update:
```sql
UPDATE dc_requirements SET status='implemented' WHERE id IN (...);
```
Covered IDs: 2153, 2154, 2156, 2158, 2160, 2161, 2162, 2164, 2165, 2166, 2167, 2168, 2169, 2170, 2171, 2172, 2173, 2177, 2178
- Agent: dev-dungeoncrawler
- Status: pending
