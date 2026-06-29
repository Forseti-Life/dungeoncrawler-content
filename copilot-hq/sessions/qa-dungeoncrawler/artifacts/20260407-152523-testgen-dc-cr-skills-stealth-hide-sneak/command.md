# Test Plan Design: dc-cr-skills-stealth-hide-sneak

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-stealth-hide-sneak/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-stealth-hide-sneak "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-stealth-hide-sneak

## Gap analysis reference
- DB sections: core/ch04/Stealth (Dex) (REQs 1715–1729)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions

---

## Happy Path

### Conceal an Object [1 action, Manipulation]
- [ ] `[NEW]` Conceal an Object allows hiding a carried/worn item; observers must Seek to find it.
- [ ] `[NEW]` Crit Success: item hidden; observers need Seek to discover it; Success: same as Crit Success but lower DC to find.

### Hide [1 action]
- [ ] `[NEW]` Hide requires cover or concealment to attempt; transitions character from Observed → Hidden vs targeted observers.
- [ ] `[NEW]` Check: Stealth vs each observer's Perception DC.
- [ ] `[NEW]` If any observer beats DC: character remains Observed (not just detected by one).
- [ ] `[NEW]` Hidden character cannot use most actions without becoming Observed; Hide/Sneak/Step/undetected actions preserve Hidden status.

### Sneak [1 action, Move]
- [ ] `[NEW]` Sneak is a 1-action move requiring Hidden status; moves at half Speed (rounded down to 5-ft intervals).
- [ ] `[NEW]` At end of Sneak: roll Stealth vs each observer's Perception.
- [ ] `[NEW]` Success: remain Hidden; Failure: become Observed by failing observer.
- [ ] `[NEW]` Cannot end Sneak in an obvious/open location without becoming Observed.

### Avoid Notice [Exploration]
- [ ] `[NEW]` Avoid Notice (exploration) uses Stealth for the duration of exploration; character starts as Unnoticed.
- [ ] `[NEW]` First failed Seek or Perception by a relevant creature transitions character to Observed.

---

## Edge Cases
- [ ] `[NEW]` Sneak without Hidden status first: blocked — must be Hidden before Sneak.
- [ ] `[NEW]` Hide in open terrain with no cover: blocked or auto-fails.

## Failure Modes
- [ ] `[TEST-ONLY]` Sneak at full speed: rounds down to 5-ft interval (not blocked).
- [ ] `[TEST-ONLY]` Hide vs multiple observers: must succeed against ALL to stay Hidden.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
