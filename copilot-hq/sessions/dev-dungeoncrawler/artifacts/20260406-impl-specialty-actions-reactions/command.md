# Dev Task: Implement Specialty Basic Actions and Reactions in Encounters (Reqs 2219–2232)

**Type:** dev-impl  
**Section:** Ch9 — Specialty Basic Actions + Reactions in Encounters  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9  
**Requirements:** 2219–2232

---

## Summary

None of these 14 requirements are implemented. This task covers:
- **Specialty movement actions:** Burrow, Fly, Mount
- **Defensive reactions:** Arrest a Fall, Grab an Edge, Shield Block, Raise a Shield
- **Awareness actions:** Avert Gaze, Point Out
- **Class reactions:** Attack of Opportunity (AoO)

---

## Implementation Plan

### Add to `getLegalIntents()` in EncounterPhaseHandler:
```php
'burrow', 'fly', 'mount', 'dismount',
'raise_shield', 'avert_gaze', 'point_out',
'arrest_fall', 'grab_edge', 'shield_block',
'attack_of_opportunity',
```

---

### Fly and Burrow (REQ 2221–2223)

**Burrow:**
- Require `burrow_speed > 0` on entity stats
- Move entity to target hex, tag `underground: true`
- No tunnel unless `creates_tunnel: true` in ability

**Fly (REQ 2222–2223):**
- Require `fly_speed > 0` on entity stats
- Tag entity as `airborne: true`
- Upward movement: each square up counts as 2 squares of movement (difficult terrain cost)
- Fly 0 ft: `distance: 0` → hover (stays airborne, costs 1 action)
- End of turn: if `airborne: true` and no Fly action used this turn → apply fall
  - In `endTurn()`: check `fly_used_this_turn`; if `airborne` and not set → trigger fall damage

PF2e ref: "Fly 0 feet to hover in place."

---

### Mount/Dismount (REQ 2225)

- Mount: require adjacent willing creature with size ≥ actor's size + 1
- Set `actor.mounted_on = mount_id` in entity state
- Dismount: clear `mounted_on`, place actor in adjacent hex
- Rider shares MAP with mount (tracked at ride level — see REQ 2258)
- Only one rider unless ability specifies

---

### Raise a Shield (REQ 2227)

- Require shield in hand (`held_items` contains shield-type item)
- Set `shield_raised: true` in entity state; clear at start of next turn
- When shield raised, add shield's `ac_bonus` to AC calculation in `Calculator.calculateAC`

---

### Shield Block (REQ 2231–2232) — Reaction

- Require `shield_raised: true` on entity
- Trigger: taking damage
- Reduce incoming damage by shield's `hardness`
- Remaining damage (after hardness) splits equally: half to entity HP, half to shield HP
- Shield HP tracked; when shield HP ≤ 0 → broken (cannot Shield Block or Raise a Shield)

```php
$hardness = $shield['hardness'] ?? 0;
$reduced_damage = max(0, $incoming_damage - $hardness);
$shield_takes = (int) ($reduced_damage / 2);
$entity_takes = $reduced_damage - $shield_takes;
$this->hpManager->applyDamage($entity_id, $entity_takes, ...);
$this->reduceShieldHp($shield, $shield_takes);
```

---

### Avert Gaze (REQ 2220)

- 1 action
- Set `avert_gaze_active: true` on entity state (expires at start of next turn)
- In `Calculator.rollSavingThrow` and attack defense resolution: if `avert_gaze_active` and effect has `gaze: true` trait → add +2 circumstance bonus

---

### Point Out (REQ 2226)

- 1 action
- Require target is `undetected` to actor
- For each ally in perception range of actor: set target detection state from `undetected` → `hidden` for that ally
- Actor does not need to see target (uses general position awareness)

---

### Arrest a Fall (REQ 2219) — Reaction

- Require `fly_speed > 0` on entity
- Trigger: beginning a fall while airborne
- Roll Acrobatics vs DC 15
- Crit success/success: land safely, 0 fall damage
- Failure: normal fall damage
- Crit failure: 10 bludgeoning per 20 ft fallen (so far)

---

### Grab an Edge (REQ 2224) — Reaction

- Trigger: falling past a surface with a handhold (GM flag on terrain)
- Roll Reflex save DC 15
- Success: stop falling, hanging on edge (clinging state)
- Failure: continue falling

---

### Attack of Opportunity (REQ 2228–2230) — Fighter Class Reaction

- Only available if entity has `class_feature: attack_of_opportunity` (Fighter class feature)
- Trigger: enemy within reach uses a move action (Stride, Step, etc.) or manipulate action (casting, Interact)
- Resolve as a melee Strike BUT:
  - Does NOT count toward MAP (`attacks_this_turn` NOT incremented)
  - Does NOT apply MAP to the AoO roll
- If AoO is a critical hit AND trigger was a manipulate action → apply `disrupted: true` to the triggering action

**Key files:**
- `EncounterPhaseHandler.php` → add reaction trigger check at end of Stride/Interact/CastSpell resolution
- `CombatEngine.resolveAttack()` → add `skip_map: true` option for AoO

---

## Priority Order

1. Raise a Shield + Shield Block (defensive baseline — high player use)
2. Fly + Burrow (mobility for creatures)
3. Avert Gaze + Point Out (detection/awareness)
4. Attack of Opportunity (fighter class feature)
5. Mount/Dismount (builds on movement system)
6. Arrest a Fall + Grab an Edge (situational reactions)

---

## DB Update

```sql
UPDATE dc_requirements SET status='implemented' WHERE id IN (2219,2220,2221,2222,2223,2224,2225,2226,2227,2228,2229,2230,2231,2232);
```
- Agent: dev-dungeoncrawler
- Status: pending
