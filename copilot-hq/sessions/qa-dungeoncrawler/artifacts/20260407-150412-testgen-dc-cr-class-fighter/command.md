# Test Plan Design: dc-cr-class-fighter

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:04:12+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-class-fighter/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-class-fighter "<brief summary>"
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

# Acceptance Criteria: dc-cr-class-fighter

## Gap analysis reference
- DB sections: core/ch03/Fighter (REQs 1172–1250+)
- Track B: no existing FighterService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-equipment-system (in-progress Release B)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Fighter exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Fighter HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Expert Fortitude and Expert Reflex, Trained Will; Expert simple and martial weapons + unarmed attacks (unique to Fighter at level 1); Trained advanced weapons; Trained all armor categories (light, medium, heavy).

### Attack of Opportunity (Level 1)
- [ ] `[NEW]` Fighter gains Attack of Opportunity at level 1 as a class feature (reaction; trigger: creature in reach uses manipulate/moves/ranged attacks/leaves a square; make a melee Strike).

### Class Feature Unlocks by Level
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20; skill feats at every even level; general feats at 3, 7, 11, 15, 19; ancestry feats at 5, 9, 13, 17.

### Key Combat Trait Rules
- [ ] `[NEW]` Press trait: can only be used when under MAP (not first action of turn); cannot be Readied; failure on a press action does not apply crit-fail effects.
- [ ] `[NEW]` Stance trait: only one stance active at a time per encounter; 1-round cooldown on stance actions; stances end on knockout/violation/end of encounter.
- [ ] `[NEW]` Flourish trait: only one flourish action per turn enforced.

### Feat Progression
- [ ] `[NEW]` Fighter gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).

---

## Edge Cases

- [ ] `[NEW]` Press actions blocked when no prior attack has been made this turn (not under MAP).
- [ ] `[NEW]` Stance change: previous stance immediately ends; new stance subject to 1-round cooldown.
- [ ] `[NEW]` Attack of Opportunity: only once per triggering event; does not count toward MAP.
- [ ] `[NEW]` Power Attack: counts as 2 MAP attacks; +1 extra damage die (becomes +2 at 10th, +3 at 18th); correctly scales.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Attempting a press action before any other attack this turn: blocked.
- [ ] `[TEST-ONLY]` Two stances simultaneously: not permitted.
- [ ] `[TEST-ONLY]` Flourish limit: second flourish in same turn blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
