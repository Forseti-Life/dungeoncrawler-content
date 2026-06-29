- Status: done
- Completed: 2026-04-12T22:44:57Z

# Test Plan Design: dc-cr-halfling-keen-eyes

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-halfling-keen-eyes/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-halfling-keen-eyes "<brief summary>"
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

# Acceptance Criteria: dc-cr-halfling-keen-eyes

## Gap analysis reference
- DB sections: core/ch02 (Halfling ancestry traits)
- Depends on: dc-cr-halfling-ancestry

---

## Happy Path

### Seek bonus
- [ ] `[NEW]` Halfling characters gain a +2 circumstance bonus on Seek checks against hidden or undetected creatures within 30 feet.

### Flat-check reduction
- [ ] `[NEW]` Against concealed targets, the targeting flat-check DC is reduced from 5 to 3 for halflings.
- [ ] `[NEW]` Against hidden targets, the targeting flat-check DC is reduced from 11 to 9 for halflings.

### Automatic grant
- [ ] `[NEW]` Keen Eyes is granted automatically as part of the halfling ancestry and does not require a separate selection.

---

## Edge Cases
- [ ] `[NEW]` The Seek bonus does not apply beyond 30 feet.
- [ ] `[NEW]` Flat-check reductions apply only to concealed/hidden targets, not to undetected or observed targets.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-halfling characters use the default flat-check DCs.
- [ ] `[TEST-ONLY]` Halflings do not get the Seek bonus when the target is outside 30 feet.

## Security acceptance criteria
- Security AC exemption: passive ancestry trait only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
