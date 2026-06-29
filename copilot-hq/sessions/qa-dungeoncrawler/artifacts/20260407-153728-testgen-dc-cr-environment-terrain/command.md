# Test Plan Design: dc-cr-environment-terrain

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-environment-terrain/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-environment-terrain "<brief summary>"
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

# Acceptance Criteria: dc-cr-environment-terrain

## Gap analysis reference
- DB sections: core/ch10/Environment (23 reqs)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions

---

## Happy Path

### Environmental Damage Categories
- [ ] `[NEW]` Environmental damage categories implemented (minor/moderate/major/massive bludgeoning, falling, fire, cold, etc.) with damage amounts per tier.

### Terrain Types
- [ ] `[NEW]` Bogs: shallow = difficult terrain; deep = greater difficult terrain; magical bogs = hazardous terrain.
- [ ] `[NEW]` Ice: uneven ground AND difficult terrain.
- [ ] `[NEW]` Snow: shallow/packed = difficult terrain; loose/deep = greater difficult terrain; deep snow may be uneven ground.
- [ ] `[NEW]` Sand: packed = normal; loose/shallow = difficult terrain; loose/deep = uneven ground.
- [ ] `[NEW]` Rubble: difficult terrain; dense rubble = uneven ground.
- [ ] `[NEW]` Undergrowth: light = difficult terrain (allow Take Cover); heavy = greater difficult terrain (automatic cover); thorns = also hazardous terrain.
- [ ] `[NEW]` Slopes: gentle = normal; steep = requires Climb (Athletics). Characters flat-footed while climbing inclines.
- [ ] `[NEW]` Narrow surfaces: require Balance (Acrobatics); flat-footed; fall risk on hit or failed save (Reflex save at Balance DC).
- [ ] `[NEW]` Uneven ground: requires Balance (Acrobatics); flat-footed; fall risk on hit or failed save.

### Temperature Effects
- [ ] `[NEW]` Temperature effects implemented (mild/severe/extreme cold/heat) with damage and condition thresholds.

### Collapse and Burial
- [ ] `[NEW]` Avalanche damage: major or massive bludgeoning; Reflex save (Success = half; Crit Success = no burial).
- [ ] `[NEW]` Burial: restrained condition; minor bludgeoning damage per minute; possible cold damage; Fortitude saves for suffocation.
- [ ] `[NEW]` Rescue digging: Athletics check; 5×5 ft per 4 minutes (2 min on Crit Success); halved without tools.
- [ ] `[NEW]` Collapses: major or massive bludgeoning + burial; don't spread unless structural integrity failed.

### Wind
- [ ] `[NEW]` Wind: circumstance penalty to auditory Perception checks (strength-based tier system).
- [ ] `[NEW]` Wind: circumstance penalty to physical ranged attacks; powerful winds make ranged attacks impossible.
- [ ] `[NEW]` Flying in wind: difficult terrain (or greater) when moving against; Maneuver in Flight required; blown away on Crit Fail.
- [ ] `[NEW]` Ground movement in strong wind: Athletics check; Crit Fail = knocked back and prone. Small: –1 circumstance; Tiny: –2.

### Underwater
- [ ] `[NEW]` Underwater visibility: up to 240 ft (clear water); as low as 10 ft (murky water).
- [ ] `[NEW]` Swimming against current: difficult or greater difficult terrain (current-speed-based).
- [ ] `[NEW]` Current displacement: creature moved in current direction by current's speed at end of each turn.

---

## Edge Cases
- [ ] `[NEW]` Multiple terrain types stacking: each terrain effect applied independently (e.g., icy uneven ground = both conditions).
- [ ] `[NEW]` Flat-footed while climbing steep slope: combined with any other flat-footed source.

## Failure Modes
- [ ] `[TEST-ONLY]` Burial suffocation: Fortitude check required each round; failure advances suffocation.
- [ ] `[TEST-ONLY]` Wind making ranged attacks impossible: blocked entirely (not just penalized).

## Security acceptance criteria
- Security AC exemption: game-mechanic terrain and environment logic; no new routes or user-facing input beyond existing encounter and exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
