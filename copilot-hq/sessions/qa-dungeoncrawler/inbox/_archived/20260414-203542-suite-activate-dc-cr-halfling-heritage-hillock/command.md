# Suite Activation: dc-cr-halfling-heritage-hillock

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-halfling-heritage-hillock"`**  
   This links the test to the living requirements doc at `features/dc-cr-halfling-heritage-hillock/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-halfling-heritage-hillock-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-halfling-heritage-hillock",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-halfling-heritage-hillock"`**  
   Example:
   ```json
   {
     "id": "dc-cr-halfling-heritage-hillock-<route-slug>",
     "feature_id": "dc-cr-halfling-heritage-hillock",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-halfling-heritage-hillock",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-halfling-heritage-hillock

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-HHI-01-05)
- Suites: playwright (character creation, rest, medicine/healing)
- Security: AC exemption granted (existing healing routes only)

---

## TC-HHI-01 — Heritage selectable
- Description: Hillock Halfling appears in the halfling heritage picker.
- Suite: playwright/character-creation
- Expected: heritage list includes Hillock Halfling
- AC: Heritage selection

## TC-HHI-02 — Overnight rest grants level bonus
- Description: Character completes overnight rest.
- Suite: playwright/rest
- Expected: HP regained includes base recovery + character level
- AC: Overnight recovery bonus, Edge Cases-2

## TC-HHI-03 — Treat Wounds snack rider adds level
- Description: Another character Treats Wounds on the patient and the snack rider is used.
- Suite: playwright/medicine
- Expected: healing total includes +character level
- AC: Treat Wounds snack rider

## TC-HHI-04 — Bonus not applied outside allowed sources
- Description: Apply a non-Treat-Wounds healing source.
- Suite: playwright/medicine
- Expected: no snack rider or heritage bonus added
- AC: Edge Cases-1, Failure Modes-1

## TC-HHI-05 — Snack rider cannot double-apply
- Description: Attempt to reapply the rider to the same Treat Wounds resolution.
- Suite: playwright/medicine
- Expected: only one heritage bonus instance is counted
- AC: Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-halfling-heritage-hillock

## Gap analysis reference
- DB sections: core/ch02 (Halfling heritages)
- Depends on: dc-cr-halfling-ancestry, dc-cr-heritage-system, dc-cr-skills-medicine-actions

---

## Happy Path

### Heritage selection
- [ ] `[NEW]` Hillock Halfling is selectable as a halfling heritage at character creation.

### Overnight recovery bonus
- [ ] `[NEW]` On overnight rest, a Hillock Halfling regains additional HP equal to character level.

### Treat Wounds snack rider
- [ ] `[NEW]` When another character Treats Wounds on a Hillock Halfling, the patient can trigger the snack rider to add HP equal to character level.

---

## Edge Cases
- [ ] `[NEW]` The snack rider applies only to Treat Wounds and not to other healing sources.
- [ ] `[NEW]` The overnight bonus stacks on top of the normal overnight recovery amount.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without Hillock Halfling receive no bonus healing.
- [ ] `[TEST-ONLY]` The snack rider cannot be applied multiple times to the same Treat Wounds result.

## Security acceptance criteria
- Security AC exemption: passive heritage/healing adjustment only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
