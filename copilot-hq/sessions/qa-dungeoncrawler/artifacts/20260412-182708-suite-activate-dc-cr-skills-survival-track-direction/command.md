- Status: done
- Completed: 2026-04-12T22:51:00Z

# Suite Activation: dc-cr-skills-survival-track-direction

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:27:08+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-survival-track-direction"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-survival-track-direction/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-survival-track-direction-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-survival-track-direction",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-survival-track-direction"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-survival-track-direction-<route-slug>",
     "feature_id": "dc-cr-skills-survival-track-direction",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-survival-track-direction",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-survival-track-direction

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Survival skill — Sense Direction, Cover Tracks, Track
**KB reference:** None found specific to Survival. Cover Tracks pursuer-DC pattern (Track DC = Cover Tracks result) is the same cross-feature DC-passing pattern seen in Create a Forgery detection (SOC). No dc-cr-conditions dependency — all Survival actions are exploration activities with no persistent character-state requirements beyond the trail record for Track Crit Fail.
**Dependency note:** All TCs immediately activatable on dc-cr-skill-system. The only data model dependency beyond skill-system is a "trail record" for Track (to enforce Crit Fail permanent-loss). No character condition states required.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Survival business logic: Sense Direction (free exploration, no-check in clear, check in special conditions, Crit Success landmark distance), Cover Tracks (Trained gate, half-speed, pursuer DC = character Stealth/Survival), Track (Trained gate, trail-age/environment DC, 4 degrees, Crit Fail permanent trail loss, Cover Tracks interaction) |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing exploration handler routes only |

---

## Test Cases

### Sense Direction

### TC-SUR-01 — Sense Direction: free exploration activity, no action cost
- **Suite:** module-test-suite
- **Description:** Sense Direction is a free exploration activity — it requires no action expenditure during exploration and is available to any character regardless of Survival rank.
- **Expected:** activity_type = free_exploration; survival_rank = untrained → action allowed (no Trained gate for this action).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-02 — Sense Direction: no check in clear conditions
- **Suite:** module-test-suite
- **Description:** In clear conditions (normal terrain, daylight, or standard environment), Sense Direction requires no Survival check — the character automatically determines cardinal direction and rough location.
- **Expected:** conditions = clear → no check required; result = direction_and_rough_location (auto-success).
- **Notes to PM:** Confirm what qualifies as "clear conditions" in the data model. Is it a flag on the environment/zone record, or assumed clear unless an active condition applies? Automation needs a deterministic trigger.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-03 — Sense Direction: check required in supernatural darkness, magical fog, or featureless planes
- **Suite:** module-test-suite
- **Description:** In special adverse navigation conditions (supernatural darkness, magical fog, featureless planes), a Survival check is required to Sense Direction.
- **Expected:** conditions IN [supernatural_darkness, magical_fog, featureless_plane] → Survival check required; auto-success does not apply.
- **Notes to PM:** Confirm the authoritative list of conditions that require a Sense Direction check. AC lists three; are there others? Automation needs an enumerated condition list.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (check-trigger gate; no check resolution detail required for this TC)

### TC-SUR-04 — Sense Direction: Critical Success also returns approximate distance from landmark
- **Suite:** module-test-suite
- **Description:** On Critical Success (when a check is required), the character also receives an approximate distance from their home settlement or last known landmark — in addition to direction and rough location.
- **Expected:** result = critical_success (on a check) → output includes direction + rough_location + approximate_distance_from_landmark.
- **Notes to PM:** Confirm whether "home settlement" and "last known landmark" are tracked values in the character record or GM-adjudicated. Automation needs to know what data to assert on (stored value vs narrative text).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (output fields; no external state required beyond character record)

---

### Cover Tracks

