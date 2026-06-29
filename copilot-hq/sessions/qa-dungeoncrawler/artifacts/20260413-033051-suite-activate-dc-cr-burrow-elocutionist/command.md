- Status: done
- Completed: 2026-04-13T05:32:54Z

# Suite Activation: dc-cr-burrow-elocutionist

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-13T03:30:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-burrow-elocutionist"`**  
   This links the test to the living requirements doc at `features/dc-cr-burrow-elocutionist/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-burrow-elocutionist-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-burrow-elocutionist",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-burrow-elocutionist"`**  
   Example:
   ```json
   {
     "id": "dc-cr-burrow-elocutionist-<route-slug>",
     "feature_id": "dc-cr-burrow-elocutionist",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-burrow-elocutionist",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-burrow-elocutionist

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 4 (TC-BEL-01-04)
- Suites: playwright (character creation, exploration/dialogue)
- Security: AC exemption granted (existing interaction routes only)

---

## TC-BEL-01 — Feat availability
- Description: Burrow Elocutionist appears in the Gnome ancestry feat list.
- Suite: playwright/character-creation
- Expected: feat picker includes `dc-cr-burrow-elocutionist`
- AC: Availability

## TC-BEL-02 — Burrowing creature dialogue enabled
- Description: Character attempts to converse with a burrowing creature.
- Suite: playwright/exploration
- Expected: dialogue option is available and responses are understandable
- AC: Communication effect-1, Communication effect-2

## TC-BEL-03 — Non-burrowing creature unaffected
- Description: Character tries the same interaction with a non-burrowing creature.
- Suite: playwright/exploration
- Expected: no special communication channel is granted
- AC: Edge Cases-1, Failure Modes-1

## TC-BEL-04 — Character without feat blocked
- Description: Non-qualified character attempts burrow-language interaction.
- Suite: playwright/exploration
- Expected: interaction not available
- AC: Failure Modes-2, Edge Cases-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-burrow-elocutionist

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Burrow Elocutionist is selectable as a Gnome ancestry feat at level 1.

### Communication effect
- [ ] `[NEW]` Character can communicate with burrowing creatures using the feat's special language effect.
- [ ] `[NEW]` Burrowing creatures can answer questions in a way the character understands.

---

## Edge Cases
- [ ] `[NEW]` The effect applies only to creatures tagged as burrowing creatures.
- [ ] `[NEW]` The feat grants communication, not general language fluency with all animals.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-burrowing creatures do not gain a dialogue channel from this feat.
- [ ] `[TEST-ONLY]` Characters without the feat cannot use the special burrow-language interaction.

## Security acceptance criteria
- Security AC exemption: interaction capability flag only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
