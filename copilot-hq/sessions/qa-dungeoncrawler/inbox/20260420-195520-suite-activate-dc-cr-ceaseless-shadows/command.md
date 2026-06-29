# Suite Activation: dc-cr-ceaseless-shadows

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-20T19:55:20+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-ceaseless-shadows"`**  
   This links the test to the living requirements doc at `features/dc-cr-ceaseless-shadows/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-ceaseless-shadows-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-ceaseless-shadows",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-ceaseless-shadows"`**  
   Example:
   ```json
   {
     "id": "dc-cr-ceaseless-shadows-<route-slug>",
     "feature_id": "dc-cr-ceaseless-shadows",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-ceaseless-shadows",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-ceaseless-shadows

- Feature: Ceaseless Shadows (Halfling Feat 13)
- Module: dungeoncrawler_content
- Agent: qa-dungeoncrawler
- Target release: 20260412-dungeoncrawler-release-s
- Created: 2026-04-20

---

## Test Cases

### Feat Availability

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-01 | A halfling character with Distracting Shadows views feat selection at level 13. | Ceaseless Shadows appears as a selectable feat option. | PASS/FAIL |
| TC-02 | A halfling character without Distracting Shadows attempts to select Ceaseless Shadows. | Ceaseless Shadows is not selectable; prerequisite validation blocks selection. | PASS/FAIL |

---

### Hide/Sneak Without Cover or Concealment

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-03 | A halfling with Ceaseless Shadows attempts the Hide action in an open area with no cover or concealment. | Hide action is available and executes without error. | PASS/FAIL |
| TC-04 | A halfling with Ceaseless Shadows attempts the Sneak action in an open area with no cover or concealment. | Sneak action is available and executes without error. | PASS/FAIL |
| TC-05 | A character without Ceaseless Shadows (any ancestry) attempts Hide with no cover or concealment. | Hide action is blocked; cover/concealment requirement is enforced. | PASS/FAIL |
| TC-06 | A character without Ceaseless Shadows (any ancestry) attempts Sneak with no cover or concealment. | Sneak action is blocked; cover/concealment requirement is enforced. | PASS/FAIL |

---

### Upgraded Creature Cover

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-07 | A halfling with Ceaseless Shadows is adjacent to creatures that would normally grant lesser cover. | Cover is upgraded to full cover; halfling may Take Cover against those creatures. | PASS/FAIL |
| TC-08 | A halfling with Ceaseless Shadows is adjacent to creatures that already grant full cover. | Cover is upgraded to greater cover. | PASS/FAIL |
| TC-09 | A character without Ceaseless Shadows is adjacent to creatures that would grant lesser cover. | Cover remains lesser cover; no upgrade occurs. | PASS/FAIL |
| TC-10 | A character without Ceaseless Shadows is adjacent to creatures that grant full cover. | Cover remains full cover; no upgrade to greater cover. | PASS/FAIL |

---

### Edge Cases

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-11 | A halfling has Distracting Shadows but not Ceaseless Shadows; attempts Hide/Sneak with no cover or concealment. | Hide/Sneak require cover or concealment; actions are blocked. | PASS/FAIL |
| TC-12 | A halfling with Ceaseless Shadows is behind terrain cover (not creature cover). | Terrain cover value is unaffected — no upgrade is applied to terrain-sourced cover. | PASS/FAIL |

---

### Failure Modes

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-13 | Player attempts to select Ceaseless Shadows on a character that lacks Distracting Shadows. | Selection is blocked with a prerequisite-missing error. | PASS/FAIL |
| TC-14 | A non-halfling character (e.g., Human, Elf, Dwarf) attempts to select Ceaseless Shadows. | Selection is blocked; feat is restricted to Halfling ancestry. | PASS/FAIL |

---

## Coverage Map

| AC Item | Test Case(s) |
|---------|-------------|
| [NEW] Ceaseless Shadows appears as selectable Halfling Feat 13 with Distracting Shadows | TC-01 |
| [NEW] Ceaseless Shadows requires Distracting Shadows prerequisite | TC-02 |
| [NEW] Hide action usable without cover/concealment (with feat) | TC-03 |
| [NEW] Sneak action usable without cover/concealment (with feat) | TC-04 |
| [NEW] Characters without feat still require cover/concealment for Hide | TC-05 |
| [NEW] Characters without feat still require cover/concealment for Sneak | TC-06 |
| [NEW] Creatures grant upgraded cover: lesser → full (+ Take Cover) | TC-07 |
| [NEW] Creatures grant upgraded cover: full → greater | TC-08 |
| [NEW] Cover upgrade does not apply to characters without feat | TC-09, TC-10 |
| [NEW] Halfling with Distracting Shadows but not Ceaseless Shadows still requires cover | TC-11 |
| [NEW] Creature-cover upgrade does not affect terrain cover | TC-12 |
| [TEST-ONLY] Selecting feat without Distracting Shadows is blocked | TC-13 |
| [TEST-ONLY] Non-halfling cannot select or benefit from feat | TC-14 |

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-ceaseless-shadows

## Gap analysis reference
- DB sections: PF2E Core Rulebook (Fourth Printing), Halfling Ancestry Feats (Feat 13)
- Depends on: dc-cr-halfling-ancestry, dc-cr-ancestry-system, dc-cr-halfling-keen-eyes

---

## Happy Path

### Feat availability
- [ ] `[NEW]` Ceaseless Shadows appears as a selectable Halfling Feat 13 when the character is a halfling with Distracting Shadows.
- [ ] `[NEW]` Ceaseless Shadows requires and validates the Distracting Shadows prerequisite — characters without it cannot select this feat.

### Hide/Sneak without cover or concealment
- [ ] `[NEW]` A halfling with Ceaseless Shadows can use the Hide action without requiring cover or concealment.
- [ ] `[NEW]` A halfling with Ceaseless Shadows can use the Sneak action without requiring cover or concealment.
- [ ] `[NEW]` Characters without Ceaseless Shadows still require cover or concealment for Hide/Sneak (no regression).

### Upgraded creature cover
- [ ] `[NEW]` When creatures would grant lesser cover to the halfling, they instead grant full cover, and the halfling may Take Cover against those creatures.
- [ ] `[NEW]` When creatures already grant full cover to the halfling, that cover is upgraded to greater cover.
- [ ] `[NEW]` The upgraded cover tiers (lesser→full, full→greater) do not apply to characters without Ceaseless Shadows.

---

## Edge Cases
- [ ] `[NEW]` If a halfling has Distracting Shadows but not Ceaseless Shadows, Hide/Sneak still require cover or concealment.
- [ ] `[NEW]` Ceaseless Shadows creature-cover upgrade applies only to creature-granted cover (terrain cover is unaffected).

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to select Ceaseless Shadows without Distracting Shadows is blocked.
- [ ] `[TEST-ONLY]` Non-halfling characters cannot select or benefit from Ceaseless Shadows.

## Security acceptance criteria
- Security AC exemption: feat data and character-mechanic logic only; no new route surface beyond existing character/feat flows.
- Status: pending
