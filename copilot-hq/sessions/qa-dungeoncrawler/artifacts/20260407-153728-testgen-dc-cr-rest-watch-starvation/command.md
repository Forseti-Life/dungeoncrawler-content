# Test Plan Design: dc-cr-rest-watch-starvation

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-rest-watch-starvation/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-rest-watch-starvation "<brief summary>"
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

# Acceptance Criteria: dc-cr-rest-watch-starvation

## Gap analysis reference
- DB sections: core/ch10/Resting and Daily Preparations (4 reqs)
- Depends on: dc-cr-conditions ✓, dc-cr-skill-system ✓

---

## Happy Path

### Watch Schedule
- [ ] `[NEW]` Watch duration by party size implemented; all party members share watch duty; minimum watch segments tracked.
- [ ] `[NEW]` Daily preparation sequence: rest → watch → prepare spells/abilities (10 min focus and preparation).

### Starvation and Thirst
- [ ] `[NEW]` Without water: immediate fatigue applied; after (Con modifier + 1) days, 1d4 damage per hour (cannot be healed until thirst quenched).
- [ ] `[NEW]` Without food: immediate fatigue applied; after (Con modifier + 1) days, 1 damage per day (cannot be healed until fed).
- [ ] `[NEW]` Starvation/thirst tracked as environmental hazards; triggers resolve at appropriate intervals.
- [ ] `[NEW]` Healing blocked until underlying condition (starvation/thirst) resolved.

---

## Edge Cases
- [ ] `[NEW]` Con modifier ≤ 0: minimum of 1 day before damage onset.
- [ ] `[NEW]` Simultaneously starving and dehydrated: both damage tracks active independently.

## Failure Modes
- [ ] `[TEST-ONLY]` Healing during active starvation: healing blocked until fed.
- [ ] `[TEST-ONLY]` Daily prep without completing rest: blocked or flagged (no spell restoration).

## Security acceptance criteria
- Security AC exemption: game-mechanic rest and survival logic; no new routes or user-facing input beyond existing exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
