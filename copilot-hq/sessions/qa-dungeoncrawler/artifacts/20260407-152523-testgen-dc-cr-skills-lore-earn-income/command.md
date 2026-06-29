# Test Plan Design: dc-cr-skills-lore-earn-income

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-lore-earn-income/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-lore-earn-income "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-lore-earn-income

## Gap analysis reference
- DB sections: core/ch04/Lore (Int) (REQs 1684–1687)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Lore)
- [ ] `[NEW]` Lore Recall Knowledge functions identically to other skills' Recall Knowledge but is scoped to a specific Lore subcategory (e.g., Mercantile Lore, Sailing Lore).
- [ ] `[NEW]` Characters may have multiple Lore subcategories from background, ancestry, or class features.
- [ ] `[NEW]` Untrained Lore can be used to Recall Knowledge, but only for the chosen subcategory.

### Earn Income [Downtime]
- [ ] `[NEW]` Earn Income is a downtime activity using Lore (or Crafting/Performance for other professions).
- [ ] `[NEW]` DC determined by task level (1–20); max task level = character level (except Trained cap at level –1).
- [ ] `[NEW]` Degrees: Crit Success = next-tier daily income; Success = on-level daily income; Failure = 0 income but no penalty; Crit Failure = 0 income + blocked from that employer for ~1 week.

---

## Edge Cases
- [ ] `[NEW]` Multiple Lore subcategories stacked: each tracked independently.
- [ ] `[NEW]` Earn Income task level cap enforced per proficiency tier.

## Failure Modes
- [ ] `[TEST-ONLY]` Earn Income Crit Fail blocks same employer for ~1 week; other employers unaffected.
- [ ] `[TEST-ONLY]` Task above level cap: blocked (not just penalized).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
