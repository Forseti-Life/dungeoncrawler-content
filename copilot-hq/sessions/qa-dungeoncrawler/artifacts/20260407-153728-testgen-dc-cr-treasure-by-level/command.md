# Test Plan Design: dc-cr-treasure-by-level

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-treasure-by-level/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-treasure-by-level "<brief summary>"
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

# Acceptance Criteria: dc-cr-treasure-by-level

## Gap analysis reference
- DB sections: core/ch10/Treasure (6 reqs)
- Depends on: dc-cr-economy, dc-cr-xp-award-system

---

## Happy Path
- [ ] `[NEW]` Treasure per level (4-PC baseline) table implemented (currency + permanent items + consumables per level).
- [ ] `[NEW]` Currency column represents coins, gems, art objects, and lower-level items sold at half price.
- [ ] `[NEW]` For each PC beyond 4: additional currency per level added per Party Size adjustment rules.
- [ ] `[NEW]` Selling items: standard = half Price; gems, art objects, raw materials = full Price.
- [ ] `[NEW]` Characters can buy/sell items typically only during downtime.
- [ ] `[NEW]` New/replacement character starting wealth by level table implemented.

## Edge Cases
- [ ] `[NEW]` Selling during encounter or exploration (not downtime): flagged or blocked per GM.
- [ ] `[NEW]` Party size < 4: proportional currency reduction (not explicitly blocking).

## Failure Modes
- [ ] `[TEST-ONLY]` Selling a standard item at full price: blocked or flagged (only half price allowed).
- [ ] `[TEST-ONLY]` Starting wealth for mid-campaign character (level 5): uses level 5 row, not level 1.

## Security acceptance criteria
- Security AC exemption: game-mechanic economy logic; no new routes or user-facing input beyond existing character creation and downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
