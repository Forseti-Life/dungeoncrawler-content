- Status: done
- Completed: 2026-04-12T19:45:10Z

# Test Plan Design: dc-cr-first-world-adept

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-first-world-adept/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-first-world-adept "<brief summary>"
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

# Acceptance Criteria: dc-cr-first-world-adept

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-spellcasting, one existing primal innate spell source

---

## Happy Path

### Availability
- [ ] `[NEW]` First World Adept is selectable as a Gnome feat 9 only when the character already has at least one primal innate spell.

### Granted spells
- [ ] `[NEW]` Selecting the feat grants `faerie fire` as a 2nd-level primal innate spell usable once per day.
- [ ] `[NEW]` Selecting the feat grants `invisibility` as a 2nd-level primal innate spell usable once per day.

---

## Edge Cases
- [ ] `[NEW]` The prerequisite is satisfied by any valid primal innate spell source (heritage or feat), not by prepared spellcasting alone.
- [ ] `[NEW]` Both granted spells reset on daily preparation with other once-per-day innate spells.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without a primal innate spell cannot select First World Adept.
- [ ] `[TEST-ONLY]` The granted spells are not castable more than once per day each.

## Security acceptance criteria
- Security AC exemption: ancestry feat grant only; no new route surface beyond existing character and spell flows
- Agent: qa-dungeoncrawler
- Status: pending
