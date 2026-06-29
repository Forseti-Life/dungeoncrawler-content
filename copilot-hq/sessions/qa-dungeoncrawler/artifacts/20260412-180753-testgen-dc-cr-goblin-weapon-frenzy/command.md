- Status: done
- Completed: 2026-04-12T22:33:27Z

# Test Plan Design: dc-cr-goblin-weapon-frenzy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-goblin-weapon-frenzy/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-goblin-weapon-frenzy "<brief summary>"
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

# Acceptance Criteria: dc-cr-goblin-weapon-frenzy

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-goblin-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Goblin Weapon Frenzy is selectable as a Goblin feat 5 when Goblin Weapon Familiarity is already present.

### Critical specialization trigger
- [ ] `[NEW]` On a critical hit with a goblin weapon, the appropriate weapon critical specialization effect is applied.
- [ ] `[NEW]` The effect works for `dogslicer`, `horsechopper`, and other goblin-tagged weapons that the character is proficient with.

---

## Edge Cases
- [ ] `[NEW]` Critical hits with non-goblin weapons do not trigger Goblin Weapon Frenzy.
- [ ] `[NEW]` The feature piggybacks on the existing critical specialization lookup instead of duplicating weapon-effect logic.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters missing Goblin Weapon Familiarity cannot select or use Goblin Weapon Frenzy.
- [ ] `[TEST-ONLY]` A normal hit does not trigger a specialization effect from this feat.

## Security acceptance criteria
- Security AC exemption: combat-resolution hook only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
