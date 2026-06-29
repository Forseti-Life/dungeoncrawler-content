- Status: done
- Completed: 2026-04-13T05:27:47Z

# Suite Activation: dc-cr-animal-accomplice

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-13T03:30:40+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-animal-accomplice"`**  
   This links the test to the living requirements doc at `features/dc-cr-animal-accomplice/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-animal-accomplice-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-animal-accomplice",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-animal-accomplice"`**  
   Example:
   ```json
   {
     "id": "dc-cr-animal-accomplice-<route-slug>",
     "feature_id": "dc-cr-animal-accomplice",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-animal-accomplice",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-animal-accomplice

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 6 (TC-AAC-01–06)
- Suites: playwright (character creation, familiar management)
- Security: AC exemption granted (existing familiar routes only)

---

## TC-AAC-01 — Feat availability
- Description: Animal Accomplice appears in the Gnome feat list.
- Suite: playwright/character-creation
- Expected: feat picker includes `dc-cr-animal-accomplice`
- AC: Availability

## TC-AAC-02 — Familiar granted on feat selection
- Description: Character selects the feat during creation or progression.
- Suite: playwright/character-creation
- Expected: familiar record is created using standard familiar rules
- AC: Familiar grant-1

## TC-AAC-03 — Familiar type chosen from valid catalog
- Description: Player selects a familiar type.
- Suite: playwright/familiar
- Expected: valid familiar types are accepted; burrow-speed options may be highlighted
- AC: Familiar grant-2, Edge Cases-1

## TC-AAC-04 — Non-spellcaster still receives familiar
- Description: Gnome martial/non-caster takes the feat.
- Suite: playwright/character-creation
- Expected: familiar grant succeeds despite no spellcasting class
- AC: Edge Cases-2

## TC-AAC-05 — Invalid familiar type rejected
- Description: Submit a familiar type not in the catalog.
- Suite: playwright/familiar
- Expected: server rejects invalid familiar assignment
- AC: Failure Modes-2

## TC-AAC-06 — No familiar granted without feat
- Description: Gnome character who has NOT taken Animal Accomplice completes character creation.
- Suite: playwright/character-creation
- Expected: no familiar record is created via this feat path
- AC: Failure Modes-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-animal-accomplice

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-familiar

---

## Happy Path

### Availability
- [ ] `[NEW]` Animal Accomplice is selectable as a Gnome feat 1.

### Familiar grant
- [ ] `[NEW]` Selecting the feat grants a familiar using the standard familiar rules.
- [ ] `[NEW]` The familiar can be chosen from the normal familiar type catalog.

---

## Edge Cases
- [ ] `[NEW]` Burrow-speed animals may be recommended in UI copy but are not mandatory.
- [ ] `[NEW]` Non-spellcasting gnome characters can still receive the familiar from this feat.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without the feat do not gain a familiar through this path.
- [ ] `[TEST-ONLY]` Invalid familiar types are rejected by the selection flow.

## Security acceptance criteria
- Security AC exemption: familiar grant/configuration only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
