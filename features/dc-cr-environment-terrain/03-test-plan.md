# Test Plan: dc-cr-environment-terrain

## Feature
Environment & Terrain rules from PF2E Core Rulebook Chapter 10.

## KB references
- None found for environment/terrain mechanics specifically.

## Dependencies
- dc-cr-skill-system ✓ (confirmed available)
- dc-cr-conditions (required for flat-footed, restrained, prone conditions)

## Suite mapping
All TCs target the `dungeoncrawler-content` suite (game-mechanic data/logic).
No new routes or auth surfaces — Security AC exemption confirmed.

---

## Test Cases

### TC-ENV-01: Environmental damage categories — data completeness
- **Description:** All damage categories (minor/moderate/major/massive bludgeoning, falling, fire, cold) exist with defined damage amounts per tier.
- **Suite:** dungeoncrawler-content
- **Expected:** Each category returns a non-null damage value for each tier; no missing tiers.
- **Roles:** N/A (data integrity)

### TC-ENV-02: Environmental damage — tier ordering
- **Description:** Damage amounts increase monotonically: minor < moderate < major < massive.
- **Suite:** dungeoncrawler-content
- **Expected:** Tier ordering assertion passes for each damage type.
- **Roles:** N/A

### TC-ENV-03: Bog terrain — shallow classification
- **Description:** Shallow bog returns terrain_type = "difficult".
- **Suite:** dungeoncrawler-content
- **Expected:** `terrain_classification("bog", "shallow")` → `"difficult"`
- **Roles:** N/A

### TC-ENV-04: Bog terrain — deep classification
- **Description:** Deep bog returns terrain_type = "greater_difficult".
- **Suite:** dungeoncrawler-content
- **Expected:** `terrain_classification("bog", "deep")` → `"greater_difficult"`
- **Roles:** N/A

### TC-ENV-05: Bog terrain — magical classification
- **Description:** Magical bog returns terrain_type = "hazardous".
- **Suite:** dungeoncrawler-content
- **Expected:** `terrain_classification("bog", "magical")` → `"hazardous"`
- **Roles:** N/A

### TC-ENV-06: Ice terrain — dual condition
- **Description:** Ice terrain applies both uneven_ground and difficult terrain simultaneously.
- **Suite:** dungeoncrawler-content
- **Expected:** Ice terrain record has `conditions: ["uneven_ground", "difficult"]`
- **Roles:** N/A

### TC-ENV-07: Snow terrain — variant classifications
- **Description:** Shallow/packed snow = difficult; loose/deep = greater_difficult; deep may include uneven_ground.
- **Suite:** dungeoncrawler-content
- **Expected:** Three snow variants return correct terrain_type values.
- **Roles:** N/A

### TC-ENV-08: Sand terrain — variant classifications
- **Description:** Packed sand = normal; loose/shallow = difficult; loose/deep = uneven_ground.
- **Suite:** dungeoncrawler-content
- **Expected:** Three sand variants return correct terrain_type values.
- **Roles:** N/A

### TC-ENV-09: Rubble terrain — classifications
- **Description:** Rubble = difficult; dense rubble = uneven_ground.
- **Suite:** dungeoncrawler-content
- **Expected:** `terrain_classification("rubble", "standard")` → `"difficult"`; `terrain_classification("rubble", "dense")` → `"uneven_ground"`
- **Roles:** N/A

### TC-ENV-10: Undergrowth terrain — variants
- **Description:** Light = difficult + Take Cover allowed; heavy = greater_difficult + automatic cover; thorns = also hazardous.
- **Suite:** dungeoncrawler-content
- **Expected:** Each undergrowth variant returns correct terrain_type and cover flag.
- **Roles:** N/A

### TC-ENV-11: Slopes — gentle vs steep
- **Description:** Gentle slope = normal terrain; steep slope = requires Athletics Climb; character flat-footed while climbing incline.
- **Suite:** dungeoncrawler-content
- **Expected:** `slope("gentle")` → `"normal"`; `slope("steep")` → `{requires: "Athletics:Climb", flat_footed: true}`
- **Roles:** N/A

