# Test Plan Design: dc-cr-skills-performance-perform

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-performance-perform/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-performance-perform "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-performance-perform

## Gap analysis reference
- DB sections: core/ch04/Performance (Cha) (REQs 1705–1708)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Perform [1 action or Downtime]
- [ ] `[NEW]` Perform can be used as a 1-action in encounter (to support abilities) or downtime to Earn Income.
- [ ] `[NEW]` Art types: acting, comedy, dance, oratory, singing, keyboards, percussion, strings, winds, etc. (character chooses one art type at character creation or training).
- [ ] `[NEW]` Earn Income via Performance: follows standard Earn Income table (see dc-cr-skills-lore-earn-income AC).

### Perform (Encounter — Inspire / Class Feature Support)
- [ ] `[NEW]` Perform check result (Crit Success / Success / Failure / Crit Failure) communicated to class-feature hooks (e.g., Bard Inspire Courage).
- [ ] `[NEW]` Crit Success: crowd loves it; Success: polite reception; Failure: poor reaction; Crit Failure: embarrassing.

---

## Edge Cases
- [ ] `[NEW]` Multiple art types: each tracked independently if feat grants additional art.

## Failure Modes
- [ ] `[TEST-ONLY]` Perform Crit Fail in encounter does not silently succeed — returns fail degree to caller.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
