# Suite Activation: dc-cr-encounter-creature-xp-table

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T04:53:22+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-encounter-creature-xp-table"`**  
   This links the test to the living requirements doc at `features/dc-cr-encounter-creature-xp-table/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-encounter-creature-xp-table-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-encounter-creature-xp-table",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-encounter-creature-xp-table"`**  
   Example:
   ```json
   {
     "id": "dc-cr-encounter-creature-xp-table-<route-slug>",
     "feature_id": "dc-cr-encounter-creature-xp-table",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-encounter-creature-xp-table",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-encounter-creature-xp-table — Encounter Building and Creature XP

**QA owner:** qa-dungeoncrawler
**Feature:** dc-cr-encounter-creature-xp-table
**Depends on:** dc-cr-xp-award-system
**KB reference:** none found (first encounter-building test plan in this batch)

---

## Summary

14 TCs (TC-XPT-01–14) covering: encounter threat tier classification (5 tiers), 4-PC baseline + Character Adjustment, creature XP cost table (9 level-delta values −4→+4), out-of-range creature handling, double-XP catch-up rule, hazard XP reference gate, party-size edge cases (1–3 and 5+), and failure modes (creature >+4 no entry / trivial encounter 0 XP). All 14 TCs conditional on dc-cr-xp-award-system (dependency).

---

## Test Cases

### Encounter Threat Tiers (TC-XPT-01–02)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-01 | Threat tier classification: total encounter XP budget maps to correct tier | Data validation | Budget ≤40 → Trivial; 41–60 → Low; 61–80 → Moderate; 81–120 → Severe; 121–160 → Extreme; >160 → beyond Extreme (label TBD by PM) | authenticated | dc-cr-xp-award-system |
| TC-XPT-02 | XP budget baseline is for 4 PCs; Character Adjustment applied for non-4-PC parties | Playwright / encounter builder | 4-PC party: budget = tier value; 3-PC party: budget reduced by 1× Character Adjustment; 5-PC party: budget increased by 1× Character Adjustment | authenticated | dc-cr-xp-award-system |

### Encounter Budget System (TC-XPT-03–06)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-03 | Creature XP cost lookup by level-delta (creature level − party level) returns correct value for all 9 defined rows | Data validation | All 9 table rows validated: −4→10, −3→15, −2→20, −1→30, 0→40, +1→60, +2→80, +3→120, +4→160 | authenticated | dc-cr-xp-award-system |
| TC-XPT-04 | Creature >4 levels below party level treated as trivial (0 XP, no budget cost) | Playwright / encounter builder | Level-delta = −5 or lower: creature contributes 0 XP to encounter budget; no validation error returned | authenticated | dc-cr-xp-award-system |
| TC-XPT-05 | Creature >4 levels above party level: no XP table entry, encounter flagged as "too dangerous" | Playwright / encounter builder | Level-delta = +5 or higher: API returns null or "not_defined" for XP cost; UI displays "too dangerous" warning; encounter can still be saved (GM override) | authenticated | dc-cr-xp-award-system |
| TC-XPT-06 | Party level drives budget calculations; multi-level party uses correct level reference | Playwright / encounter builder | Mixed-level party (e.g., levels 3–5): confirm which level (avg, lowest, highest, or specific field) is used for budget calculation; PM to confirm reference level model | authenticated | dc-cr-xp-award-system |

### Double-XP Catch-Up (TC-XPT-07)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-07 | PC behind party level earns double XP from encounters until caught up to party level | Playwright / encounter | PC at level 3 in level-5 party: XP award for that PC = 2× standard award; once PC reaches level 5: multiplier removed | authenticated | dc-cr-xp-award-system |

### Hazard XP Reference Gate (TC-XPT-08)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-08 | Hazard XP uses Table 10–14 values (dc-cr-hazards feature); not the creature XP table | Data validation | Encounter budget calculation for a hazard uses hazard_xp_table lookup, not creature_xp_table; wrong table lookup returns incorrect value and should fail | authenticated | dc-cr-xp-award-system |

### Party Size Edge Cases (TC-XPT-09–10)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-09 | Party size 1–3: Character Adjustment reduces XP budget for each missing PC below 4 | Playwright / encounter builder | 1-PC party: budget = standard budget − (3 × Character Adjustment); 2-PC: budget − (2 × CA); 3-PC: budget − (1 × CA); result must remain ≥ 0 | authenticated | dc-cr-xp-award-system |
| TC-XPT-10 | Party size 5+: Character Adjustment increases XP budget for each additional PC above 4 | Playwright / encounter builder | 5-PC party: budget + (1 × Character Adjustment); 6-PC: budget + (2 × CA) | authenticated | dc-cr-xp-award-system |

### Additional Encounter Budget TCs (TC-XPT-11–12)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-11 | Multi-creature encounter: total budget = sum of all creature XP costs | Playwright / encounter builder | Encounter with 3 creatures at level-delta 0 (40 XP each): total = 120 XP → Severe tier | authenticated | dc-cr-xp-award-system |
| TC-XPT-12 | Mixed level-delta encounter: each creature's XP cost computed independently then summed | Playwright / encounter builder | Encounter with level −2 (20 XP) + level 0 (40 XP) + level +1 (60 XP): total = 120 XP | authenticated | dc-cr-xp-award-system |

### Failure Modes (TC-XPT-13–14)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-XPT-13 | Creature at level-delta >+4: XP cost lookup returns no entry (null/not_defined), not an error code | Data validation | `GET /api/encounter/xp-cost?delta=5` → `{"xp": null, "status": "not_defined"}` or equivalent; HTTP 200, not 4xx | authenticated | dc-cr-xp-award-system |
| TC-XPT-14 | Trivial encounter (≤40 XP total): XP award to party = 0; this is expected, not an error state | Playwright / encounter | Encounter resolved with ≤40 total XP: post-encounter XP award logged as 0 for all PCs; no error state triggered | authenticated | dc-cr-xp-award-system |

---

## Open PM questions / automation notes

1. **TC-XPT-06 party level reference model**: When PCs are different levels, which level drives the encounter budget (average, minimum, maximum, or a specific "party level" field set by GM)? Automation cannot assert correct XP costs without knowing the reference level model.
2. **TC-XPT-05 "too dangerous" GM override**: Does the encounter builder block saving when a creature >+4 levels is added, or allow it with a warning? AC says "XP values not defined" but doesn't specify whether the encounter is blocked or just flagged.
3. **TC-XPT-01 >Extreme tier**: The AC caps at Extreme (160 XP). PM should confirm label/behavior for encounters exceeding 160 XP budget (e.g., "Epic" or uncapped).
4. **Character Adjustment value**: The AC references "Character Adjustment" but does not define the numeric value. PM should confirm the value (commonly 20 XP per PC in PF2E) so automation can assert exact adjusted budgets.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-encounter-creature-xp-table

## Gap analysis reference
- DB sections: core/ch10/Encounter Building (5 reqs), core/ch10/Experience Points and Advancement (8 reqs partially — encounter-specific rows)
- Depends on: dc-cr-xp-award-system

---

## Happy Path

### Encounter Threat Tiers
- [ ] `[NEW]` Encounter threat tiers implemented: Trivial (≤40 XP), Low (60 XP), Moderate (80 XP), Severe (120 XP), Extreme (160 XP).
- [ ] `[NEW]` XP budget baseline is for 4 PCs; Character Adjustment value used for additional/missing PCs.

### Encounter Budget System
- [ ] `[NEW]` Encounter budget system built using creature XP cost table (relative level vs. party level → XP cost).
- [ ] `[NEW]` Creatures > 4 levels below party are trivial; creatures > 4 levels above are too dangerous (XP values not defined).
- [ ] `[NEW]` Party level drives all encounter budget calculations; level-variance handling supported.
- [ ] `[NEW]` PCs behind party level earn double XP until caught up.

### Creature XP Table
- [ ] `[NEW]` Creature XP by level-delta implemented:
  - Party Level –4: 10 XP
  - Party Level –3: 15 XP
  - Party Level –2: 20 XP
  - Party Level –1: 30 XP
  - Party Level: 40 XP
  - Party Level +1: 60 XP
  - Party Level +2: 80 XP
  - Party Level +3: 120 XP
  - Party Level +4: 160 XP
- [ ] `[NEW]` Hazard XP uses Table 10–14 (per dc-cr-hazards feature).

## Edge Cases
- [ ] `[NEW]` Party size of 1–3: Character Adjustment reduces XP budget proportionally.
- [ ] `[NEW]` Party size 5+: Character Adjustment increases budget.

## Failure Modes
- [ ] `[TEST-ONLY]` Creature level > party +4: no XP entry returned (not an error).
- [ ] `[TEST-ONLY]` Trivial encounter XP award: 0 XP (not an error state).

## Security acceptance criteria
- Security AC exemption: game-mechanic XP calculation; no new routes or user-facing input beyond existing encounter phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