### TC-ENV-12: Narrow surfaces — Balance requirement
- **Description:** Narrow surface requires Acrobatics Balance; character flat-footed; fall risk on hit or failed Reflex save.
- **Suite:** dungeoncrawler-content
- **Expected:** Narrow surface record includes `requires: "Acrobatics:Balance"`, `flat_footed: true`, `fall_risk: {trigger: ["hit", "reflex_fail"]}`
- **Roles:** N/A

### TC-ENV-13: Uneven ground — Balance requirement
- **Description:** Uneven ground requires Acrobatics Balance; character flat-footed; fall risk on hit or failed save.
- **Suite:** dungeoncrawler-content
- **Expected:** Uneven ground record matches Narrow surface pattern for Balance/flat-footed/fall_risk.
- **Roles:** N/A

### TC-ENV-14: Temperature effects — all tiers present
- **Description:** All temperature bands (mild cold, severe cold, extreme cold, mild heat, severe heat, extreme heat) have defined damage amounts and condition thresholds.
- **Suite:** dungeoncrawler-content
- **Expected:** Six temperature tier records returned; each has `damage` and `conditions` non-null.
- **Roles:** N/A

### TC-ENV-15: Avalanche — damage tier
- **Description:** Avalanche applies major or massive bludgeoning damage.
- **Suite:** dungeoncrawler-content
- **Expected:** Avalanche damage_tier ∈ {"major_bludgeoning", "massive_bludgeoning"}.
- **Roles:** N/A

### TC-ENV-16: Avalanche — Reflex save outcomes
- **Description:** Avalanche Reflex save: Success = half damage; Critical Success = no burial.
- **Suite:** dungeoncrawler-content
- **Expected:** Avalanche record has `save: {type: "Reflex", success: "half_damage", crit_success: "no_burial"}`.
- **Roles:** N/A

### TC-ENV-17: Burial — conditions and damage
- **Description:** Burial applies restrained condition; minor bludgeoning damage per minute; possible cold damage; Fortitude saves for suffocation.
- **Suite:** dungeoncrawler-content
- **Expected:** Burial record: `conditions: ["restrained"]`, `damage_per_minute: "minor_bludgeoning"`, `suffocation_save: "Fortitude"`.
- **Roles:** N/A

### TC-ENV-18: Rescue digging — Athletics rate
- **Description:** Rescue digging clears 5×5 ft per 4 min; Crit Success = 2 min; halved without tools.
- **Suite:** dungeoncrawler-content
- **Expected:** Rescue digging record: `base_rate: {area: "5x5", time_minutes: 4}`, `crit_success_time: 2`, `no_tools_modifier: 0.5`.
- **Roles:** N/A

### TC-ENV-19: Collapses — damage and non-spread rule
- **Description:** Collapse causes major or massive bludgeoning + burial; does not spread unless structural integrity failed.
- **Suite:** dungeoncrawler-content
- **Expected:** Collapse record: damage_tier ∈ {"major", "massive"} + burial flag; spread_condition = "structural_integrity_failed".
- **Roles:** N/A

### TC-ENV-20: Wind — Perception penalty (auditory)
- **Description:** Wind applies circumstance penalty to auditory Perception checks; penalty scales by wind strength tier.
- **Suite:** dungeoncrawler-content
- **Expected:** Each wind strength tier has a non-zero `auditory_perception_penalty`; stronger tiers have higher penalties.
- **Roles:** N/A

### TC-ENV-21: Wind — ranged attack penalty
- **Description:** Wind applies circumstance penalty to physical ranged attacks; powerful wind makes ranged attacks impossible.
- **Suite:** dungeoncrawler-content
- **Expected:** Low-tier wind: `ranged_attack_penalty > 0`; powerful wind: `ranged_attacks_impossible: true`.
- **Roles:** N/A

### TC-ENV-22: Flying in wind — movement terrain
- **Description:** Flying against wind = difficult (or greater) terrain; Maneuver in Flight required; blown away on Crit Fail.
- **Suite:** dungeoncrawler-content
- **Expected:** Flying wind record: `against_wind: "difficult_terrain"`, `requires: "Maneuver_in_Flight"`, `crit_fail: "blown_away"`.
- **Roles:** N/A

