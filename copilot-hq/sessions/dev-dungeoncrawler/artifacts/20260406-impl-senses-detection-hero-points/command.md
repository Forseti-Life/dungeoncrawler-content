# Dev Task: Implement Senses, Detection, and Hero Point Mechanics (Reqs 2267–2282, 2286, 2288)

**Type:** dev-impl  
**Section:** Ch9 — Senses and Detection, Hero Points, Encounter Mode  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9  
**Requirements:** 2267–2282, 2286, 2288

---

## Summary

This task builds two separate systems:
1. **Senses and Detection** — full state machine for perception, light levels, and detection states
2. **Hero Point Mechanics** — mechanically enforced reroll and heroic recovery spends

---

## System 1: Detection State Machine (REQ 2267–2278)

### Entity Detection States (REQ 2276)

Replace `hidden: bool` flag with a detection state per perceiver:

```json
"detection_states": {
    "entity_id_of_perceiver": "observed|hidden|undetected|unnoticed"
}
```

Default: `unnoticed` for new entities; `observed` for entities the party has line of sight to at encounter start.

**State transitions:**
- `unnoticed` → `undetected`: perceiver is aware something may be present but not location
- `undetected` → `hidden`: Seek success or Point Out
- `hidden` → `observed`: Seek crit success or coming into clear LoS
- `observed` → `hidden`: creature uses Sneak (Stealth) successfully

### Sense Precision System (REQ 2267–2268)

Add `senses` array to entity stats:
```json
"senses": [
    {"type": "vision", "precision": "precise", "range_ft": 60},
    {"type": "hearing", "precision": "imprecise", "range_ft": 30},
    {"type": "smell", "precision": "vague", "range_ft": 10}
]
```

Default senses for all entities: vision (precise, 60 ft), hearing (imprecise, 30 ft), smell (vague, 5 ft).

Precision caps on detection state:
- Precise → can achieve `observed`
- Imprecise → max `hidden`
- Vague → max `undetected`

---

### Light Level Effects (REQ 2274–2275)

In `CombatEngine.resolveAttack()`, before resolving attack:
1. Get room's `lighting` field
2. Apply to attacker's ability to see target based on senses:
   - Darkness: vision blinded (unless darkvision/greater_darkvision)
   - Dim: vision imprecise (unless low_light_vision) → target at most hidden = need DC 5 flat check

```php
$lighting = $dungeon_data['rooms'][$room_id]['lighting'] ?? 'bright_light';
$target_concealed = ($lighting === 'dim_light' && !$attacker_has_lowlight);
$target_blinded = ($lighting === 'darkness' && !$attacker_has_darkvision);

if ($target_blinded) {
    // Attacker treats target as undetected: flat check (secret) + attack vs flat-footed AC
}
if ($target_concealed) {
    // DC 5 flat check before attack roll
}
```

---

### Concealment Flat Check (REQ 2277)

In `CombatEngine.resolveAttack()`: if target is concealed, roll flat check DC 5 before attack.
If flat check fails → miss (no attack roll needed).

```php
if ($target_concealed) {
    $flat_roll = $this->numberGeneration->rollDie(20);
    if ($flat_roll < 5) {
        return ['degree' => 'failure', 'reason' => 'concealment', ...];
    }
}
```

---

### Special Senses (REQ 2269–2273)

Add boolean fields to entity stats:
- `darkvision: bool` — ignore darkness/dim light effects
- `greater_darkvision: bool` — also ignore magical darkness
- `low_light_vision: bool` — treat dim light as bright light
- `tremorsense_ft: int` — detect moving ground-contact creatures up to range
- `scent_ft: int` — detect creatures by smell up to range; wind modifier flag

---

### Hidden/Undetected Attack Mechanics (REQ 2276–2278)

**Hidden target (DC 11 flat check):**
```php
if ($target_detection === 'hidden') {
    $flat = $this->numberGeneration->rollDie(20);
    if ($flat < 11) { return miss; }
    // Use target's flat-footed AC (not normal AC)
    $ac = $target_ac - 2; // flat-footed
}
```

**Undetected target (attacker guesses square):**
- Client must provide guessed hex
- GM-side: check if correct; if wrong → auto-miss
- If correct → flat check DC unknown (GM rolls secretly) + attack vs flat-footed AC

---

## System 2: Hero Point Mechanics (REQ 2279–2282)

### Reroll Mechanic (REQ 2280)

Add `hero_point_reroll: true` to any action/check request payload.

In `Calculator` and `CombatEngine`:
```php
if (!empty($action_data['hero_point_reroll'])) {
    // Verify character has ≥1 hero point.
    // Spend 1 hero point.
    // Reroll the d20. Must use new result.
    // Flag as 'fortune' so another fortune effect cannot stack.
}
```

---

### Heroic Recovery with All Hero Points (REQ 2281)

When character is dying and player spends ALL hero points:
- Check `hero_points >= 1` (spend all of them)
- Remove dying condition entirely
- Do NOT add wounded condition
- Remain at 0 HP, unconscious
- Store `hero_point_recovery: true` in event log

Note: REQ 2171 (spend 1 Hero Point) and REQ 2281 (spend all) are DIFFERENT:
- REQ 2171: spend 1 Hero Point → gain HP equal to Con mod, remove dying, gain wounded
- REQ 2281: spend ALL Hero Points → remove dying, no wounded, stay at 0 HP

Coordinate with HP/Dying dev task (inbox: `20260406-impl-hp-dying-fixes/command.md`).

---

### Familiar/Companion Hero Points (REQ 2282)

When familiar or animal companion system is implemented:
- The owner can spend their own Hero Points on behalf of the familiar/companion
- Tag the reroll with `on_behalf_of: familiar_id`

---

## System 3: Initiative Edge Cases (REQ 2286, 2288)

### In-World Time (REQ 2286)

Track `in_world_seconds` in `game_state`:
```php
// In startRound():
$game_state['in_world_seconds'] = ($game_state['in_world_seconds'] ?? 0) + 6;
```

This enables time-sensitive effects (e.g., duration in minutes/hours for exploration mode).

### Knockout Initiative Shift (REQ 2288)

In `HPManager.applyDamage()`: if HP drops to 0, find attacker's initiative and set entity's initiative to `attacker_initiative - 0.1` (just after attacker).

```php
if ($new_hp <= 0 && $attacker_initiative !== NULL) {
    $this->encounterStore->updateParticipant($entity_id, [
        'initiative' => $attacker_initiative - 0.1
    ]);
    // On next startRound(), sort re-orders automatically.
}
```

---

## Implementation Order

1. Detection state machine (detection_states field replacement)
2. Light level effect application
3. Concealment flat check in attack resolution
4. Special senses (darkvision, low-light, tremorsense)
5. Hidden/undetected attack mechanics
6. Hero Point reroll mechanic
7. Heroic recovery (all Hero Points)
8. In-world time tracking
9. Knockout initiative shift

---

## DB Update

```sql
UPDATE dc_requirements SET status='implemented' WHERE id IN (2267,2268,2269,2270,2271,2272,2273,2274,2275,2276,2277,2278,2280,2281,2282,2286,2288);
```
- Agent: dev-dungeoncrawler
- Status: pending