### TC-SUR-05 — Cover Tracks: Trained gate
- **Suite:** module-test-suite
- **Description:** Cover Tracks requires Trained Survival. Untrained characters are blocked from the activity.
- **Expected:** survival_rank = untrained → Cover Tracks blocked; survival_rank >= trained → allowed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-06 — Cover Tracks: exploration activity, character moves at half speed
- **Suite:** module-test-suite
- **Description:** Cover Tracks is an exploration activity. While active, the character's movement speed is halved.
- **Expected:** activity_type = exploration; movement_speed_modifier = 0.5 (half speed) while Cover Tracks is active.
- **Notes to PM:** Confirm whether half-speed rounds to 5-ft intervals (same as Sneak) or is a free decimal. Automation needs a deterministic speed calculation rule.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-07 — Cover Tracks: pursuer must beat Stealth or Survival DC to follow the covered trail
- **Suite:** module-test-suite
- **Description:** A pursuer attempting to Track a covered trail must succeed at a Survival check against the covering character's Stealth DC or Survival DC (whichever is higher, or as determined by implementation).
- **Expected:** pursuer.track_check vs max(character.stealth_dc, character.survival_dc) → pursuer_success = trail followed; pursuer_failure = trail lost.
- **Notes to PM:** Confirm whether the pursuer uses the character's Stealth DC, Survival DC, or the result of the Cover Tracks check roll (as a set DC). AC says "Stealth or Survival DC" which may mean the character chooses, or it's always Survival. Automation needs a deterministic DC source.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (DC source ambiguity is a PM flag, not a blocker for gate logic)

---

### Track

### TC-SUR-08 — Track: Trained gate
- **Suite:** module-test-suite
- **Description:** Track requires Trained Survival. Untrained characters are blocked from the activity.
- **Expected:** survival_rank = untrained → Track blocked; survival_rank >= trained → allowed.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-09 — Track: DC based on trail age and environmental conditions
- **Suite:** module-test-suite
- **Description:** The DC for Track is determined by how old the trail is and the environmental conditions (weather, terrain). A fresh trail in clear terrain has a lower DC than an old trail in rain.
- **Expected:** track_dc = f(trail_age, environment_conditions); older trail → higher DC; adverse conditions → higher DC.
- **Notes to PM:** Confirm the authoritative DC table for Track (trail age × environment matrix or formula). Automation needs exact DC values per tier combination to write deterministic fixtures.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (DC gate; exact values are a PM flag for fixture configuration)

### TC-SUR-10 — Track: Critical Success — fast movement and full information
- **Suite:** module-test-suite
- **Description:** On Critical Success, the character follows the trail at full (or faster) movement speed and receives full information about the tracked party (size, composition, elapsed time, etc.).
- **Expected:** result = critical_success → movement_speed = full (or better); trail_info_level = full.
- **Notes to PM:** Confirm what "full info" consists of in the data model (trail-record fields: party size, rough elapsed time, etc.).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-11 — Track: Success — follow trail at half speed
- **Suite:** module-test-suite
- **Description:** On Success, the character follows the trail at half their normal movement speed. They make progress but slower.
- **Expected:** result = success → trail_progress = true; movement_speed_modifier = 0.5.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-12 — Track: Failure — no progress, can retry in same area
- **Suite:** module-test-suite
- **Description:** On Failure, the character makes no progress on the trail. They may retry the Track check in the same area (trail is not permanently lost).
- **Expected:** result = failure → trail_progress = false; trail_state = retryable; retry_allowed = true.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SUR-13 — Track: Critical Failure — trail permanently lost, cannot retry
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the trail is permanently lost. The character cannot retry that specific trail. The trail record must be marked as permanently-lost (not just failed).
- **Expected:** result = critical_failure → trail_state = permanently_lost; retry_allowed = false; attempting to retry returns blocked/error.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Interactions

### TC-SUR-14 — Cover Tracks + Track interaction: Track DC equals Cover Tracks result
- **Suite:** module-test-suite
- **Description:** Edge case — when a character used Cover Tracks on a trail and a pursuer attempts to Track that same trail, the Track DC is determined by the Cover Tracks roll result (not the standard trail-age/environment table).
- **Expected:** trail.covered_by_cover_tracks = true → track_dc = cover_tracks_result (stored on trail record); standard DC table not used.
- **Notes to PM:** Confirm whether Cover Tracks result is stored on the trail record as the new DC, or whether it adds a modifier to the standard DC. Automation needs to know which value to read for the Track DC assertion.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (interaction gate; requires trail record to store Cover Tracks result)

