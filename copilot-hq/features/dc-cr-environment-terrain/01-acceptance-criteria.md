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
