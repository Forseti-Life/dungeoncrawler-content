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
