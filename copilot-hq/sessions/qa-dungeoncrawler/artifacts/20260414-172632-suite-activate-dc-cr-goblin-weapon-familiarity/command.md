- Status: done
- Completed: 2026-04-14T17:48:24Z

# Suite Activation: dc-cr-goblin-weapon-familiarity

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-goblin-weapon-familiarity"`**  
   This links the test to the living requirements doc at `features/dc-cr-goblin-weapon-familiarity/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-goblin-weapon-familiarity-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-goblin-weapon-familiarity",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-goblin-weapon-familiarity"`**  
   Example:
   ```json
   {
     "id": "dc-cr-goblin-weapon-familiarity-<route-slug>",
     "feature_id": "dc-cr-goblin-weapon-familiarity",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-goblin-weapon-familiarity",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-goblin-weapon-familiarity

## Coverage summary
- AC items: 10 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 6 (TC-GWF-01-06)
- Suites: playwright (character creation, inventory, feat progression)
- Security: AC exemption granted (existing character routes only)

---

## TC-GWF-01 — Goblin feat availability
- Description: Goblin Weapon Familiarity appears in the Goblin level-1 ancestry feat list.
- Suite: playwright/character-creation
- Expected: goblin feat picker includes `dc-cr-goblin-weapon-familiarity`
- AC: Availability

## TC-GWF-02 — Dogslicer and horsechopper proficiency granted
- Description: Selecting the feat grants trained proficiency with both signature goblin weapons.
- Suite: playwright/character-creation
- Expected: character.weapon_proficiencies includes `dogslicer=trained`, `horsechopper=trained`
- AC: Granted proficiencies-1, Granted proficiencies-2

## TC-GWF-03 — Uncommon goblin weapons unlocked
- Description: Uncommon goblin weapons become selectable after the feat is applied.
- Suite: playwright/inventory
- Expected: goblin-only uncommon weapons are visible/valid for the character
- AC: Weapon access and proficiency remap-1

## TC-GWF-04 — Proficiency remap applied
- Description: Goblin weapon proficiency tiers are remapped down by one step for proficiency checks.
- Suite: playwright/inventory
- Expected: martial goblin weapons resolve through simple proficiency; advanced goblin weapons resolve through martial proficiency
- AC: Weapon access and proficiency remap-2, Weapon access and proficiency remap-3, Edge Cases-1

## TC-GWF-05 — Non-goblin blocked from feat
- Description: Non-goblin character cannot select Goblin Weapon Familiarity.
- Suite: playwright/character-creation
- Expected: feat is hidden or rejected with prerequisite failure
- AC: Failure Modes-1

## TC-GWF-06 — Goblin Weapon Frenzy prerequisite opens
- Description: Goblin Weapon Familiarity satisfies the prerequisite gate for Goblin Weapon Frenzy.
- Suite: playwright/feat-progression
- Expected: Goblin Weapon Frenzy becomes eligible when other prerequisites are met
- AC: Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-goblin-weapon-familiarity

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Goblin Weapon Familiarity is selectable as a Goblin ancestry feat at level 1.

### Granted proficiencies
- [ ] `[NEW]` Selecting the feat grants trained proficiency with `dogslicer`.
- [ ] `[NEW]` Selecting the feat grants trained proficiency with `horsechopper`.

### Weapon access and proficiency remap
- [ ] `[NEW]` Character gains access to uncommon goblin weapons once the feat is selected.
- [ ] `[NEW]` Martial goblin weapons count as simple weapons for proficiency calculation for this character.
- [ ] `[NEW]` Advanced goblin weapons count as martial weapons for proficiency calculation for this character.

---

## Edge Cases
- [ ] `[NEW]` The proficiency remap applies only to goblin-tagged weapons; non-goblin weapon categories are unchanged.
- [ ] `[NEW]` The feat does not auto-equip any weapon; it only changes access and proficiency handling.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-goblin characters cannot select Goblin Weapon Familiarity.
- [ ] `[TEST-ONLY]` Goblin Weapon Frenzy remains unavailable unless Goblin Weapon Familiarity is present.

## Security acceptance criteria
- Security AC exemption: ancestry feat data/update only; no new route surface beyond existing character flows
