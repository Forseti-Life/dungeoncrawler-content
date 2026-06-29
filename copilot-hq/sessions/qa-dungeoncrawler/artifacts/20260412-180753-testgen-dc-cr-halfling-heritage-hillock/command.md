- Status: done
- Completed: 2026-04-12T22:42:59Z

# Test Plan Design: dc-cr-halfling-heritage-hillock

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-halfling-heritage-hillock/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-halfling-heritage-hillock "<brief summary>"
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

# Acceptance Criteria: dc-cr-halfling-heritage-hillock

## Gap analysis reference
- DB sections: core/ch02 (Halfling heritages)
- Depends on: dc-cr-halfling-ancestry, dc-cr-heritage-system, dc-cr-skills-medicine-actions

---

## Happy Path

### Heritage selection
- [ ] `[NEW]` Hillock Halfling is selectable as a halfling heritage at character creation.

### Overnight recovery bonus
- [ ] `[NEW]` On overnight rest, a Hillock Halfling regains additional HP equal to character level.

### Treat Wounds snack rider
- [ ] `[NEW]` When another character Treats Wounds on a Hillock Halfling, the patient can trigger the snack rider to add HP equal to character level.

---

## Edge Cases
- [ ] `[NEW]` The snack rider applies only to Treat Wounds and not to other healing sources.
- [ ] `[NEW]` The overnight bonus stacks on top of the normal overnight recovery amount.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without Hillock Halfling receive no bonus healing.
- [ ] `[TEST-ONLY]` The snack rider cannot be applied multiple times to the same Treat Wounds result.

## Security acceptance criteria
- Security AC exemption: passive heritage/healing adjustment only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
