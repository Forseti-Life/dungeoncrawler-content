# Test Plan Design: dc-cr-economy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:43:04+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-economy/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-economy "<brief summary>"
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

# Acceptance Criteria: dc-cr-economy

## Gap analysis reference
- DB sections: core/ch06 — Currency and Economy (6), Services and Economy (5), Animals and Mounts (5)
  Note: These sections were already covered in dc-cr-equipment-ch06 and flipped to in_progress.
  This feature focuses on the economy/services/animals subsystem as a distinct service layer.
- Depends on: dc-cr-equipment-system ✓, dc-cr-character-creation

---

## Happy Path

### Currency
- [ ] `[EXTEND]` Currency system supports cp/sp/gp/pp with standard exchange rates (10cp=1sp, 10sp=1gp, 10gp=1pp).
- [ ] `[EXTEND]` Items with Price "—" cannot be purchased; Price 0 items are free.
- [ ] `[EXTEND]` Character starting wealth = 15 gp (150 sp) at level 1.
- [ ] `[EXTEND]` Bulk of coins: 1,000 = 1 Bulk (rounded down) tracked in inventory.

### Services
- [ ] `[EXTEND]` Hireling rate table implemented: unskilled = +0 all skills; skilled = +4 specialty, +0 otherwise.
- [ ] `[EXTEND]` Hireling rates double when adventuring into danger.
- [ ] `[EXTEND]` Spellcasting services: uncommon; cost = table price + material component cost; surcharges for uncommon/long-cast spells.
- [ ] `[EXTEND]` Subsist action can fulfill subsistence standard (replaces coin cost).

### Animals and Mounts
- [ ] `[EXTEND]` Animals have purchase and rental prices per Table 6-17.
- [ ] `[EXTEND]` Non-combat-trained animals panic in combat (frightened 4 + fleeing).
- [ ] `[EXTEND]` Combat-trained animals do not panic.
- [ ] `[EXTEND]` Barding tracked as armor: Strength modifier-based; no runes; Price/Bulk scale by size.

---

## Edge Cases
- [ ] `[EXTEND]` Selling during encounter or exploration: flagged (downtime-only purchase/sell).
- [ ] `[EXTEND]` Coin Bulk calculation: floor(coins / 1000), not ceiling.

## Failure Modes
- [ ] `[TEST-ONLY]` Item with Price "—" presented for purchase: blocked.
- [ ] `[TEST-ONLY]` Services at uncommon spell level: surcharge applied (not blocked).

## Security acceptance criteria
- Security AC exemption: game-mechanic economy and services logic; no new routes or user-facing input beyond existing character creation and downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
