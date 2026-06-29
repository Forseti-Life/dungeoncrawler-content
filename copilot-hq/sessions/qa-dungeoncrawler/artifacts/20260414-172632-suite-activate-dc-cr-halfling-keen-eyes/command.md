- Status: done
- Completed: 2026-04-14T17:59:56Z

# Suite Activation: dc-cr-halfling-keen-eyes

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-halfling-keen-eyes"`**  
   This links the test to the living requirements doc at `features/dc-cr-halfling-keen-eyes/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-halfling-keen-eyes-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-halfling-keen-eyes",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-halfling-keen-eyes"`**  
   Example:
   ```json
   {
     "id": "dc-cr-halfling-keen-eyes-<route-slug>",
     "feature_id": "dc-cr-halfling-keen-eyes",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-halfling-keen-eyes",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-halfling-keen-eyes

## Coverage summary
- AC items: 8 (4 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-HKE-01-05)
- Suites: playwright (encounter, visibility, character sheet)
- Security: AC exemption granted (existing combat routes only)

---

## TC-HKE-01 — Keen Eyes auto-granted
- Description: Halfling ancestry automatically grants Keen Eyes.
- Suite: playwright/character-sheet
- Expected: Keen Eyes passive appears without extra choice
- AC: Automatic grant

## TC-HKE-02 — Seek bonus within 30 feet
- Description: Halfling uses Seek on a hidden target within 30 feet.
- Suite: playwright/encounter
- Expected: Seek check includes +2 circumstance bonus
- AC: Seek bonus

## TC-HKE-03 — Concealed target flat-check reduced
- Description: Halfling attacks a concealed target.
- Suite: playwright/encounter
- Expected: flat-check DC = 3
- AC: Flat-check reduction-1

## TC-HKE-04 — Hidden target flat-check reduced
- Description: Halfling attacks a hidden target.
- Suite: playwright/encounter
- Expected: flat-check DC = 9
- AC: Flat-check reduction-2

## TC-HKE-05 — Defaults preserved outside scope
- Description: Test non-halfling or target beyond 30 feet.
- Suite: playwright/encounter
- Expected: default modifiers/DCs remain in place
- AC: Edge Cases-1, Edge Cases-2, Failure Modes-1, Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-halfling-keen-eyes

## Gap analysis reference
- DB sections: core/ch02 (Halfling ancestry traits)
- Depends on: dc-cr-halfling-ancestry

---

## Happy Path

### Seek bonus
- [ ] `[NEW]` Halfling characters gain a +2 circumstance bonus on Seek checks against hidden or undetected creatures within 30 feet.

### Flat-check reduction
- [ ] `[NEW]` Against concealed targets, the targeting flat-check DC is reduced from 5 to 3 for halflings.
- [ ] `[NEW]` Against hidden targets, the targeting flat-check DC is reduced from 11 to 9 for halflings.

### Automatic grant
- [ ] `[NEW]` Keen Eyes is granted automatically as part of the halfling ancestry and does not require a separate selection.

---

## Edge Cases
- [ ] `[NEW]` The Seek bonus does not apply beyond 30 feet.
- [ ] `[NEW]` Flat-check reductions apply only to concealed/hidden targets, not to undetected or observed targets.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-halfling characters use the default flat-check DCs.
- [ ] `[TEST-ONLY]` Halflings do not get the Seek bonus when the target is outside 30 feet.

## Security acceptance criteria
- Security AC exemption: passive ancestry trait only; no new route surface
