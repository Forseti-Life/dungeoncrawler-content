- Status: done
- Completed: 2026-04-13T04:25:43Z

# Suite Activation: dc-cr-gnome-weapon-familiarity

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-13T00:39:59+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-weapon-familiarity"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-weapon-familiarity/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-weapon-familiarity-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-weapon-familiarity",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-weapon-familiarity"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-weapon-familiarity-<route-slug>",
     "feature_id": "dc-cr-gnome-weapon-familiarity",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-weapon-familiarity",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-weapon-familiarity

## Coverage summary
- AC items: 8 (5 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GWFM-01-05)
- Suites: playwright (character creation, inventory, feat progression)
- Security: AC exemption granted (existing feat/inventory routes only)

---

## TC-GWFM-01 — Feat availability
- Description: Gnome Weapon Familiarity appears in the Gnome feat list.
- Suite: playwright/character-creation
- Expected: feat picker includes `dc-cr-gnome-weapon-familiarity`
- AC: Availability

## TC-GWFM-02 — Glaive and kukri proficiency granted
- Description: Selecting the feat grants trained proficiency with both named weapons.
- Suite: playwright/character-creation
- Expected: weapon proficiencies include glaive and kukri at trained
- AC: Granted proficiencies-1, Granted proficiencies-2

## TC-GWFM-03 — Uncommon gnome weapons unlocked
- Description: Character gains access to uncommon gnome weapons.
- Suite: playwright/inventory
- Expected: uncommon gnome weapons become visible/valid for the character
- AC: Granted proficiencies-3

## TC-GWFM-04 — Martial gnome weapons remapped
- Description: Proficiency resolver treats martial gnome weapons as simple.
- Suite: playwright/inventory
- Expected: proficiency math uses simple-weapon proficiency tier for martial gnome weapons
- AC: Proficiency remap, Edge Cases-1

## TC-GWFM-05 — Downstream feat prerequisite opened
- Description: Gnome Weapon Specialist/Expertise unlock only after this feat is present.
- Suite: playwright/feat-progression
- Expected: downstream feat gates respect the prerequisite
- AC: Failure Modes-2, Failure Modes-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-weapon-familiarity

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Familiarity is selectable as a Gnome feat 1.

### Granted proficiencies
- [ ] `[NEW]` Selecting the feat grants trained proficiency with glaive.
- [ ] `[NEW]` Selecting the feat grants trained proficiency with kukri.
- [ ] `[NEW]` Selecting the feat grants access to uncommon gnome weapons.

### Proficiency remap
- [ ] `[NEW]` Martial gnome weapons count as simple weapons for proficiency calculation.

---

## Edge Cases
- [ ] `[NEW]` The remap applies only to gnome-tagged martial weapons.
- [ ] `[NEW]` The feat does not automatically equip any weapons.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-gnome characters cannot select the feat.
- [ ] `[TEST-ONLY]` Gnome Weapon Specialist and Gnome Weapon Expertise remain gated until Gnome Weapon Familiarity is present.

## Security acceptance criteria
- Security AC exemption: feat + proficiency data only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
