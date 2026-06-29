# Suite Activation: dc-cr-skills-athletics-actions

- Agent: qa-dungeoncrawler
- Status: pending

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:49:28+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-athletics-actions"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-athletics-actions/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-athletics-actions-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-athletics-actions",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-athletics-actions"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-athletics-actions-<route-slug>",
     "feature_id": "dc-cr-skills-athletics-actions",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-athletics-actions",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-athletics-actions

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Athletics skill actions — Escape extension, Climb, Force Open, Grapple, High Jump, Long Jump, Shove, Swim, Trip, Disarm, Falling Damage
**KB reference:** conditions-dependency pattern follows dc-cr-class-rogue/03-test-plan.md (TC-ROG-18/19/20 deferred on dc-cr-conditions); same pattern applies here.
**Dependency note:** dc-cr-conditions (in-progress Release B) — 10 TCs conditional on dc-cr-conditions for grabbed/restrained/flat-footed/prone condition tracking; 37 TCs immediately activatable at Stage 0.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Athletics action business logic: action costs, traits, degrees of success, proficiency gates, MAP, size limits, movement math, hold-breath tracking |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter phase handlers only |

---

## Test Cases

### Escape Extension

### TC-ATH-01 — Escape accepts Athletics modifier as alternative
- **Suite:** module-test-suite
- **Description:** When a character attempts the Escape action, they may choose Athletics modifier OR unarmed attack modifier; the system presents both options.
- **Expected:** escape_modifier_type accepts `athletics` or `unarmed`; result calculated using selected modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-02 — Escape default is unarmed when Athletics not selected
- **Suite:** module-test-suite
- **Description:** Escape defaults to unarmed attack modifier when player does not explicitly select Athletics (backward-compatible with existing Escape implementation).
- **Expected:** escape_modifier_type = `unarmed` by default.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Climb

### TC-ATH-03 — Climb is a 1-action Move action
- **Suite:** module-test-suite
- **Description:** Climb costs exactly 1 action and has the Move trait.
- **Expected:** action_cost = 1; traits include `move`.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-04 — Climb: flat-footed applied unless character has a climb Speed
- **Suite:** module-test-suite
- **Description:** Character without a dedicated climb Speed is flat-footed during Climb; character with climb Speed is not flat-footed.
- **Expected:** flat_footed = true when climb_speed = 0; flat_footed = false when climb_speed > 0.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (flat-footed condition tracking)

### TC-ATH-05 — Climb movement distance scales with land Speed
- **Suite:** module-test-suite
- **Description:** On Success, character moves approximately 5 ft; on Critical Success, approximately 10 ft (for standard Speed character); exact formula tied to base Speed.
- **Expected:** climb_movement_success = ~5 ft; climb_movement_crit_success = ~10 ft for base Speed 25.
- **Notes to PM:** Confirm whether this is exactly 5 ft / 10 ft (fixed) or Speed/5 / Speed/2.5 (proportional). Affects parameterization.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-06 — Climb Critical Failure: character falls and lands prone
- **Suite:** module-test-suite
- **Description:** On Critical Failure, character falls from climbing surface and lands prone.
- **Expected:** result = fall; prone = true; position = base of climb.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone condition tracking)

---

### Force Open

### TC-ATH-07 — Force Open has Attack trait (counts toward MAP)
- **Suite:** module-test-suite
- **Description:** Force Open has the Attack trait; using it increments the multiple attack penalty for subsequent attacks this turn.
- **Expected:** traits include `attack`; MAP incremented after use.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-08 — Force Open: –2 item penalty without crowbar
- **Suite:** module-test-suite
- **Description:** When no crowbar is equipped/in-hand, Force Open applies a –2 item penalty to the Athletics check.
- **Expected:** item_penalty = –2 when crowbar_equipped = false; item_penalty = 0 when crowbar_equipped = true.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-09 — Force Open Critical Success: object opens without damage
- **Suite:** module-test-suite
- **Description:** On Critical Success, the object opens cleanly with no structural damage.
- **Expected:** object_state = open; object_damaged = false.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-10 — Force Open Success: object becomes broken
- **Suite:** module-test-suite
- **Description:** On Success, the object is forced open but the object gains the broken condition.
- **Expected:** object_state = open; object_condition = broken.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-11 — Force Open Critical Failure: object jammed with –2 penalty to future attempts
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the object becomes jammed (cannot be opened for now) and all future Force Open attempts against it take an additional –2 penalty.
- **Expected:** object_state = jammed; future_force_open_penalty = –2 (stacks per crit failure per rules, or caps at –2 per PM clarification).
- **Notes to PM:** Confirm whether the –2 penalty from Crit Failure stacks across multiple Critical Failures or is a fixed –2 cap.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Grapple

