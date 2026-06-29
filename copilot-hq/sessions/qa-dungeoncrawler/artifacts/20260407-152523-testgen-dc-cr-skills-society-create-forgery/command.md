# Test Plan Design: dc-cr-skills-society-create-forgery

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-society-create-forgery/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-society-create-forgery "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-society-create-forgery

## Gap analysis reference
- DB sections: core/ch04/Society (Int) (REQs 1710–1714)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Society)
- [ ] `[NEW]` Society covers: cultures, laws, social structures, history, humanoid organizations, nations, settlements.
- [ ] `[NEW]` Untrained Recall Knowledge permitted.

### Create a Forgery [Downtime, Secret, Trained]
- [ ] `[NEW]` Create a Forgery is a downtime activity (10 min per page); requires Trained Society and appropriate writing materials.
- [ ] `[NEW]` Difficulty: common documents (DC 20), specialist documents (DC 30+), official government seals (DC 40+).
- [ ] `[NEW]` On Failure: forgery is detectable; on Crit Failure: forgery is obviously fake AND character becomes aware it failed (can retry).
- [ ] `[NEW]` Detection: viewers use Society vs character's Deception DC when examining the forgery.

---

## Edge Cases
- [ ] `[NEW]` Create Forgery untrained: blocked.
- [ ] `[NEW]` Official seal forgery without special tools: DC 40 (highest tier, often auto-fail).

## Failure Modes
- [ ] `[TEST-ONLY]` Crit Fail: character is informed the forgery failed (not a surprise to them).
- [ ] `[TEST-ONLY]` Detection uses Deception DC not Forgery DC (two separate rolls).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
