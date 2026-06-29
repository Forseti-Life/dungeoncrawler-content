# Test Plan Design: dc-cr-xp-award-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-xp-award-system/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-xp-award-system "<brief summary>"
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

# Acceptance Criteria: dc-cr-xp-award-system

## Gap analysis reference
- DB sections: core/ch10/Experience Points and Advancement (8 reqs)
- Depends on: dc-cr-encounter-creature-xp-table

---

## Happy Path
- [ ] `[NEW]` XP threshold: 1,000 XP to gain a level; on leveling, subtract 1,000 XP and continue accumulation.
- [ ] `[NEW]` All party members receive the same XP award from any encounter or accomplishment.
- [ ] `[NEW]` Trivial encounters normally grant 0 XP (GM may award minor accomplishment XP).
- [ ] `[NEW]` Advancement speed variants: Fast (800 XP), Standard (1,000 XP), Slow (1,200 XP) — configurable.
- [ ] `[NEW]` Story-based leveling option: ignore XP entirely; level after ~3–4 sessions at major milestones.
- [ ] `[NEW]` Accomplishment XP table implemented: minor/moderate/major accomplishments grant defined XP.
- [ ] `[NEW]` Moderate and major accomplishments: system flags for Hero Point award to an instrumental PC.
- [ ] `[NEW]` Creature XP uses Table 10–2 (dc-cr-encounter-creature-xp-table); Hazard XP uses Table 10–14.

## Edge Cases
- [ ] `[NEW]` XP award at level-up: excess XP carries over (no reset to 0).
- [ ] `[NEW]` PCs behind party level: double XP until caught up.

## Failure Modes
- [ ] `[TEST-ONLY]` Story-based leveling active: XP not tracked (not an error).
- [ ] `[TEST-ONLY]` Trivial encounter: 0 XP returned (not an error state).

## Security acceptance criteria
- Security AC exemption: game-mechanic XP/progression logic; no new routes or user-facing input beyond existing encounter and advancement handlers
- Agent: qa-dungeoncrawler
- Status: pending
