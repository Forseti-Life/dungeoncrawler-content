# Test Plan Design: dc-cr-skills-thievery-disable-pick-lock

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-thievery-disable-pick-lock/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-thievery-disable-pick-lock "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-thievery-disable-pick-lock

## Gap analysis reference
- DB sections: core/ch04/Thievery (Dex) (REQs 1738–1748)
- Depends on: dc-cr-skill-system ✓, dc-cr-equipment-system

---

## Happy Path

### Palm an Object [1 action, Manipulation]
- [ ] `[NEW]` Palm an Object hides a small, easily concealed item; observers must Seek to notice.
- [ ] `[NEW]` Success: item considered hidden on the character's person until Seek reveals it.

### Steal [1 action, Manipulation]
- [ ] `[NEW]` Steal takes a small item from a target; requires the target to be unaware of the attempt (Observed to character or distracted).
- [ ] `[NEW]` Critical Failure: target (and nearby observers) become aware the character attempted to steal.

### Disable a Device [2 actions, Manipulation, Trained]
- [ ] `[NEW]` Disable a Device costs 2 actions per attempt; requires thieves' tools and Trained Thievery.
- [ ] `[NEW]` Complex devices (traps, alarms, etc.) may require multiple successes.
- [ ] `[NEW]` Crit Failure: triggers the trap/device.

### Pick a Lock [2 actions, Manipulation, Trained]
- [ ] `[NEW]` Pick a Lock costs 2 actions per attempt; requires thieves' tools and Trained Thievery.
- [ ] `[NEW]` DC set by lock quality (simple, average, good, superior).
- [ ] `[NEW]` Without thieves' tools (improvised): DC increases by 5.
- [ ] `[NEW]` Crit Failure: lock becomes jammed; no further attempts until repaired.

---

## Edge Cases
- [ ] `[NEW]` Disable Device without thieves' tools: improvised penalty or blocked per GM (system flags).
- [ ] `[NEW]` Pick Lock jammed state tracked per lock; persists until repaired.

## Failure Modes
- [ ] `[TEST-ONLY]` Steal Crit Fail: observers become aware (not just target).
- [ ] `[TEST-ONLY]` Pick Lock Crit Fail: lock jammed, further attempts blocked (not retriable).
- [ ] `[TEST-ONLY]` Disable Device untrained: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
