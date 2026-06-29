- Status: done
- Completed: 2026-04-11T01:40:59Z

# Suite Activation: dc-cr-skills-acrobatics-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T17:07:56+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-acrobatics-actions"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-acrobatics-actions/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-acrobatics-actions-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-acrobatics-actions",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-acrobatics-actions"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-acrobatics-actions-<route-slug>",
     "feature_id": "dc-cr-skills-acrobatics-actions",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-acrobatics-actions",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-acrobatics-actions

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Acrobatics skill actions — Balance, Tumble Through, Maneuver in Flight, Squeeze, and Escape extension
**KB reference:** skills grooming pattern follows dc-cr-class-* batch conventions (class session, 2026-04-07); skill-system dependency dc-cr-skill-system confirmed shipped.
**Dependency note:** dc-cr-skills-calculator-hardening (planned, not shipped) — flagged per AC; no TCs deferred since hardening is a calculator improvement, not a blocker for action logic tests. All 28 TCs immediately activatable at Stage 0.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Acrobatics action business logic: action type, timing, degrees of success, proficiency gates, condition flags, status effects |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter/exploration phase handlers only |

---

## Test Cases

### Escape Extension

### TC-ACR-01 — Escape accepts Acrobatics modifier as alternative
- **Suite:** module-test-suite
- **Description:** When a character attempts the Escape action, they may choose Acrobatics modifier OR unarmed attack modifier; the system presents both options and applies whichever the player selects.
- **Expected:** escape_modifier_type field accepts `acrobatics` or `unarmed`; result calculated using selected modifier; both options present in action UI/data.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-02 — Escape default is unarmed when Acrobatics not selected
- **Suite:** module-test-suite
- **Description:** If player does not explicitly select Acrobatics, Escape defaults to unarmed attack modifier (backward-compatible with existing Escape implementation).
- **Expected:** escape_modifier_type = `unarmed` by default; result unchanged from pre-extension behavior.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Balance

### TC-ACR-03 — Balance is a 1-action Move action
- **Suite:** module-test-suite
- **Description:** Balance action costs exactly 1 action and has the Move trait; action economy recorded correctly.
- **Expected:** action_cost = 1; traits include `move`; action recorded in turn action log.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-04 — Character is flat-footed during Balance
- **Suite:** module-test-suite
- **Description:** When a character initiates Balance, they gain the flat-footed condition for the duration of the action.
- **Expected:** flat_footed = true on character record while Balance is resolving; cleared after resolution.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-05 — Balance Critical Success: move at full Speed
- **Suite:** module-test-suite
- **Description:** On a critical success, character moves up to their full Speed along the balanced surface with no restriction.
- **Expected:** movement_result = full Speed; difficult_terrain_flag = false; no prone; no turn_end.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-06 — Balance Success: move at full Speed (difficult terrain tracking)
- **Suite:** module-test-suite
- **Description:** On a success, character moves at full Speed but the space counts as difficult terrain for tracking purposes (e.g., subsequent movement calculations this turn).
- **Expected:** movement_result = full Speed; difficult_terrain_flag = true (for tracking); no prone; no turn_end.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-07 — Balance Failure: movement stops or character falls prone
- **Suite:** module-test-suite
- **Description:** On a failure, movement stops at the current position and character falls prone.
- **Expected:** movement_result = 0 (movement stopped); prone = true.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-08 — Balance Critical Failure: prone AND turn ends
- **Suite:** module-test-suite
- **Description:** On a critical failure, character falls prone AND the turn immediately ends (no further actions available this turn).
- **Expected:** prone = true; turn_end = true; remaining actions = 0; both flags set simultaneously, not just one.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-09 — Balance DC tiers by sample surface
- **Suite:** module-test-suite
- **Description:** Balance DC correctly maps to sample surface types: Untrained (roots/cobblestones), Trained (wooden beam), Expert (gravel), Master (tightrope), Legendary (razor edge).
- **Expected:** DC returned for each surface_type matches the expected proficiency-tier DC; no DC assigned outside these tiers for the listed samples.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-10 — Balance blocked in midair without fly speed
- **Suite:** module-test-suite
- **Description:** Attempting Balance when character is airborne and has no fly speed returns an error; no check rolled.
- **Expected:** Balance attempt in midair without fly_speed → action blocked; error returned; no DC check initiated.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-11 — Balance check not called on normal flat ground
- **Suite:** module-test-suite
- **Description:** Edge case — Balance check is not triggered when character is on normal flat ground (sure footing); character moves without requiring a check.
- **Expected:** Balance check_required = false when surface_type = `flat`; no DC check; no flat-footed flag.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Tumble Through

