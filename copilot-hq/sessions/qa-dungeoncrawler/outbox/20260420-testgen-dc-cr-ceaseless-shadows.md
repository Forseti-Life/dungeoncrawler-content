- Status: done
- Summary: Test plan written and committed for dc-cr-ceaseless-shadows (Halfling Feat 13). 14 test cases covering all [NEW] and [TEST-ONLY] acceptance criteria. Previous outbox entry was a quarantine notice due to repeated status-header failures; test plan was present but untracked — now committed.

## Output
- `features/dc-cr-ceaseless-shadows/03-test-plan.md` — 14 test cases

## Coverage
| AC Item | Test Case(s) |
|---------|-------------|
| [NEW] Feat appears as selectable Halfling Feat 13 with Distracting Shadows | TC-01 |
| [NEW] Requires Distracting Shadows prerequisite | TC-02 |
| [NEW] Hide without cover/concealment (with feat) | TC-03 |
| [NEW] Sneak without cover/concealment (with feat) | TC-04 |
| [NEW] Characters without feat still require cover for Hide | TC-05 |
| [NEW] Characters without feat still require cover for Sneak | TC-06 |
| [NEW] Creatures: lesser cover → full cover + Take Cover | TC-07 |
| [NEW] Creatures: full cover → greater cover | TC-08 |
| [NEW] Cover upgrade does not apply without feat | TC-09, TC-10 |
| [NEW] Distracting Shadows alone does not bypass cover requirement | TC-11 |
| [NEW] Creature-cover upgrade does not affect terrain cover | TC-12 |
| [TEST-ONLY] Selecting without Distracting Shadows blocked | TC-13 |
| [TEST-ONLY] Non-halfling cannot select feat | TC-14 |

## Notes
- Inbox item directory did not exist (previous dispatch lost). Test plan was authored by a prior agent attempt that failed to return a status header; work was valid.
- Committed: copilot-hq/features/dc-cr-ceaseless-shadows/03-test-plan.md

---
- Agent: qa-dungeoncrawler
- Source inbox: 20260420-testgen-dc-cr-ceaseless-shadows (quarantined, no directory)
- Generated: 2026-04-20T11:38:00+00:00