### TC-ENV-23: Ground movement in strong wind — Athletics check
- **Description:** Ground movement in strong wind requires Athletics; Crit Fail = knocked back and prone; Small −1 penalty; Tiny −2 penalty.
- **Suite:** dungeoncrawler-content
- **Expected:** Ground wind record: `requires: "Athletics"`, `crit_fail: ["knocked_back", "prone"]`, `small_penalty: -1`, `tiny_penalty: -2`.
- **Roles:** N/A

### TC-ENV-24: Underwater visibility — clear water
- **Description:** Clear water visibility up to 240 ft.
- **Suite:** dungeoncrawler-content
- **Expected:** `underwater_visibility("clear")` → `240` (ft).
- **Roles:** N/A

### TC-ENV-25: Underwater visibility — murky water
- **Description:** Murky water visibility as low as 10 ft.
- **Suite:** dungeoncrawler-content
- **Expected:** `underwater_visibility("murky")` → `10` (ft minimum).
- **Roles:** N/A

### TC-ENV-26: Swimming against current — terrain classification
- **Description:** Swimming against current = difficult or greater difficult terrain based on current speed.
- **Suite:** dungeoncrawler-content
- **Expected:** Slow current → `"difficult"`; fast current → `"greater_difficult"`.
- **Roles:** N/A
- **Note to PM:** Exact current speed thresholds distinguishing difficult vs greater_difficult are not specified in AC. Automation cannot assert specific thresholds without defined values.

### TC-ENV-27: Current displacement — end-of-turn movement
- **Description:** Creature in current is moved in current's direction by current's speed at end of each turn.
- **Suite:** dungeoncrawler-content
- **Expected:** Current displacement record: `timing: "end_of_turn"`, `direction: "current_direction"`, `distance: "current_speed"`.
- **Roles:** N/A

### TC-ENV-28 (Edge): Multiple terrain types — independent stacking
- **Description:** Multiple terrain types stack independently (e.g., icy uneven ground = both difficult and uneven_ground conditions).
- **Suite:** dungeoncrawler-content
- **Expected:** Combined terrain query returns all conditions from each component terrain type; no deduplication/override.
- **Roles:** N/A

### TC-ENV-29 (Edge): Flat-footed stacking on steep slope
- **Description:** Flat-footed from climbing steep slope stacks with any other flat-footed source (is not cancelled/redundant).
- **Suite:** dungeoncrawler-content
- **Expected:** Flat-footed flag remains true regardless of other flat-footed sources; no override logic removes it.
- **Roles:** N/A

### TC-ENV-30 (Failure): Burial suffocation — Fortitude advance
- **Description:** Each round while buried, character must make Fortitude save; failure advances suffocation track.
- **Suite:** dungeoncrawler-content
- **Expected:** Burial suffocation: `save_required_each_round: true`, `fail_result: "advance_suffocation"`.
- **Roles:** N/A

### TC-ENV-31 (Failure): Wind ranged attacks — impossible, not just penalized
- **Description:** Powerful wind makes ranged attacks entirely impossible, not simply penalized.
- **Suite:** dungeoncrawler-content
- **Expected:** Powerful wind: `ranged_attacks_impossible: true` (not a finite penalty value).
- **Roles:** N/A

---

## Notes to PM

1. **Current speed thresholds (TC-ENV-26):** AC specifies difficult vs greater_difficult for swimming against current based on "current speed" but does not define the speed breakpoint. Automation needs the exact threshold value (e.g., "≤15 ft/round = difficult; >15 ft/round = greater_difficult"). Flag for BA clarification.

2. **Dependency on dc-cr-conditions:** TCs involving flat-footed, restrained, prone, and suffocation conditions (TC-ENV-11 through TC-ENV-13, TC-ENV-17, TC-ENV-23, TC-ENV-29, TC-ENV-30) are conditional on dc-cr-conditions being in scope. Automation for those TCs cannot be activated until dc-cr-conditions ships.

3. **Security AC exemption confirmed:** No new routes or user-facing input surfaces. All TCs are data-model/logic assertions only.