### TC-ACR-12 — Tumble Through requires entering enemy space
- **Suite:** module-test-suite
- **Description:** Tumble Through only triggers a check when the character attempts to enter and pass through an occupied enemy space; movement that does not enter an enemy space does not trigger a check.
- **Expected:** check triggered when path_includes_enemy_space = true; no check when path is clear.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-13 — Tumble Through Success: character passes through, space is difficult terrain
- **Suite:** module-test-suite
- **Description:** On success, character completes the move through the enemy space; the enemy space counts as difficult terrain for movement cost calculation.
- **Expected:** movement_result = through; difficult_terrain_flag = true for that space; character exits enemy space.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-14 — Tumble Through Failure: movement stops, enemy reactions trigger
- **Suite:** module-test-suite
- **Description:** On failure, character's movement stops in front of the enemy space (not inside) and the enemy may use their reactions.
- **Expected:** movement_result = stopped before enemy space; enemy_reaction_eligible = true; character does not enter enemy space.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-15 — Tumble Through: Climb/Fly/Swim substitution for Stride in appropriate environments
- **Suite:** module-test-suite
- **Description:** In environments where Climb, Fly, or Swim is the movement mode (e.g., underwater, aerial combat), those movement types substitute for Stride in Tumble Through.
- **Expected:** tumble_through_movement_type accepts `climb`, `fly`, `swim` when environment_context = matching mode; check and resolution proceed normally.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-16 — Tumble Through vs immovable/incorporeal enemies: check presented
- **Suite:** module-test-suite
- **Description:** Edge case — for immovable or incorporeal enemies, the system presents the Tumble Through check; GM applicability is preserved (system does not auto-block or auto-succeed).
- **Expected:** check presented regardless of enemy mobility/corporeal status; result recorded; GM can override.
- **Roles covered:** authenticated player, GM/admin
- **Status:** immediately activatable

---

### Maneuver in Flight

### TC-ACR-17 — Maneuver in Flight requires fly speed AND Trained Acrobatics
- **Suite:** module-test-suite
- **Description:** Maneuver in Flight is gated on both fly_speed > 0 and acrobatics_proficiency ≥ Trained; missing either blocks the action.
- **Expected:** Action available only when fly_speed > 0 AND acrobatics_rank ≥ trained; missing either → action blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-18 — Maneuver in Flight blocked: no fly speed
- **Suite:** module-test-suite
- **Description:** Failure mode — character with Trained Acrobatics but no fly speed cannot use Maneuver in Flight.
- **Expected:** fly_speed = 0 → action blocked; error returned; no check rolled.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-19 — Maneuver in Flight blocked: untrained Acrobatics (even with fly speed)
- **Suite:** module-test-suite
- **Description:** Failure mode — character with fly speed but Untrained Acrobatics cannot use Maneuver in Flight.
- **Expected:** acrobatics_rank = untrained → action blocked; error returned; no check rolled even with fly_speed > 0.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-20 — Maneuver in Flight requires active fly speed (not jump or levitate)
- **Suite:** module-test-suite
- **Description:** Edge case — temporary height gain from Jump or Levitate does not satisfy the fly speed requirement; only a true fly speed qualifies.
- **Expected:** jump_in_air = true, fly_speed = 0 → action blocked; levitate_active = true, fly_speed = 0 → action blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-21 — Maneuver in Flight DC tiers by sample maneuver
- **Suite:** module-test-suite
- **Description:** DC maps correctly to sample maneuvers: Trained (steep ascent/descent), Expert (against wind or hover), Master (reverse direction), Legendary (gale force winds).
- **Expected:** DC returned for each maneuver_type matches the expected proficiency-tier DC.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-22 — Maneuver in Flight Failure: Reflex save triggered or character falls
- **Suite:** module-test-suite
- **Description:** On a failure, a Reflex save is triggered; if the save fails, the character falls.
- **Expected:** On Failure result: reflex_save_triggered = true; if reflex save fails: character gains falling status; if reflex save succeeds: character maintains altitude.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Squeeze

### TC-ACR-23 — Squeeze requires Trained Acrobatics
- **Suite:** module-test-suite
- **Description:** Squeeze is blocked for characters with Untrained Acrobatics.
- **Expected:** acrobatics_rank = untrained → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-24 — Squeeze is an exploration activity with 1 min/5 ft speed
- **Suite:** module-test-suite
- **Description:** Squeeze is classified as an exploration activity (not an encounter action); speed through tight space = 1 minute per 5 feet traveled.
- **Expected:** action_type = exploration; squeeze_speed = 1 min per 5 ft; action not available as a single encounter action.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-25 — Squeeze Critical Success: 1 min/10 ft speed
- **Suite:** module-test-suite
- **Description:** On Critical Success, squeeze speed doubles to 1 minute per 10 feet.
- **Expected:** critical_success → squeeze_speed = 1 min per 10 ft.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-26 — Squeeze Critical Failure: character stuck
- **Suite:** module-test-suite
- **Description:** On Critical Failure, character becomes stuck in the tight space; movement through that space is blocked until freed.
- **Expected:** stuck = true; movement_blocked_through_space = true; character cannot advance until freed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-27 — Squeeze: stuck character freed by non-critical-failure follow-up Squeeze check
- **Suite:** module-test-suite
- **Description:** A stuck character can attempt another Squeeze check; any result that is not a Critical Failure frees them (Success, Failure, Critical Success all free the character).
- **Expected:** Follow-up Squeeze with result = success OR failure OR critical_success → stuck = false; movement_blocked = false. Follow-up with critical_failure → stuck = true (still stuck).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ACR-28 — Squeeze DC tiers by sample passage
- **Suite:** module-test-suite
- **Description:** DC maps correctly to sample passages: Trained (barely fits shoulders), Master (barely fits head).
- **Expected:** DC returned for each passage_type matches the expected proficiency-tier DC.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### ACL regression

