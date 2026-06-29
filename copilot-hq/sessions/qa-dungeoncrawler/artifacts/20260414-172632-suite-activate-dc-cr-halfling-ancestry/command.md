- Status: done
- Completed: 2026-04-14T17:57:11Z

# Suite Activation: dc-cr-halfling-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T17:26:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-halfling-ancestry"`**  
   This links the test to the living requirements doc at `features/dc-cr-halfling-ancestry/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-halfling-ancestry-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-halfling-ancestry",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-halfling-ancestry"`**  
   Example:
   ```json
   {
     "id": "dc-cr-halfling-ancestry-<route-slug>",
     "feature_id": "dc-cr-halfling-ancestry",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-halfling-ancestry",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-halfling-ancestry

## Coverage summary
- AC items: 10 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 6 (TC-HAL-01-06)
- Suites: playwright (character creation, character sheet)
- Security: AC exemption granted (existing character routes only)

---

## TC-HAL-01 — Halfling ancestry selectable
- Description: Halfling appears in the ancestry picker.
- Suite: playwright/character-creation
- Expected: ancestry list includes Halfling
- AC: Character creation availability

## TC-HAL-02 — Core ancestry stats applied
- Description: Selecting Halfling assigns HP, size, speed, and boosts.
- Suite: playwright/character-creation
- Expected: character ancestry data = {hp: 6, size: Small, speed: 25, boosts include Dex and Wis}
- AC: Core ancestry stats-1, Core ancestry stats-2

## TC-HAL-03 — Halfling Luck granted
- Description: Character gains the baseline halfling luck ancestry benefit.
- Suite: playwright/character-sheet
- Expected: halfling luck passive appears on character summary
- AC: Automatic ancestry traits-1

## TC-HAL-04 — Keen Eyes granted automatically
- Description: Halfling selection auto-grants Keen Eyes.
- Suite: playwright/character-sheet
- Expected: keen-eyes trait/effect present with no extra player selection
- AC: Automatic ancestry traits-2, Edge Cases-1

## TC-HAL-05 — Heritage and feat tree unlocks
- Description: Halfling-specific heritages and feats appear after ancestry selection.
- Suite: playwright/character-creation
- Expected: halfling heritage list and feat list are visible and valid
- AC: Ancestry integration-1

## TC-HAL-06 — Non-halfling access blocked
- Description: Non-halfling character tries to access halfling-only options.
- Suite: playwright/character-creation
- Expected: halfling-only heritages/feats rejected by server
- AC: Failure Modes-1, Failure Modes-2, Edge Cases-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-halfling-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Halfling ancestry, halfling heritages, halfling ancestry feats)
- Depends on: dc-cr-ancestry-system, dc-cr-halfling-keen-eyes

---

## Happy Path

### Character creation availability
- [ ] `[NEW]` Halfling appears as a selectable ancestry in character creation.

### Core ancestry stats
- [ ] `[NEW]` Halfling ancestry grants 6 HP, Small size, and 25-foot Speed.
- [ ] `[NEW]` Halfling ancestry grants Dexterity and Wisdom boosts plus the free-boost path supported by the build rules.

### Automatic ancestry traits
- [ ] `[NEW]` Halfling characters gain Halfling Luck.
- [ ] `[NEW]` Halfling characters gain Keen Eyes automatically.

### Ancestry integration
- [ ] `[NEW]` Halfling heritages and ancestry feats become available when Halfling is selected.
- [ ] `[NEW]` Halfling ancestry persists on the character sheet and downstream ancestry logic.

---

## Edge Cases
- [ ] `[NEW]` Keen Eyes is granted automatically and is not presented as an optional feat choice.
- [ ] `[NEW]` Switching away from Halfling ancestry removes halfling-only passive ancestry benefits on recalculation.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid ancestry-stat submissions are rejected and replaced with canonical halfling values.
- [ ] `[TEST-ONLY]` Halfling-only heritages and feats are blocked for non-halfling characters.

## Security acceptance criteria
- Security AC exemption: ancestry data modeling only; no new route surface beyond existing character flows
