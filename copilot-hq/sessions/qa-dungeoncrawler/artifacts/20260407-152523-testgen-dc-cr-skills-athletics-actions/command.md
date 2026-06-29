# Test Plan Design: dc-cr-skills-athletics-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-athletics-actions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-athletics-actions "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-athletics-actions

## Gap analysis reference
- DB sections: core/ch04/Athletics (Str) (REQs 1619–1643)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions (in-progress Release B)

---

## Happy Path

### Escape (Athletics alternative)
- [ ] `[EXTEND]` Escape action accepts Athletics modifier as an alternative to unarmed attack modifier.

### Climb [1 action, Move]
- [ ] `[NEW]` Climb is a 1-action move; character is flat-footed during Climb unless they have a climb Speed.
- [ ] `[NEW]` Movement distance scales with land Speed (~5 ft success, ~10 ft crit success for standard character).
- [ ] `[NEW]` Critical Failure: character falls and lands prone.

### Force Open [1 action, Attack]
- [ ] `[NEW]` Force Open has the attack trait (counts toward MAP); –2 item penalty without crowbar.
- [ ] `[NEW]` Degrees: Critical Success = open without damage; Success = object becomes broken; Critical Failure = jammed + –2 to all future attempts.

### Grapple [1 action, Attack]
- [ ] `[NEW]` Grapple requires one free hand (or an existing grapple/restrain); size limit = target no more than one size larger.
- [ ] `[NEW]` Degrees: Crit Success = restrained; Success = grabbed; Failure = release existing grapple; Crit Failure = target may grab you or knock you prone.
- [ ] `[NEW]` Grabbed and restrained conditions last until end of grappler's next turn; broken by moving away or target Escaping.

### High Jump [2 actions]
- [ ] `[NEW]` High Jump costs 2 actions; requires a Stride of ≥10 ft or automatically fails.
- [ ] `[NEW]` Degrees: Crit Success = 8 ft vertical (or 5+10); Success = 5 ft; Failure = normal Leap; Crit Failure = fall prone.

### Long Jump [2 actions]
- [ ] `[NEW]` Long Jump costs 2 actions; DC = distance in feet; must Stride ≥10 ft in same direction first.
- [ ] `[NEW]` Maximum distance = character Speed; Crit Failure = normal Leap + prone.

### Shove [1 action, Attack]
- [ ] `[NEW]` Shove has attack trait; forced movement does NOT trigger movement reactions.
- [ ] `[NEW]` Degrees: Crit Success = 10 ft push; Success = 5 ft push; Crit Failure = attacker falls prone. Character may follow with a Stride.

### Swim [1 action, Move]
- [ ] `[NEW]` Swim is a 1-action move; no check required in calm water.
- [ ] `[NEW]` Air-breathing characters must hold breath each round while submerged.
- [ ] `[NEW]` If no Swim action at end of turn: sink 10 ft or drift (not applied on the turn entering water).
- [ ] `[NEW]` Critical Failure: costs 1 round of held breath.

### Trip [1 action, Attack]
- [ ] `[NEW]` Trip has attack trait; Crit Success = 1d6 bludgeoning + prone; Success = prone only; Crit Failure = attacker falls prone.

### Disarm [1 action, Attack, Trained]
- [ ] `[NEW]` Disarm requires Trained Athletics; has attack trait.
- [ ] `[NEW]` Crit Success = item dropped; Success = grip weakened (bonuses/penalties until start of target's next turn); Crit Failure = attacker becomes flat-footed.

### Falling Damage
- [ ] `[NEW]` Falling damage = half distance in feet as bludgeoning damage; character lands prone.
- [ ] `[NEW]` Soft surfaces (water, snow) reduce effective fall distance by up to 20 ft (capped at surface depth).
- [ ] `[NEW]` Grab an Edge reaction can reduce or eliminate fall damage.

---

## Edge Cases
- [ ] `[NEW]` Grapple vs creature more than one size larger: blocked.
- [ ] `[NEW]` High Jump without prior Stride: automatically fails (prone not applied on auto-fail).
- [ ] `[NEW]` Long Jump max distance capped at Speed; attempting beyond is blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Disarm blocked for untrained Athletics.
- [ ] `[TEST-ONLY]` Shove forced movement: movement reactions NOT triggered.
- [ ] `[TEST-ONLY]` Swim sink rule: applied only at turn end, not on initial entry turn.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