### TC-ATH-12 — Grapple has Attack trait
- **Suite:** module-test-suite
- **Description:** Grapple has the Attack trait; using it increments MAP.
- **Expected:** traits include `attack`; MAP incremented after use.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-13 — Grapple requires one free hand
- **Suite:** module-test-suite
- **Description:** Grapple is blocked if the character has no free hand (both hands occupied by weapons/items) UNLESS they already have an existing grapple or restrain on the target.
- **Expected:** free_hand = false AND active_grapple = false → action blocked; free_hand = false AND active_grapple = true → action available.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-14 — Grapple size limit: blocked for targets more than one size larger
- **Suite:** module-test-suite
- **Description:** Edge case — Grapple is blocked when the target is more than one size category larger than the grappler (e.g., Medium character cannot Grapple a Huge or larger target).
- **Expected:** target_size > attacker_size + 1 → action blocked; target_size = attacker_size + 1 → action allowed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-15 — Grapple Critical Success: target gains restrained condition
- **Suite:** module-test-suite
- **Description:** On Critical Success, target gains the restrained condition lasting until end of grappler's next turn.
- **Expected:** target_condition = restrained; condition_duration = end_of_grappler_next_turn.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (restrained condition)

### TC-ATH-16 — Grapple Success: target gains grabbed condition
- **Suite:** module-test-suite
- **Description:** On Success, target gains the grabbed condition lasting until end of grappler's next turn.
- **Expected:** target_condition = grabbed; condition_duration = end_of_grappler_next_turn.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (grabbed condition)

### TC-ATH-17 — Grapple Failure: existing grapple released
- **Suite:** module-test-suite
- **Description:** On Failure, any existing grapple or restraint the grappler had on this target is released.
- **Expected:** active_grapple_on_target = false after Failure result.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-18 — Grapple Critical Failure: target may grab attacker or knock prone
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the target may immediately grab the attacker or knock the attacker prone (target's choice, if relevant).
- **Expected:** crit_failure → counter_opportunity_eligible = true; target gains option to apply grabbed to attacker OR make attacker prone.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (grabbed/prone applied to attacker)

### TC-ATH-19 — Grapple condition broken by moving away
- **Suite:** module-test-suite
- **Description:** If the grappler moves away from the grabbed/restrained target, the condition is broken (grappler's movement ends the grapple).
- **Expected:** grappler moves 5+ ft from target → active_grapple = false; condition cleared.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (grabbed/restrained condition lifecycle)

---

### High Jump

### TC-ATH-20 — High Jump costs 2 actions
- **Suite:** module-test-suite
- **Description:** High Jump costs exactly 2 actions.
- **Expected:** action_cost = 2.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-21 — High Jump auto-fails without prior Stride of ≥10 ft
- **Suite:** module-test-suite
- **Description:** Edge case — High Jump automatically fails (no check rolled) if the character did not Stride at least 10 ft as part of the 2-action sequence; prone is NOT applied on auto-fail (only on Critical Failure with a roll).
- **Expected:** stride_distance < 10 → result = auto_fail; no check; prone = false.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-22 — High Jump Critical Success: 8 ft vertical (or 5 ft + 10 ft reach)
- **Suite:** module-test-suite
- **Description:** On Critical Success, character reaches 8 ft vertical height (or alternatively 5 ft up + 10 ft forward per rules variant).
- **Notes to PM:** Confirm which variant applies in dc-cr scope — "8 ft vertical" vs "5 ft+10 ft" are different movement interpretations; current TC assumes 8 ft vertical.
- **Expected:** vertical_height = 8 ft on Crit Success.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-23 — High Jump Success: 5 ft vertical
- **Suite:** module-test-suite
- **Description:** On Success, character reaches 5 ft vertical height.
- **Expected:** vertical_height = 5 ft on Success.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-24 — High Jump Failure: resolves as normal Leap
- **Suite:** module-test-suite
- **Description:** On Failure, the action resolves as a normal Leap (standard horizontal jump distance, no vertical bonus).
- **Expected:** result = normal_leap; no additional vertical height.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-25 — High Jump Critical Failure: attacker falls prone
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the attacker falls prone.
- **Expected:** prone = true on Crit Failure.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone condition tracking)

---

### Long Jump

### TC-ATH-26 — Long Jump costs 2 actions; DC = target distance in feet
- **Suite:** module-test-suite
- **Description:** Long Jump costs 2 actions; the DC equals the intended distance in feet (e.g., DC 20 to jump 20 ft).
- **Expected:** action_cost = 2; DC = target_distance_ft.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-27 — Long Jump requires prior Stride of ≥10 ft in same direction
- **Suite:** module-test-suite
- **Description:** Long Jump is blocked if the character did not Stride at least 10 ft in the same direction as the jump.
- **Expected:** stride_same_direction_distance < 10 → action blocked; no check.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-28 — Long Jump max distance capped at character Speed
- **Suite:** module-test-suite
- **Description:** Edge case — a character cannot attempt a Long Jump for a distance exceeding their Speed; the action is blocked if the requested distance > Speed.
- **Expected:** target_distance > character_speed → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-29 — Long Jump Critical Failure: resolves as normal Leap + attacker prone
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the action resolves as a normal Leap and the attacker lands prone.
- **Expected:** result = normal_leap; prone = true.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone condition tracking)

