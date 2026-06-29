# Dev Task: Implement Missing Basic Actions (Reqs 2190–2218)

**Type:** dev-impl  
**Section:** Ch9 — Basic Actions  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Basic Actions"  
**Requirements:** 2190–2199, 2201–2209, 2211–2215, 2218 (+ fix Delay 2193–2195, fix Ready 2203–2205)

---

## Summary

Most PF2e basic actions are not implemented. This is a large feature task.  
The system currently supports: Strike, Stride, Interact, Delay (partial), Ready (stub only).

---

## Implementation Plan

### Step 1: Add actions to `getLegalIntents()` in EncounterPhaseHandler

Add all missing action types to the allowed list:
```php
'crawl', 'drop_prone', 'escape', 'leap', 'stand', 'step', 'take_cover',
'seek', 'sense_motive', 'aid', 'aid_setup', 'release',
```

---

### Step 2: Add case handlers in EncounterPhaseHandler::processAction()

#### Aid (REQ 2190, 2191)
```
case 'aid_setup':
    // Mark actor as having prepared Aid for target ally.
    // $game_state['turn']['aid_prepared'][$actor_id][$target_id] = $params['skill'];
    // Deduct 1 action.

case 'aid':
    // Reaction: verify aid_prepared exists.
    // Roll check vs DC 20 using $params['skill'].
    // Apply +2/+1/−1 based on degree. Master/legendary bumps crit success.
    // Deduct reaction_available.
```
PF2e ref: CR Rulebook Ch9 — "Aid" pp. 470-471.

#### Crawl (REQ 2192)
```
case 'crawl':
    // Require prone condition on actor.
    // Require Speed >= 10 ft.
    // Move 5 ft. Deduct 1 action.
```

#### Delay (REQ 2193–2195) — FIX existing stub
```
case 'delay':
    // Set delayed=TRUE.
    // Apply any negative start-of-turn effects immediately (dying recovery, persistent damage).
    // End any "until end of turn" buffs.
    // Store remaining actions for re-entry.
    // Allow re-entry via 'delay_reenter' intent on any other creature's turn end.
    // After full round without re-entry: lose remaining actions.
```

#### Drop Prone (REQ 2196)
```
case 'drop_prone':
    // Apply prone condition.
    // Deduct 1 action.
```

#### Escape (REQ 2197–2199)
```
case 'escape':
    // Require grabbed/immobilized/restrained condition.
    // Roll: unarmed modifier, Acrobatics, or Athletics (player's choice) vs. grapple DC.
    // Add attacks_this_turn to MAP calculation (attack trait).
    // Increment attacks_this_turn.
    // Crit success: remove condition + allow 5-ft Stride.
    // Crit fail: set escape_blocked=TRUE until next turn.
```

#### Leap (REQ 2201–2202)
```
case 'leap':
    // Horizontal: Speed >= 30 → up to 15 ft; Speed 15-29 → up to 10 ft.
    // Vertical: up to 3 ft + 5 ft horizontal.
    // Deduct 1 action.
    // High Jump / Long Jump → handled as Athletics actions (specialty basic actions).
```

#### Ready (REQ 2203–2205) — FIX existing stub
```
case 'ready':
    // Deduct 2 actions.
    // Store: ready_action (action type), ready_trigger (description), ready_map (attacks_this_turn).
    // On trigger event: fire stored action as reaction using stored MAP.
    // Cannot Ready a triggered free action (REQ 2205).
```

#### Release (REQ 2206)
```
case 'release':
    // Free action.
    // Remove item from actor's held-in-hand slot.
    // Does NOT trigger manipulate-trait reactions.
```

#### Seek (REQ 2207–2210)
```
case 'seek':
    // 1 action, concentrate.
    // GM-side: roll Perception vs. Stealth DC of each creature/object in area.
    // Secret check (result returned to GM, not player directly).
    // Detection outcomes per degree:
    //   crit success: undetected→observed; hidden→observed.
    //   success: undetected→hidden.
    //   fail/crit fail: no change.
    // Imprecise sense cap: max hidden (not observed).
```

#### Sense Motive (REQ 2211–2212)
```
case 'sense_motive':
    // 1 action, concentrate, secret.
    // GM rolls actor Perception vs. target Deception.
    // Result communicated as secret (not raw roll to player).
    // Track retry cooldown: set sensed[$actor_id][$target_id] = round.
    // Retry only if situation changed.
```

#### Stand (REQ 2213)
```
case 'stand':
    // Remove prone condition.
    // Deduct 1 action.
```

#### Step (REQ 2214–2215)
```
case 'step':
    // Move exactly 5 ft.
    // Does NOT trigger move-triggered reactions (no AoO).
    // Cannot enter difficult terrain.
    // Only valid with land Speed.
    // Deduct 1 action.
```

#### Take Cover (REQ 2218)
```
case 'take_cover':
    // No cover → standard cover (+2 AC, +2 Reflex saves, +2 Stealth).
    // Standard cover → greater cover (+4).
    // Set cover_active=TRUE in entity state.
    // Ends on: move action, Strike, becoming unconscious.
```

---

## Priority Order

1. Stand, Drop Prone (simple, foundational for movement)
2. Step (movement anti-AoO)
3. Stride fixes + movement validation
4. Escape (combat utility)
5. Seek + Sense Motive (AI and detection)
6. Take Cover (AC improvement)
7. Delay fixes (full initiative management)
8. Ready fixes (readied reactions)
9. Aid (ally buff)
10. Crawl, Leap, Release (lower frequency)

---

## DB Update

After each action is implemented and verified by QA:
```sql
UPDATE dc_requirements SET status='implemented' WHERE id IN (...);
```
Full set: 2190, 2191, 2192, 2193, 2194, 2195, 2196, 2197, 2198, 2199, 2201, 2202, 2203, 2204, 2205, 2206, 2207, 2208, 2209, 2210, 2211, 2212, 2213, 2214, 2215, 2218
- Agent: dev-dungeoncrawler
- Status: pending
