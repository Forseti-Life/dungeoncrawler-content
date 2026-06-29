# Test Plan Design: dc-cr-encounter-creature-xp-table

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-encounter-creature-xp-table/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-encounter-creature-xp-table "<brief summary>"
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

# Acceptance Criteria: dc-cr-encounter-creature-xp-table

## Gap analysis reference
- DB sections: core/ch10/Encounter Building (5 reqs), core/ch10/Experience Points and Advancement (8 reqs partially — encounter-specific rows)
- Depends on: dc-cr-xp-award-system

---

## Happy Path

### Encounter Threat Tiers
- [ ] `[NEW]` Encounter threat tiers implemented: Trivial (≤40 XP), Low (60 XP), Moderate (80 XP), Severe (120 XP), Extreme (160 XP).
- [ ] `[NEW]` XP budget baseline is for 4 PCs; Character Adjustment value used for additional/missing PCs.

### Encounter Budget System
- [ ] `[NEW]` Encounter budget system built using creature XP cost table (relative level vs. party level → XP cost).
- [ ] `[NEW]` Creatures > 4 levels below party are trivial; creatures > 4 levels above are too dangerous (XP values not defined).
- [ ] `[NEW]` Party level drives all encounter budget calculations; level-variance handling supported.
- [ ] `[NEW]` PCs behind party level earn double XP until caught up.

### Creature XP Table
- [ ] `[NEW]` Creature XP by level-delta implemented:
  - Party Level –4: 10 XP
  - Party Level –3: 15 XP
  - Party Level –2: 20 XP
  - Party Level –1: 30 XP
  - Party Level: 40 XP
  - Party Level +1: 60 XP
  - Party Level +2: 80 XP
  - Party Level +3: 120 XP
  - Party Level +4: 160 XP
- [ ] `[NEW]` Hazard XP uses Table 10–14 (per dc-cr-hazards feature).

## Edge Cases
- [ ] `[NEW]` Party size of 1–3: Character Adjustment reduces XP budget proportionally.
- [ ] `[NEW]` Party size 5+: Character Adjustment increases budget.

## Failure Modes
- [ ] `[TEST-ONLY]` Creature level > party +4: no XP entry returned (not an error).
- [ ] `[TEST-ONLY]` Trivial encounter XP award: 0 XP (not an error state).

## Security acceptance criteria
- Security AC exemption: game-mechanic XP calculation; no new routes or user-facing input beyond existing encounter phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
