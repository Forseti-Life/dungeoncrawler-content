# Dev Task: Implement Exploration Activities and Downtime Rules (Reqs 2290–2310)

**Type:** dev-impl  
**Section:** Ch9 — Exploration Mode and Downtime Mode  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Exploration Mode" and "Downtime Mode"  
**Requirements:** 2290–2310 (except 2298 already implemented)

---

## Summary

Exploration activities are stored as labels but have no mechanical effect. This task adds mechanical resolution for each activity and fixes downtime stubs.

---

## BUG FIX: REST HP FORMULA (REQ 2301)

**File:** `DowntimePhaseHandler.php` — `processLongRest()`  
**Current:** `$entity['state']['hit_points']['current'] = $max_hp;` (restores full HP)  
**Fix:**
```php
$con_mod = $entity['stats']['con_modifier'] ?? 0;
$level = $entity['stats']['level'] ?? 1;
$hp_per_rest = max(1, $con_mod) * $level;
$new_hp = min($max_hp, $current_hp + $hp_per_rest);
$entity['state']['hit_points']['current'] = $new_hp;
```
PF2e ref: "After 8 hours of sleep, you regain Hit Points equal to your Constitution modifier × your level."

---

## EXPLORATION ACTIVITIES

### Travel Speed Calculation (REQ 2290–2291)

**File:** `ExplorationPhaseHandler.php` — new method `calculateTravelSpeed(entity, terrain_type)`

```php
public function calculateTravelSpeed(array $entity, string $terrain_type = 'normal'): array {
    $speed_ft = $entity['stats']['speed'] ?? 25;
    // Miles per hour = speed_ft / 10 (rough PF2e scale: 30 ft/rd × 10 min/hr ÷ 5280 ft/mi)
    // Per Table 9-2: Speed 30 = 3 miles/hour = 24 miles/day (8 hours)
    $mph = round($speed_ft / 10, 1);
    $miles_per_day = $mph * 8;

    $multiplier = match($terrain_type) {
        'difficult' => 0.5,
        'greater_difficult' => 1/3,
        default => 1.0,
    };

    return [
        'mph' => $mph * $multiplier,
        'miles_per_day' => $miles_per_day * $multiplier,
    ];
}
```

---

### Avoid Notice (REQ 2292)

**File:** `ExplorationPhaseHandler.php` — in `set_activity` handler:

When activity is `avoid_notice`:
- Set `travel_speed_modifier: 0.5` on character state
- Set `initiative_skill: stealth` flag (override Perception for next encounter init)
- In `CombatEngine.startEncounter()`: check `initiative_skill` — if `stealth`, roll Stealth + d20 instead of Perception

---

### Defend (REQ 2293)

When activity is `defend`:
- Set `travel_speed_modifier: 0.5`
- Set `shield_raised_on_encounter_start: true`
- In `EncounterPhaseHandler.processEncounterStart()`: apply `shield_raised: true` automatically for characters with this flag

---

### Detect Magic (REQ 2294)

When activity is `detect_magic`:
- Require character has `Detect Magic` spell or innate ability
- Set `travel_speed_modifier: 0.5`
- On each room entry during exploration: auto-add magic items/auras to detected list
- Populate `discovered_magic` in room state with entity IDs that have magic traits

---

### Follow the Expert (REQ 2295)

When activity is `follow_expert`:
- Require `follow_target_id` in params
- Match target's current exploration activity
- Grant circumstance bonus based on target's proficiency in that activity:
  - Trained → +1; Expert → +2; Master → +3; Legendary → +4

---

### Hustle (REQ 2296)

When activity is `hustle`:
- Set `travel_speed_modifier: 2.0`
- Track `hustle_minutes_remaining` = max(10, con_mod * 10)
- Each exploration tick (10 minutes): decrement `hustle_minutes_remaining` by 10
- When `hustle_minutes_remaining <= 0`: apply `fatigued` condition; remove hustle activity

---

### Scout (REQ 2297)

When activity is `scout`:
- Set `travel_speed_modifier: 0.5`
- Set `scout_initiative_bonus: 1` on character state (party-wide on next encounter)
- In `CombatEngine.startEncounter()`: apply `+1` to each party member's initiative if any party member has `scout_initiative_bonus`; clear after applying

---

