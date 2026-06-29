- Status: done
- Completed: 2026-04-12T03:27:53Z

# Suite Activation: dc-cr-rest-watch-starvation

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T23:55:14+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-rest-watch-starvation"`**  
   This links the test to the living requirements doc at `features/dc-cr-rest-watch-starvation/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-rest-watch-starvation-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-rest-watch-starvation",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-rest-watch-starvation"`**  
   Example:
   ```json
   {
     "id": "dc-cr-rest-watch-starvation-<route-slug>",
     "feature_id": "dc-cr-rest-watch-starvation",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-rest-watch-starvation",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-rest-watch-starvation

## Feature
Rest, watch schedule, and starvation/thirst mechanics from PF2E Core Rulebook Chapter 10.

## KB references
- None found for rest or survival mechanics specifically.

## Dependencies
- dc-cr-conditions ✓ (confirmed available — fatigue condition required)
- dc-cr-skill-system ✓ (confirmed available)

## Suite mapping
All TCs target the `dungeoncrawler-content` suite (game-mechanic data/logic).
No new routes or auth surfaces — Security AC exemption confirmed.

---

## Test Cases

### TC-RWS-01: Watch duration — exists by party size
- **Description:** Watch schedule data is defined for standard party sizes; all party members share watch duty; minimum watch segments are tracked.
- **Suite:** dungeoncrawler-content
- **Expected:** Watch schedule record for each supported party size (at minimum 1–6 PCs) returns non-null `watch_duration` and `min_segments`.
- **Roles:** N/A
- **Note to PM:** AC does not specify exact watch duration values per party size. Dev needs a reference table (from PF2E CRB ch10 "Watches" or GM rules). BA should extract and confirm values before implementation.

### TC-RWS-02: Daily preparation sequence — order enforcement
- **Description:** Daily preparation follows the required sequence: rest → watch → prepare spells/abilities (10-minute focus and preparation).
- **Suite:** dungeoncrawler-content
- **Expected:** Daily prep state machine: `rest_complete` must be `true` before `watch_complete` can be set; `watch_complete` must be `true` before `spells_prepared` can be set.
- **Roles:** N/A

### TC-RWS-03: Thirst — immediate fatigue on first day without water
- **Description:** A character without water immediately gains the fatigued condition.
- **Suite:** dungeoncrawler-content
- **Expected:** Day 0 of no water: `conditions` includes `"fatigued"`.
- **Roles:** N/A

### TC-RWS-04: Thirst — damage onset after (Con modifier + 1) days
- **Description:** After (Con modifier + 1) days without water, the character begins taking 1d4 damage per hour.
- **Suite:** dungeoncrawler-content
- **Expected:** At day `(con_modifier + 1)`, dehydration damage track activates: `damage_per_hour: "1d4"`.
- **Roles:** N/A

### TC-RWS-05: Thirst — healing blocked while dehydrated
- **Description:** Damage from dehydration cannot be healed until the character's thirst is quenched.
- **Suite:** dungeoncrawler-content
- **Expected:** Healing attempt while `dehydrated: true` returns `blocked: true, reason: "thirst_unresolved"`.
- **Roles:** N/A

### TC-RWS-06: Thirst — damage track deactivates on water consumed
- **Description:** Once thirst is quenched, the dehydration damage track stops and healing is unblocked.
- **Suite:** dungeoncrawler-content
- **Expected:** After `quench_thirst()`: `dehydrated: false`, `damage_per_hour: null`, healing no longer blocked.
- **Roles:** N/A

### TC-RWS-07: Starvation — immediate fatigue on first day without food
- **Description:** A character without food immediately gains the fatigued condition.
- **Suite:** dungeoncrawler-content
- **Expected:** Day 0 of no food: `conditions` includes `"fatigued"`.
- **Roles:** N/A

### TC-RWS-08: Starvation — damage onset after (Con modifier + 1) days
- **Description:** After (Con modifier + 1) days without food, the character begins taking 1 damage per day.
- **Suite:** dungeoncrawler-content
- **Expected:** At day `(con_modifier + 1)`, starvation damage track activates: `damage_per_day: 1`.
- **Roles:** N/A

### TC-RWS-09: Starvation — healing blocked while starving
- **Description:** Damage from starvation cannot be healed until the character is fed.
- **Suite:** dungeoncrawler-content
- **Expected:** Healing attempt while `starving: true` returns `blocked: true, reason: "starvation_unresolved"`.
- **Roles:** N/A

### TC-RWS-10: Starvation — damage track deactivates on food consumed
- **Description:** Once the character eats, the starvation damage track stops and healing is unblocked.
- **Suite:** dungeoncrawler-content
- **Expected:** After `feed_character()`: `starving: false`, `damage_per_day: null`, healing no longer blocked.
- **Roles:** N/A

### TC-RWS-11: Environmental hazard tracking — interval triggers
- **Description:** Starvation and thirst are tracked as environmental hazards; their damage triggers resolve at the correct intervals (hourly for thirst, daily for starvation).
- **Suite:** dungeoncrawler-content
- **Expected:** Thirst damage trigger interval = `"hourly"`; starvation damage trigger interval = `"daily"`.
- **Roles:** N/A

### TC-RWS-12 (Edge): Con modifier ≤ 0 — minimum 1 day before damage onset
- **Description:** When Con modifier is 0 or negative, damage onset is still at least 1 day (not 0 or negative).
- **Suite:** dungeoncrawler-content
- **Expected:** `onset_days = max(1, con_modifier + 1)` for both starvation and thirst.
- **Roles:** N/A

### TC-RWS-13 (Edge): Simultaneously starving and dehydrated — independent tracks
- **Description:** A character who is both starving and dehydrated has both damage tracks active independently; neither suppresses the other.
- **Suite:** dungeoncrawler-content
- **Expected:** `starving: true` and `dehydrated: true` simultaneously; each damage track fires at its own interval; healing blocked by both conditions independently.
- **Roles:** N/A

### TC-RWS-14 (Failure): Healing during active starvation — blocked
- **Description:** Any healing applied while `starving: true` is blocked and returns no HP.
- **Suite:** dungeoncrawler-content
- **Expected:** `apply_healing(amount)` while `starving: true` → `healed: 0`, `blocked: true, reason: "starvation_unresolved"`.
- **Roles:** N/A

### TC-RWS-15 (Failure): Daily prep without completing rest — blocked
- **Description:** Attempting daily preparation without completing a full rest does not restore spells or abilities.
- **Suite:** dungeoncrawler-content
- **Expected:** `prepare_daily()` while `rest_complete: false` → `spells_restored: false`, error/block state returned.
- **Roles:** N/A

---

## Notes to PM

1. **Watch duration values (TC-RWS-01):** AC states watch duration is defined by party size but does not enumerate exact values. Dev needs the reference table before implementation; QA automation needs confirmed values to assert correctness. Recommend BA extract from PF2E CRB ch10 "Watches" section.

2. **Con modifier threshold (TC-RWS-04, TC-RWS-08):** Damage onset is `(Con modifier + 1)` days. AC confirms the minimum-1-day edge case (TC-RWS-12) but does not specify maximum (e.g., is there a cap for very high Con?). Automation can assert the formula without a cap; flag if a cap exists.

3. **Security AC exemption confirmed:** No new routes or user-facing input surfaces. All TCs are data-model/logic assertions only.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-rest-watch-starvation

## Gap analysis reference
- DB sections: core/ch10/Resting and Daily Preparations (4 reqs)
- Depends on: dc-cr-conditions ✓, dc-cr-skill-system ✓

---

## Happy Path

### Watch Schedule
- [ ] `[NEW]` Watch duration by party size implemented; all party members share watch duty; minimum watch segments tracked.
- [ ] `[NEW]` Daily preparation sequence: rest → watch → prepare spells/abilities (10 min focus and preparation).

### Starvation and Thirst
- [ ] `[NEW]` Without water: immediate fatigue applied; after (Con modifier + 1) days, 1d4 damage per hour (cannot be healed until thirst quenched).
- [ ] `[NEW]` Without food: immediate fatigue applied; after (Con modifier + 1) days, 1 damage per day (cannot be healed until fed).
- [ ] `[NEW]` Starvation/thirst tracked as environmental hazards; triggers resolve at appropriate intervals.
- [ ] `[NEW]` Healing blocked until underlying condition (starvation/thirst) resolved.

---

## Edge Cases
- [ ] `[NEW]` Con modifier ≤ 0: minimum of 1 day before damage onset.
- [ ] `[NEW]` Simultaneously starving and dehydrated: both damage tracks active independently.

## Failure Modes
- [ ] `[TEST-ONLY]` Healing during active starvation: healing blocked until fed.
- [ ] `[TEST-ONLY]` Daily prep without completing rest: blocked or flagged (no spell restoration).

## Security acceptance criteria
- Security AC exemption: game-mechanic rest and survival logic; no new routes or user-facing input beyond existing exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