---

### Shove

### TC-ATH-30 — Shove has Attack trait
- **Suite:** module-test-suite
- **Description:** Shove has the Attack trait; using it increments MAP.
- **Expected:** traits include `attack`; MAP incremented after use.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-31 — Shove forced movement does NOT trigger movement reactions
- **Suite:** module-test-suite
- **Description:** Failure mode — movement caused by Shove's push is forced movement and does not trigger reactions that trigger on voluntary movement (e.g., Attacks of Opportunity from movement).
- **Expected:** forced_movement = true for shove push; movement_reactions_eligible = false for that movement.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-32 — Shove Critical Success: 10 ft push
- **Suite:** module-test-suite
- **Description:** On Critical Success, target is pushed 10 ft; grappler may follow with a Stride.
- **Expected:** push_distance = 10; follow_stride_available = true.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-33 — Shove Success: 5 ft push
- **Suite:** module-test-suite
- **Description:** On Success, target is pushed 5 ft; grappler may follow with a Stride.
- **Expected:** push_distance = 5; follow_stride_available = true.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-34 — Shove Critical Failure: attacker falls prone
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the attacker falls prone.
- **Expected:** prone = true for attacker; target unaffected.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone condition tracking)

---

### Swim

### TC-ATH-35 — Swim is a 1-action Move action
- **Suite:** module-test-suite
- **Description:** Swim costs exactly 1 action and has the Move trait.
- **Expected:** action_cost = 1; traits include `move`.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-36 — Swim: no check required in calm water
- **Suite:** module-test-suite
- **Description:** In calm water, the Swim action succeeds automatically without requiring an Athletics check.
- **Expected:** water_condition = calm → no check; movement proceeds at swim speed (or Speed-based default).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-37 — Swim: air-breathing characters must hold breath while submerged
- **Suite:** module-test-suite
- **Description:** Air-breathing characters spend 1 round of held breath per round while submerged; held_breath_remaining decrements each round.
- **Expected:** character_type = air_breathing AND submerged = true → held_breath_remaining decrements by 1 per round.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-38 — Swim: sink rule applied at end of turn (not on initial entry turn)
- **Suite:** module-test-suite
- **Description:** Failure mode — if a character takes no Swim action by end of their turn, they sink 10 ft or drift; this is NOT applied on the very turn they first enter the water.
- **Expected:** turn_entered_water = true → no sink applied; subsequent turn with no Swim action → sink 10 ft.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-39 — Swim Critical Failure: costs 1 round of held breath
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the character loses 1 round of held breath (in addition to normal round deduction).
- **Expected:** held_breath_remaining decremented by 1 extra on Crit Failure (net –2 that round: –1 normal submerged + –1 Crit Failure).
- **Notes to PM:** Confirm whether this is –1 extra (total –2 for that round) or just the Crit Failure penalty replacing the normal deduction.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Trip

