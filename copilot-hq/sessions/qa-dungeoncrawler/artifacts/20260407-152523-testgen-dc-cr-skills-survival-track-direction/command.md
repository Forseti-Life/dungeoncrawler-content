# Test Plan Design: dc-cr-skills-survival-track-direction

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-survival-track-direction/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-survival-track-direction "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-survival-track-direction

## Gap analysis reference
- DB sections: core/ch04/Survival (Wis) (REQs 1730–1737)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Sense Direction [Exploration]
- [ ] `[NEW]` Sense Direction is a free exploration activity; determines cardinal direction and rough location.
- [ ] `[NEW]` No check required in clear conditions; check required in supernatural darkness, magical fog, or featureless planes.
- [ ] `[NEW]` Critical Success: also senses approximate distance from home settlement or last known landmark.

### Cover Tracks [Exploration, Trained]
- [ ] `[NEW]` Cover Tracks is an exploration activity requiring Trained Survival; character moves at half speed.
- [ ] `[NEW]` Pursuers tracking the character's path must succeed at a Survival check vs the character's Stealth or Survival DC.

### Track [Exploration, Trained]
- [ ] `[NEW]` Track is an exploration activity requiring Trained Survival.
- [ ] `[NEW]` DC determined by how old the trail is and environmental conditions.
- [ ] `[NEW]` Degrees: Crit Success = fast movement + full info; Success = follow at half speed; Failure = no progress (can retry in same area); Crit Failure = lost track, cannot retry that specific trail.

---

## Edge Cases
- [ ] `[NEW]` Cover Tracks and Track in the same area: Track DC equals Cover Tracks result.
- [ ] `[NEW]` Track untrained: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Track Crit Fail: trail permanently lost (cannot retry that specific track).
- [ ] `[TEST-ONLY]` Cover Tracks without Trained Survival: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