---

### ACL regression

### TC-SUR-15 — ACL regression: no new routes introduced by Survival
- **Suite:** role-url-audit
- **Description:** Survival implementation adds no new HTTP routes; existing exploration handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing exploration handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

No conditional TCs — all 15 TCs are immediately activatable at Stage 0 (TC-SUR-01 through TC-SUR-15).

The only data model requirement beyond dc-cr-skill-system is a trail record entity (for Track) with fields: trail_state (active/retryable/permanently_lost), cover_tracks_result (optional DC override), trail_age, environment_conditions.

---

## Notes to PM

1. **TC-SUR-02 (clear conditions definition):** Confirm whether "clear conditions" is a flag on the environment/zone record or the default absence of adverse-condition flags. Automation needs a deterministic trigger for no-check vs check.
2. **TC-SUR-03 (check-required conditions list):** Confirm the complete list of conditions that require a Sense Direction check (AC lists 3; are there others?). Automation needs an enumerated list.
3. **TC-SUR-04 (landmark/settlement record):** Confirm whether "home settlement" and "last known landmark" are character-record fields or GM-adjudicated. If stored, automation can assert on the output value.
4. **TC-SUR-06 (half-speed rounding):** Confirm whether Cover Tracks half-speed rounds to 5-ft intervals (like Sneak) or is a free decimal (e.g., Speed 25 → 12.5 ft/action). Automation needs a deterministic speed calculation.
5. **TC-SUR-07 (pursuer DC source):** Confirm whether the pursuer's Track DC when following a covered trail is the character's Stealth DC, Survival DC, or the actual Cover Tracks roll result. AC says "Stealth or Survival DC" — is it character's choice or always highest?
6. **TC-SUR-09 (Track DC table):** Confirm the authoritative DC table (trail age × environment matrix). Automation needs exact DC values per tier combination for fixture setup.
7. **TC-SUR-10 (Crit Success info fields):** Confirm what "full information" fields are included on a Crit Success Track result (party size, elapsed time, etc.) and which trail-record fields these map to.
8. **TC-SUR-14 (Cover Tracks DC storage):** Confirm whether the Cover Tracks result is stored on the trail record as an override DC or added as a modifier to the standard DC. Critical for TC-SUR-14 assertion.
9. **Trail record model:** Track requires a trail entity with at minimum: trail_state enum (active/retryable/permanently_lost), cover_tracks_dc_override (nullable), trail_age, environment_flags. Recommend PM confirm this entity is in the dc-cr-skills-survival-track-direction implementation scope.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-survival-track-direction

## Gap analysis reference
- DB sections: core/ch04/Survival (Wis) (REQs 1730–1737)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Sense Direction [Exploration]
- [ ] `[NEW]` Sense Direction is a free exploration activity; determines cardinal direction and rough location.
- [ ] `[NEW]` No check required in clear conditions; check required in supernatural darkness, magical fog, or featureless planes.
- [ ] `[NEW]` Critical Success: also senses approximate distance from home settlement or last known landmark.

### Cover Tracks [Exploration, Trained]
- [ ] `[NEW]` Cover Tracks is an exploration activity requiring Trained Survival; character moves at half speed.
- [ ] `[NEW]` Pursuers tracking the character's path must succeed at a Survival check vs the character's Stealth or Survival DC.

### Track [Exploration, Trained]
- [ ] `[NEW]` Track is an exploration activity requiring Trained Survival.
- [ ] `[NEW]` DC determined by how old the trail is and environmental conditions.
- [ ] `[NEW]` Degrees: Crit Success = fast movement + full info; Success = follow at half speed; Failure = no progress (can retry in same area); Crit Failure = lost track, cannot retry that specific trail.

---

## Edge Cases
- [ ] `[NEW]` Cover Tracks and Track in the same area: Track DC equals Cover Tracks result.
- [ ] `[NEW]` Track untrained: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Track Crit Fail: trail permanently lost (cannot retry that specific track).
- [ ] `[TEST-ONLY]` Cover Tracks without Trained Survival: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