### TC-ATH-40 — Trip has Attack trait
- **Suite:** module-test-suite
- **Description:** Trip has the Attack trait; using it increments MAP.
- **Expected:** traits include `attack`; MAP incremented after use.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-41 — Trip Critical Success: 1d6 bludgeoning + prone
- **Suite:** module-test-suite
- **Description:** On Critical Success, target takes 1d6 bludgeoning damage and falls prone.
- **Expected:** damage = 1d6 bludgeoning; prone = true on target.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone on target)

### TC-ATH-42 — Trip Success: prone only (no damage)
- **Suite:** module-test-suite
- **Description:** On Success, target falls prone; no damage applied.
- **Expected:** prone = true on target; damage = 0.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone on target)

### TC-ATH-43 — Trip Critical Failure: attacker falls prone
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the attacker falls prone; target is unaffected.
- **Expected:** prone = true for attacker; target unchanged.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone on attacker)

---

### Disarm

### TC-ATH-44 — Disarm requires Trained Athletics
- **Suite:** module-test-suite
- **Description:** Disarm is blocked for characters with Untrained Athletics.
- **Expected:** athletics_rank = untrained → action blocked; error returned; no check rolled.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-45 — Disarm has Attack trait
- **Suite:** module-test-suite
- **Description:** Disarm has the Attack trait; using it increments MAP.
- **Expected:** traits include `attack`; MAP incremented after use.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-46 — Disarm Critical Success: item dropped
- **Suite:** module-test-suite
- **Description:** On Critical Success, the target drops the item they were holding.
- **Expected:** target_held_item = null (dropped to ground); target_grip = none.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-47 — Disarm Success: grip weakened (bonuses/penalties until start of target's next turn)
- **Suite:** module-test-suite
- **Description:** On Success, target's grip is weakened — they have penalties (or attackers have bonuses) until the start of the target's next turn.
- **Expected:** grip_weakened = true; effect expires at start of target's next turn.
- **Notes to PM:** Confirm exact penalty/bonus values for grip weakened in dc-cr scope (AC states "bonuses/penalties" without specifying magnitude).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-48 — Disarm Critical Failure: attacker becomes flat-footed
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the attacker becomes flat-footed.
- **Expected:** attacker_condition = flat-footed after Crit Failure.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (flat-footed condition tracking)

---

### Falling Damage

### TC-ATH-49 — Falling damage = half distance in feet as bludgeoning
- **Suite:** module-test-suite
- **Description:** A character falling N feet takes N/2 bludgeoning damage (e.g., 20 ft fall = 10 bludgeoning).
- **Expected:** damage = floor(fall_distance_ft / 2) bludgeoning.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-50 — Character lands prone after falling
- **Suite:** module-test-suite
- **Description:** After any fall (regardless of damage amount), character lands prone.
- **Expected:** prone = true after falling resolution.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (prone on landing)

### TC-ATH-51 — Soft surface reduces effective fall distance by up to 20 ft
- **Suite:** module-test-suite
- **Description:** Landing on a soft surface (water, snow) reduces the effective fall distance by up to 20 ft (capped at actual surface depth); a 15 ft fall into water reduces to 0 ft effective (if water depth ≥ 15 ft).
- **Expected:** effective_fall_distance = max(0, fall_distance - min(20, surface_depth_ft)); damage computed on effective distance.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ATH-52 — Grab an Edge reaction: reduces or eliminates fall damage
- **Suite:** module-test-suite
- **Description:** When a character would fall, they may use the Grab an Edge reaction to reduce or eliminate fall damage; if successful, fall distance reduced or character hangs at edge.
- **Expected:** Grab an Edge reaction available when fall triggered; success reduces fall_distance or stops fall entirely.
- **Notes to PM:** Confirm whether Grab an Edge is a separate feat/reaction in scope or part of this feature's base implementation. If it's a feat, TC-ATH-52 should be conditional on that feat being implemented.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (flag for PM if Grab an Edge is a separate feat)

---

### ACL regression

### TC-ATH-53 — ACL regression: no new routes introduced by Athletics actions
- **Suite:** role-url-audit
- **Description:** Athletics action implementation adds no new HTTP routes; existing encounter phase handlers retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing encounter handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Conditional dependency summary

| TC | Dependency feature | Reason conditional |
|---|---|---|
| TC-ATH-04 | `dc-cr-conditions` | flat-footed condition tracking during Climb |
| TC-ATH-06 | `dc-cr-conditions` | prone on Climb Crit Failure |
| TC-ATH-15 | `dc-cr-conditions` | restrained condition from Grapple Crit Success |
| TC-ATH-16 | `dc-cr-conditions` | grabbed condition from Grapple Success |
| TC-ATH-18 | `dc-cr-conditions` | grabbed/prone applied to attacker on Grapple Crit Failure |
| TC-ATH-19 | `dc-cr-conditions` | grabbed/restrained condition broken by movement |
| TC-ATH-25 | `dc-cr-conditions` | prone on High Jump Crit Failure |
| TC-ATH-29 | `dc-cr-conditions` | prone on Long Jump Crit Failure |
| TC-ATH-34 | `dc-cr-conditions` | prone on Shove Crit Failure |
| TC-ATH-41 | `dc-cr-conditions` | prone on Trip Crit Success |
| TC-ATH-42 | `dc-cr-conditions` | prone on Trip Success |
| TC-ATH-43 | `dc-cr-conditions` | prone on Trip Crit Failure (attacker) |
| TC-ATH-48 | `dc-cr-conditions` | flat-footed on Disarm Crit Failure |
| TC-ATH-50 | `dc-cr-conditions` | prone on landing after fall |

37 TCs immediately activatable at Stage 0 (action costs, traits, MAP, math, gates, non-condition outcomes).
14 TCs conditional on `dc-cr-conditions` (in-progress Release B) — activatable when dc-cr-conditions ships.
2 TCs flagged for PM clarification before full parameterization (TC-ATH-22 High Jump variant; TC-ATH-52 Grab an Edge feat scope).

---

## Notes to PM

1. **TC-ATH-05 (Climb distance formula):** AC states "~5 ft success, ~10 ft crit success for standard character." Confirm whether this is exactly 5/10 ft (fixed) or Speed-proportional (Speed/5 for success, Speed/2.5 for crit). Important for parameterized assertions with non-standard Speed characters.
2. **TC-ATH-11 (Force Open jammed penalty stacking):** AC states –2 to future attempts after Crit Failure. Confirm whether this stacks with multiple Crit Failures (–2, –4, –6…) or is a one-time fixed penalty.
3. **TC-ATH-22 (High Jump Crit Success height):** AC states "8 ft vertical (or 5+10)." Confirm which interpretation applies in dc-cr scope.
4. **TC-ATH-39 (Swim Crit Failure breath cost):** AC states "costs 1 round of held breath." Confirm whether this is an extra –1 (so –2 total for that submerged round) or replaces the normal deduction (net –1).
5. **TC-ATH-47 (Disarm Success grip weakened):** AC mentions "bonuses/penalties" without specifying magnitude. The grip-weakened penalty value needs to be defined in the feature implementation before this TC can be fully parameterized.
6. **TC-ATH-52 (Grab an Edge reaction):** AC includes this as part of Falling Damage. Confirm whether Grab an Edge is (a) a base action any character can attempt, or (b) a separate feat/reaction that must be purchased. If (b), this TC should be conditional on the relevant feat feature.
7. **dc-cr-conditions dependency:** dc-cr-conditions is in-progress Release B. Once it ships, all 14 conditional TCs become activatable. Recommend staging: activate the 37 non-condition TCs at Stage 0 for this feature, then activate the 14 condition TCs in the same release as dc-cr-conditions.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-athletics-actions

## Gap analysis reference
- DB sections: core/ch04/Athletics (Str) (REQs 1619–1643)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions (in-progress Release B)

---

## Happy Path

### Escape (Athletics alternative)
- [ ] `[EXTEND]` Escape action accepts Athletics modifier as an alternative to unarmed attack modifier.

### Climb [1 action, Move]
- [ ] `[NEW]` Climb is a 1-action move; character is flat-footed during Climb unless they have a climb Speed.
- [ ] `[NEW]` Movement distance scales with land Speed (~5 ft success, ~10 ft crit success for standard character).
- [ ] `[NEW]` Critical Failure: character falls and lands prone.

### Force Open [1 action, Attack]
- [ ] `[NEW]` Force Open has the attack trait (counts toward MAP); –2 item penalty without crowbar.
- [ ] `[NEW]` Degrees: Critical Success = open without damage; Success = object becomes broken; Critical Failure = jammed + –2 to all future attempts.

### Grapple [1 action, Attack]
- [ ] `[NEW]` Grapple requires one free hand (or an existing grapple/restrain); size limit = target no more than one size larger.
- [ ] `[NEW]` Degrees: Crit Success = restrained; Success = grabbed; Failure = release existing grapple; Crit Failure = target may grab you or knock you prone.
- [ ] `[NEW]` Grabbed and restrained conditions last until end of grappler's next turn; broken by moving away or target Escaping.

### High Jump [2 actions]
- [ ] `[NEW]` High Jump costs 2 actions; requires a Stride of ≥10 ft or automatically fails.
- [ ] `[NEW]` Degrees: Crit Success = 8 ft vertical (or 5+10); Success = 5 ft; Failure = normal Leap; Crit Failure = fall prone.

### Long Jump [2 actions]
- [ ] `[NEW]` Long Jump costs 2 actions; DC = distance in feet; must Stride ≥10 ft in same direction first.
- [ ] `[NEW]` Maximum distance = character Speed; Crit Failure = normal Leap + prone.

### Shove [1 action, Attack]
- [ ] `[NEW]` Shove has attack trait; forced movement does NOT trigger movement reactions.
- [ ] `[NEW]` Degrees: Crit Success = 10 ft push; Success = 5 ft push; Crit Failure = attacker falls prone. Character may follow with a Stride.

### Swim [1 action, Move]
- [ ] `[NEW]` Swim is a 1-action move; no check required in calm water.
- [ ] `[NEW]` Air-breathing characters must hold breath each round while submerged.
- [ ] `[NEW]` If no Swim action at end of turn: sink 10 ft or drift (not applied on the turn entering water).
- [ ] `[NEW]` Critical Failure: costs 1 round of held breath.

### Trip [1 action, Attack]
- [ ] `[NEW]` Trip has attack trait; Crit Success = 1d6 bludgeoning + prone; Success = prone only; Crit Failure = attacker falls prone.

### Disarm [1 action, Attack, Trained]
- [ ] `[NEW]` Disarm requires Trained Athletics; has attack trait.
- [ ] `[NEW]` Crit Success = item dropped; Success = grip weakened (bonuses/penalties until start of target's next turn); Crit Failure = attacker becomes flat-footed.

### Falling Damage
- [ ] `[NEW]` Falling damage = half distance in feet as bludgeoning damage; character lands prone.
- [ ] `[NEW]` Soft surfaces (water, snow) reduce effective fall distance by up to 20 ft (capped at surface depth).
- [ ] `[NEW]` Grab an Edge reaction can reduce or eliminate fall damage.

---

## Edge Cases
- [ ] `[NEW]` Grapple vs creature more than one size larger: blocked.
- [ ] `[NEW]` High Jump without prior Stride: automatically fails (prone not applied on auto-fail).
- [ ] `[NEW]` Long Jump max distance capped at Speed; attempting beyond is blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Disarm blocked for untrained Athletics.
- [ ] `[TEST-ONLY]` Shove forced movement: movement reactions NOT triggered.
- [ ] `[TEST-ONLY]` Swim sink rule: applied only at turn end, not on initial entry turn.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/exploration phase handlers