### TC-ACR-29 — ACL regression: no new routes introduced by Acrobatics actions
- **Suite:** role-url-audit
- **Description:** Acrobatics action implementation adds no new HTTP routes; existing encounter/exploration phase handlers retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing phase handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Notes to PM

1. **TC-ACR-04 (flat-footed timing):** AC states character is flat-footed "during the action." Confirm whether flat-footed applies only for the Balance resolution (i.e., anyone targeting the character during that action benefits from the condition) or if it persists for the full turn. This affects how long the flat-footed flag is active.
2. **TC-ACR-07 (Balance Failure behavior):** AC states "movement stops OR character falls prone." The `OR` is ambiguous — does failure always cause prone, or does the system choose one? Recommend "movement stops AND prone" for deterministic behavior; flagging for PM confirmation.
3. **TC-ACR-16 (Tumble Through vs immovable/incorporeal):** AC states "GM determines applicability; system presents check." Confirm whether this means the system always presents the check and GM adjudicates, or whether certain enemy flags (e.g., `incorporeal = true`) automatically block the check. Current TC assumes the former.
4. **dc-cr-skills-calculator-hardening dependency:** This planned feature is not a prerequisite for Acrobatics action logic tests; all 29 TCs (including TC-ACR-29) are immediately activatable. When calculator hardening ships, revisit DC-computation TCs (TC-ACR-09, TC-ACR-21, TC-ACR-28) to confirm DC values are calculated rather than hardcoded.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-acrobatics-actions

## Gap analysis reference
- DB sections: core/ch04/Acrobatics (Dex) (REQs 1602–1614)
- Depends on: dc-cr-skill-system ✓, dc-cr-skills-calculator-hardening (planned)

---

## Happy Path

### Escape (Acrobatics alternative)
- [ ] `[EXTEND]` Escape action accepts Acrobatics modifier as an alternative to unarmed attack modifier; player selects which to use.

### Balance [1 action, Move]
- [ ] `[NEW]` Balance is a 1-action move action; character is flat-footed during the action.
- [ ] `[NEW]` Balance degrees of success: Critical Success = move at full Speed; Success = move at full Speed (counts as difficult terrain for tracking purposes); Failure = movement stops or character falls prone; Critical Failure = character falls prone and turn ends.
- [ ] `[NEW]` Sample DCs applied: Untrained (roots/cobblestones), Trained (wooden beam), Expert (gravel), Master (tightrope), Legendary (razor edge).
- [ ] `[NEW]` Balance requires a surface to balance on; attempting in midair without flight is blocked.

### Tumble Through [1 action, Move]
- [ ] `[NEW]` Tumble Through allows movement through an occupied enemy space (must enter their space to trigger the check).
- [ ] `[NEW]` Can substitute Climb/Fly/Swim for Stride in appropriate environments (e.g., underwater, aerial combat).
- [ ] `[NEW]` Success = character passes through (space counts as difficult terrain); Failure = movement stops and enemy reactions trigger.

### Maneuver in Flight [1 action, Move, Trained]
- [ ] `[NEW]` Maneuver in Flight requires a fly Speed AND Trained Acrobatics; blocked otherwise.
- [ ] `[NEW]` Sample DCs applied: Trained (steep ascent/descent), Expert (against wind or hover), Master (reverse direction), Legendary (gale force winds).
- [ ] `[NEW]` Failure on Maneuver in Flight triggers a Reflex save or the character falls.

### Squeeze [Exploration, Trained]
- [ ] `[NEW]` Squeeze is an exploration activity; requires Trained Acrobatics; speed through tight space = 1 min/5 ft (Critical Success: 1 min/10 ft).
- [ ] `[NEW]` Critical Failure: character becomes stuck; they can escape with a follow-up Squeeze check (any non-critical-failure result frees them).
- [ ] `[NEW]` Sample DCs applied: Trained (barely fits shoulders), Master (barely fits head).

---

## Edge Cases

- [ ] `[NEW]` Balance check not called when character has sure footing (e.g., on normal flat ground).
- [ ] `[NEW]` Tumble Through vs immovable/incorporeal enemies: GM determines applicability; system presents check.
- [ ] `[NEW]` Maneuver in Flight requires active fly speed (not just jump or levitate).

---

## Failure Modes

- [ ] `[TEST-ONLY]` Maneuver in Flight blocked for characters without fly speed or untrained Acrobatics.
- [ ] `[TEST-ONLY]` Squeeze blocked for characters with untrained Acrobatics.
- [ ] `[TEST-ONLY]` Balance Critical Failure: both prone AND turn-ending trigger correctly.
- [ ] `[TEST-ONLY]` Stuck character from Squeeze critical failure: movement through that space blocked until freed.

---

## Security acceptance criteria

- Security AC exemption: skill action logic; no new routes or user-facing input beyond existing encounter/exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
