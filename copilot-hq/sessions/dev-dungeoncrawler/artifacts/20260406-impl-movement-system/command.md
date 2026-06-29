# Dev Task: Implement Movement System (Reqs 2233–2266)

**Type:** dev-impl  
**Section:** Ch9 — Movement in Encounters  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Movement in Encounters"  
**Requirements:** 2233–2266

---

## Summary

This is a large foundational system. `processStride()` currently only teleports entities between hexes with no rule enforcement. This task adds full PF2e movement rules.

---

## System 1: Movement Validation Service (new: `MovementResolverService.php`)

Create a dedicated service to validate and process all movement.

### Core method: `validateMovement(entity, from_hex, to_hex, movement_type, game_state)`

```php
public function validateMovement(array $entity, array $from, array $to, string $type, array $game_state): array {
    // 1. Get entity's speed for movement type (land, fly, burrow, climb, swim).
    // 2. Calculate path distance (hex grid) with diagonal alternation tracking.
    // 3. Apply terrain costs (difficult = +5/square, greater_difficult = +10/square).
    // 4. Validate final position (cannot end in another creature's space unless Tiny or 3+ size diff).
    // 5. Return: valid, distance_cost, errors.
}
```

---

## System 2: Diagonal Movement (REQ 2237)

Implement hex-grid diagonal cost using axial coordinates:
- Track `diagonal_count` in `game_state['turn']` across the whole turn
- Odd diagonals = 5 ft, even diagonals = 10 ft
- Reset at `startTurn()`

---

## System 3: Size and Space System (REQ 2238–2242)

Add `SizeResolver` (can be in `RulesEngine.php`):

```php
const SIZE_SPACES = [
    'tiny' => 2.5, 'small' => 5, 'medium' => 5,
    'large' => 10, 'huge' => 15, 'gargantuan' => 20
];
const SIZE_REACH = ['tiny' => 0, 'small' => 5, 'medium' => 5, 'large' => 10, 'huge' => 15, 'gargantuan' => 20];
```

- Moving through: `size_diff >= 3` → can always move through
- Tiny → can end in larger creature's space
- Cannot end in same space unless above exceptions apply

---

## System 4: Terrain Cost Application (REQ 2249–2252)

In `MovementResolverService.validateMovement()`:

```php
$hex_data = $this->mapService->getHexData($to_hex);
$terrain_extra = 0;
if ($hex_data['difficult_terrain']) { $terrain_extra += 5; }
if ($hex_data['greater_difficult_terrain']) { $terrain_extra += 10; }
$movement_cost += 5 + $terrain_extra; // base 5 + terrain overhead
```

Step action: check destination for `difficult_terrain` → reject if true.

---

## System 5: Flanking Detection (REQ 2253–2254)

In `RulesEngine.php` — new method `isFlanking(attacker_id, target_id, game_state)`:

```php
// Find all allies of attacker with melee reach to target.
// Check if any ally is on the "opposite side" of target.
// Opposite: hex position such that target is between attacker and ally (axial angle diff ≥ 150°).
// Both must be active (not unconscious/dead).
```

Apply flat-footed (−2 AC) to target for that attacker's melee strikes if flanking.

---

## System 6: Cover Calculation (REQ 2255–2257)

New method in `RulesEngine.php`: `calculateCover(attacker_hex, defender_hex, dungeon_data)`:

- Draw line from attacker center to defender center
- Check if line passes through terrain hex → standard cover (+2)
- Check if line passes through creature hex → lesser cover (+1)
- Apply per-attacker-defender pair; not shared
- `Take Cover` action upgrades: no cover → standard, standard → greater

In `Calculator.calculateAC()`: apply cover AC bonus based on attacker position.

---

## System 7: Fall Damage (REQ 2243–2246)

In `HPManager.php` — new method `applyFallDamage(entity_id, fall_distance_ft, surface_type)`:

```php
$effective_distance = $fall_distance;
if ($surface_type === 'water' || $surface_type === 'snow') {
    $surface_depth = $surface['depth_ft'] ?? 0;
    $effective_distance = max(0, $fall_distance - min(30, $surface_depth));
}
$damage = (int) ($effective_distance / 2);
$damage = min($damage, 750); // max 1500 ft
$this->applyDamage($entity_id, $damage, 'bludgeoning', ...);
// Apply prone condition.
$this->conditionManager->addCondition($entity_id, 'prone');
```

---

## System 8: Aquatic Combat (REQ 2262–2266)

Track `underwater: bool` on entity state (set when entering water hexes).

In `Calculator.calculateAC()`: if underwater and no swim_speed → add flat-footed.
In `CombatEngine.resolveAttack()`: 
- If underwater: apply −2 to slashing/bludgeoning melee
- Ranged bludgeoning/slashing: auto-miss
- Ranged piercing: halve range increments
- Fire trait actions: auto-fail underwater

**Held Breath (REQ 2265–2266):**
```
Entity state: {
    'holding_breath': bool,
    'air_rounds_remaining': int,  // 5 + Con mod
    'suffocation_round': int      // increments DC and damage
}
```
At start of each turn (startTurn): decrement `air_rounds_remaining`.  
At end of turn (endTurn): if `air_rounds_remaining <= 0` → Fort save DC = 20 + `suffocation_round`.  
Fail: `1d10 * suffocation_round` damage; crit fail: death.

---

## System 9: Mounted Combat (REQ 2258–2261)

When mounted (`entity.mounted_on = mount_id`):
- Combined MAP: rider's `attacks_this_turn` + mount's `attacks_this_turn` merged
- Mount acts on rider's turn; mount actions require `command_animal` action (or Ride feat)
- Rider: −2 circumstance to Reflex saves (add to condition effects)
- Dismount: 1 action, move to adjacent hex

---

## Forced Movement (REQ 2247–2248)

Add `forced: true` flag to movement calls. In `MovementResolverService`:
- Forced movement does NOT trigger AoO or move-triggered reactions
- Forced movement stops at impassable hexes (does not push through walls)

---

## Priority Order

1. Movement validation + distance check in `processStride()` (foundational)
2. Difficult terrain cost (high frequency)
3. Size system (needed for space/reach)
4. Flanking detection (combat accuracy)
5. Cover calculation (combat accuracy)
6. Fall damage (safety rule)
7. Mounted combat
8. Diagonal tracking
9. Aquatic combat + breath

---

## DB Update

```sql
UPDATE dc_requirements SET status='implemented' WHERE id BETWEEN 2233 AND 2266;
```
- Agent: dev-dungeoncrawler
- Status: pending