### Investigate (REQ 2299)

When activity is `investigate`:
- Set `travel_speed_modifier: 0.5`
- On room entry: trigger secret Recall Knowledge check (GM rolls)
- Result gives environmental clues about the room, hidden threats, or item lore

---

### Repeat a Spell (REQ 2300)

When activity is `repeat_spell`:
- Set `travel_speed_modifier: 0.5`
- Require an active concentration spell (`sustained_spell_id`)
- Each exploration tick: automatically sustain the spell (no action needed)

---

### Rest and Daily Preparations (REQ 2302–2305)

#### Sleeping in Armor (REQ 2302)

In `processLongRest()`: check if character has medium/heavy armor equipped:
```php
if ($this->hasArmorEquipped($entity, ['medium', 'heavy'])) {
    $this->conditionManager->addCondition($entity_id, 'fatigued');
}
```

#### Fatigue from Sleep Deprivation (REQ 2303)

Track `hours_since_rest` in entity state (increments via `in_world_seconds`).  
In exploration tick handler: if `hours_since_rest > 16` → apply fatigued condition.  
On rest of ≥6 hours: clear fatigued if source was sleep deprivation; reset `hours_since_rest`.

#### Daily Preparations (REQ 2304–2305)

Add `daily_prepare` action to `ExplorationPhaseHandler` legal intents:
- Requires 1 hour of exploration time (6 exploration ticks)
- Requires prior long rest in same 24-hour period
- On completion:
  - Restore all spell slots
  - Allow investing up to 10 worn magic items (set `invested_items[]`)
  - Record `last_prepared_at` timestamp
- If `last_prepared_at` is within 24 in-game hours → reject with error

---

## DOWNTIME ACTIVITIES

### Long-Term Rest (REQ 2306)

**File:** `DowntimePhaseHandler.php` — add `downtime_rest` action separate from `processLongRest()`:

```php
$con_mod = $entity['stats']['con_modifier'] ?? 0;
$level = $entity['stats']['level'] ?? 1;
$hp_restored = max(1, $con_mod) * (2 * $level);
$new_hp = min($max_hp, $current_hp + $hp_restored);
```

---

### Retraining (REQ 2307–2310)

**File:** `DowntimePhaseHandler.php` — implement `retrain` action:

```php
case 'retrain':
    $retrain_type = $params['retrain_type']; // 'feat', 'skill', 'class_feature_choice'
    $retrain_from = $params['retrain_from'];
    $retrain_to = $params['retrain_to'];

    // Validate cannot retrain: ancestry, heritage, background, class, ability scores (REQ 2308)
    $PROHIBITED = ['ancestry', 'heritage', 'background', 'class', 'ability_score'];
    if (in_array($retrain_type, $PROHIBITED)) {
        return error('Cannot retrain this element.');
    }

    // Determine duration (REQ 2309)
    $major_retrain = in_array($retrain_type, ['druid_order', 'wizard_school', 'sorcerer_bloodline']);
    $days_required = $major_retrain ? 30 : 7;

    // Set in-progress flag to block other activities (REQ 2310)
    $game_state['downtime']['retraining'] = [
        'type' => $retrain_type,
        'from' => $retrain_from,
        'to' => $retrain_to,
        'days_remaining' => $days_required,
        'days_required' => $days_required,
    ];
```

In `advance_day` action: decrement `days_remaining`; when 0 → apply retrain change, clear flag.  
While `retraining` flag is active: reject all other downtime actions (REQ 2310).

---

## Priority Order

1. Bug fix: Rest HP formula (processLongRest)
2. Daily preparations (high-frequency gameplay)
3. Scout and Avoid Notice (affect encounters)
4. Retraining stub → implementation
5. Travel speed + terrain
6. Defend + Hustle + Follow Expert + Detect Magic + Investigate + Repeat Spell
7. Sleeping in armor + sleep deprivation tracking

---

## DB Update

After implementation and QA:
```sql
UPDATE dc_requirements SET status='implemented' WHERE id IN (
    2290, 2291, 2292, 2293, 2294, 2295, 2296, 2297,
    2299, 2300, 2301, 2302, 2303, 2304, 2305,
    2306, 2307, 2308, 2309, 2310
);
```
- Agent: dev-dungeoncrawler
- Status: pending
