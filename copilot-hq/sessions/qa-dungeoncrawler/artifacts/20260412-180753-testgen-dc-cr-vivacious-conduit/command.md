- Status: done
- Completed: 2026-04-12T22:48:21Z

# Test Plan Design: dc-cr-vivacious-conduit

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-vivacious-conduit/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-vivacious-conduit "<brief summary>"
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

# Acceptance Criteria: dc-cr-vivacious-conduit

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-rest-watch-starvation, dc-cr-skills-medicine-actions

---

## Happy Path

### Availability
- [ ] `[NEW]` Vivacious Conduit is selectable as a Gnome feat 9.

### Rest-based healing
- [ ] `[NEW]` After a 10-minute rest, the character regains HP equal to Constitution modifier × half level.
- [ ] `[NEW]` The healing stacks with Treat Wounds rather than replacing it.

---

## Edge Cases
- [ ] `[NEW]` Half level uses the system's standard rounding rule for the feat and stays consistent across all levels.
- [ ] `[NEW]` Negative Constitution modifiers do not produce negative healing.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without the feat receive only the baseline rest/Treat Wounds healing.
- [ ] `[TEST-ONLY]` The bonus is not applied outside a valid 10-minute rest window.

## Security acceptance criteria
- Security AC exemption: passive healing adjustment only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
