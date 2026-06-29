- Status: done
- Completed: 2026-04-12T18:44:20Z

# Test Plan Design: dc-cr-animal-accomplice

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-animal-accomplice/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-animal-accomplice "<brief summary>"
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

# Acceptance Criteria: dc-cr-animal-accomplice

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-familiar

---

## Happy Path

### Availability
- [ ] `[NEW]` Animal Accomplice is selectable as a Gnome feat 1.

### Familiar grant
- [ ] `[NEW]` Selecting the feat grants a familiar using the standard familiar rules.
- [ ] `[NEW]` The familiar can be chosen from the normal familiar type catalog.

---

## Edge Cases
- [ ] `[NEW]` Burrow-speed animals may be recommended in UI copy but are not mandatory.
- [ ] `[NEW]` Non-spellcasting gnome characters can still receive the familiar from this feat.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without the feat do not gain a familiar through this path.
- [ ] `[TEST-ONLY]` Invalid familiar types are rejected by the selection flow.

## Security acceptance criteria
- Security AC exemption: familiar grant/configuration only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
