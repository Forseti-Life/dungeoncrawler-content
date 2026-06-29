# Test Plan Design: dc-cr-class-ranger

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:13:10+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-ranger/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-ranger "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: dc-cr-class-ranger

## Gap analysis reference
- DB sections: core/ch03/Ranger (REQs 1324–1391)
- Track B: no existing RangerService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Ranger exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Ranger HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Trained Fortitude, Reflex, and Will; Trained simple and martial weapons; Trained light and medium armor.

### Hunt Prey (Level 1)
- [ ] `[NEW]` Hunt Prey (1-action, free action with certain feats) designates one creature as hunted prey; changing prey replaces the previous designation.
- [ ] `[NEW]` Only one creature can be prey at a time (without Double Prey feat).

### Hunter's Edge (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects one Hunter's Edge: Flurry, Precision, or Outwit.
- [ ] `[NEW]` Flurry: MAP with attacks against hunted prey is –3/–6 (–2/–4 with agile weapons) instead of –5/–10.
- [ ] `[NEW]` Precision: First hit per round against hunted prey deals +1d8 precision damage (scales: 2d8 at level 11, 3d8 at level 19).
- [ ] `[NEW]` Outwit: +2 circumstance bonus to Deception, Intimidation, Stealth, and Recall Knowledge vs hunted prey; +1 circumstance to AC vs hunted prey's attacks.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Flurry edge: MAP reduction only applies when attacking the designated prey; normal MAP vs other targets.
- [ ] `[NEW]` Precision edge: +1d8 applies only to FIRST hit per round vs prey; subsequent hits in same round do not get bonus.
- [ ] `[NEW]` Double Prey feat: correctly allows two simultaneous prey designations when taken.
- [ ] `[NEW]` Hunted Shot (2 attacks, once/round): both attacks count for MAP; damage combines for resistance calculation only if both hit same target.
- [ ] `[NEW]` Warden's Boon: grants prey benefits to one ally for their next turn only (not full encounter).

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Precision bonus applies only to first attack hit per round vs prey; later hits don't receive bonus.
- [ ] `[TEST-ONLY]` Hunt Prey resets prey: previous prey designation removed when new prey hunted.
- [ ] `[TEST-ONLY]` Flurry MAP: cannot be lower than –2/–4 (agile) or –3/–6 (non-agile) — no further reduction.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
