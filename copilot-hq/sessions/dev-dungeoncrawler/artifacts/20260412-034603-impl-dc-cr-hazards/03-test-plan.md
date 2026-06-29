# Test Plan: dc-cr-hazards

## Feature
Hazard system from PF2E Core Rulebook Chapter 10.

## KB references
- None found for hazard mechanics specifically.
- Cross-reference: dc-cr-encounter-creature-xp-table (TC-XPT notes hazard XP uses Table 10–14, not creature table — confirmed here as a separate hazard XP table).

## Dependencies
- dc-cr-encounter-rules ✓ (confirmed available)
- dc-cr-conditions ✓ (confirmed available)

## Suite mapping
All TCs target the `dungeoncrawler-content` suite (game-mechanic data/logic).
No new routes or auth surfaces — Security AC exemption confirmed.

---

## Test Cases

### TC-HAZ-01: Hazard stat block — required fields present
- **Description:** Every hazard record has Stealth DC, AC, saving throw modifiers, Hardness, HP, and Broken Threshold defined.
- **Suite:** dungeoncrawler-content
- **Expected:** All six stat block fields are non-null for each hazard in the data set.
- **Roles:** N/A

### TC-HAZ-02: Detection — hazard without minimum proficiency (auto-roll)
- **Description:** When a party enters an area containing a hazard with no minimum proficiency requirement, all PCs auto-roll a secret Perception check vs the hazard's Stealth DC.
- **Suite:** dungeoncrawler-content
- **Expected:** Detection attempt is triggered for all party members; no active Search required.
- **Roles:** N/A

### TC-HAZ-03: Detection — hazard with minimum proficiency (Search required)
- **Description:** When a hazard requires minimum proficiency to detect, only PCs actively Searching AND meeting the minimum proficiency rank attempt the check.
- **Suite:** dungeoncrawler-content
- **Expected:** Detection attempt is limited to Searching PCs who qualify; non-Searching or below-rank PCs are excluded.
- **Roles:** N/A

### TC-HAZ-04: Detection — Detect Magic reveals magical hazard
- **Description:** Detect Magic can reveal a magical hazard regardless of minimum proficiency requirement but does not grant ability to disable.
- **Suite:** dungeoncrawler-content
- **Expected:** Detect Magic sets `detected: true` on a magical hazard; `disable_eligible: false` unless normal disable conditions met.
- **Roles:** N/A

### TC-HAZ-05: Passive trigger — auto-activates if undetected
- **Description:** A passive-trigger hazard (floor plate, sensor) activates automatically when the triggering condition is met if the hazard was not detected beforehand.
- **Suite:** dungeoncrawler-content
- **Expected:** `trigger_type: "passive"` hazard fires when condition met and `detected: false`.
- **Roles:** N/A

### TC-HAZ-06: Passive trigger — does not activate if detected and avoided
- **Description:** A detected passive-trigger hazard does not fire automatically (party can choose to avoid it).
- **Suite:** dungeoncrawler-content
- **Expected:** `detected: true` hazard does not auto-trigger; party interaction required.
- **Roles:** N/A

### TC-HAZ-07: Active trigger — only activates on explicit PC action
- **Description:** An active-trigger hazard (opening a door) activates only when a PC explicitly takes the triggering action, regardless of detection state.
- **Suite:** dungeoncrawler-content
- **Expected:** `trigger_type: "active"` hazard fires only on the specified triggering action.
- **Roles:** N/A

### TC-HAZ-08: Simple hazard — one reaction, single-step resolution
- **Description:** Simple hazard has exactly one reaction and its effect resolves in a single step with no ongoing routine.
- **Suite:** dungeoncrawler-content
- **Expected:** Simple hazard record: `hazard_type: "simple"`, `reactions: 1`, `has_routine: false`.
- **Roles:** N/A

### TC-HAZ-09: Complex hazard — starts encounter on trigger
- **Description:** Complex hazard's reaction starts an encounter/initiative sequence; performs a routine each round as specified in stat block.
- **Suite:** dungeoncrawler-content
- **Expected:** Complex hazard: `hazard_type: "complex"`, `starts_encounter: true`, `has_routine: true`, `routine` field non-null.
- **Roles:** N/A

### TC-HAZ-10: Complex hazard — initiative when PCs already in encounter
- **Description:** If PCs are already in encounter mode when a complex hazard triggers, the hazard enters at its own initiative using its Stealth modifier.
- **Suite:** dungeoncrawler-content
- **Expected:** Hazard initiative roll uses `stealth_modifier`; hazard inserted into existing initiative order correctly.
- **Roles:** N/A

### TC-HAZ-11: Disable a Device — action cost and skill check
- **Description:** Disable a Device is a 2-action activity requiring a skill check vs the hazard's disable DC.
- **Suite:** dungeoncrawler-content
- **Expected:** Disable action: `action_cost: 2`, `check_vs: "disable_dc"`.
- **Roles:** N/A

### TC-HAZ-12: Disable — requires prior detection
- **Description:** A character must have detected (or been told about) the hazard to attempt disabling it.
- **Suite:** dungeoncrawler-content
- **Expected:** Disable attempt blocked if `detected: false` and `informed: false`; returns error/prevention state.
- **Roles:** N/A

### TC-HAZ-13: Disable — minimum proficiency gate (if applicable)
- **Description:** When a hazard has a minimum proficiency requirement for disabling, characters below that rank cannot attempt the check.
- **Suite:** dungeoncrawler-content
- **Expected:** Disable attempt blocked for characters below `min_disable_proficiency`; no roll made.
- **Roles:** N/A

### TC-HAZ-14: Complex hazard — multiple successes required
- **Description:** Complex hazards may require multiple successful disable checks; a critical success counts as two successes on one component.
- **Suite:** dungeoncrawler-content
- **Expected:** Complex hazard with multi-success: `successes_required > 1`; critical success increments success counter by 2.
- **Roles:** N/A

### TC-HAZ-15: Hazard reset behavior
- **Description:** Hazards may auto-reset after a specified time or require manual reset as defined in their stat block.
- **Suite:** dungeoncrawler-content
- **Expected:** Hazard `reset` field is one of: `"auto:<duration>"`, `"manual"`, or `null` (destroyed only). Value matches stat block spec.
- **Roles:** N/A

### TC-HAZ-16: Broken Threshold — broken state
- **Description:** A hazard at or below its Broken Threshold is broken: non-functional and repairable but not destroyed.
- **Suite:** dungeoncrawler-content
- **Expected:** `current_hp <= broken_threshold` → `state: "broken"`, `active: false`, `repairable: true`.
- **Roles:** N/A

### TC-HAZ-17: Destroyed at 0 HP
- **Description:** A hazard at 0 HP is destroyed and cannot be repaired.
- **Suite:** dungeoncrawler-content
- **Expected:** `current_hp == 0` → `state: "destroyed"`, `active: false`, `repairable: false`.
- **Roles:** N/A

### TC-HAZ-18: Hitting a hazard — triggers it
- **Description:** Attacking a hazard typically triggers it unless the attack destroys it outright (reduces HP to 0).
- **Suite:** dungeoncrawler-content
- **Expected:** Attack that reduces HP > 0 → `triggered: true`; attack that reduces HP to 0 → `triggered: false`, `state: "destroyed"`.
- **Roles:** N/A

### TC-HAZ-19: Hazard immunity to non-object-targeting effects
- **Description:** Hazards are immune to effects that cannot target objects unless the stat block specifies otherwise.
- **Suite:** dungeoncrawler-content
- **Expected:** Applying a non-object-targeting effect to a standard hazard returns `immune: true`; hazard with override flag accepts the effect.
- **Roles:** N/A

### TC-HAZ-20: Magical hazard — spell level and counteract DC
- **Description:** Magical hazards have a spell level and counteract DC; counteracting follows ch09 rules.
- **Suite:** dungeoncrawler-content
- **Expected:** Magical hazard record has `spell_level` and `counteract_dc` both non-null.
- **Roles:** N/A
- **Note to PM:** ch09 counteract rules are in dc-cr-spells-ch07 scope. Automation for this TC is conditional on dc-cr-spells-ch07 being implemented.

### TC-HAZ-21: Hazard XP table — simple vs complex distinction
- **Description:** Hazard XP table assigns different XP values for simple vs complex hazards at equivalent level deltas.
- **Suite:** dungeoncrawler-content
- **Expected:** For each level delta, `xp_simple < xp_complex`.
- **Roles:** N/A

### TC-HAZ-22: Hazard XP — scales by hazard level vs party level
- **Description:** Hazard XP scales appropriately based on hazard level relative to party level (similar to creature XP table).
- **Suite:** dungeoncrawler-content
- **Expected:** Hazard XP table has entries for standard level delta range; higher hazard level relative to party = higher XP.
- **Roles:** N/A
- **Note to PM:** Exact XP values per level delta are not specified in AC. Automation needs the exact hazard XP table values (from Table 10–14 of PF2E CRB) to assert correctness. This is a data-model completeness check only until those values are confirmed.

### TC-HAZ-23: Hazard XP — awarded only once per hazard per party
- **Description:** XP is awarded when a hazard is overcome (disabled, avoided, or endured) and only once regardless of engagement count.
- **Suite:** dungeoncrawler-content
- **Expected:** `xp_awarded_flag` set to `true` after first overcome; subsequent overcomes of same hazard return 0 XP and do not re-flag.
- **Roles:** N/A

### TC-HAZ-24 (Edge): Hazard triggered before detection — full effect, no save
- **Description:** If a hazard triggers before any PC detects it, PCs take the full effect with no pre-trigger save opportunity.
- **Suite:** dungeoncrawler-content
- **Expected:** Trigger on `detected: false` applies full hazard effect; no detection-save step inserted.
- **Roles:** N/A

### TC-HAZ-25 (Edge): Broken hazard cannot activate
- **Description:** A broken hazard (HP at or below BT) cannot activate even if triggering conditions are met.
- **Suite:** dungeoncrawler-content
- **Expected:** `state: "broken"` hazard ignores passive and active trigger conditions; no effect fires.
- **Roles:** N/A

### TC-HAZ-26 (Failure): Disable with insufficient proficiency — blocked
- **Description:** Attempting to disable a minimum-proficiency hazard with a character below the required rank is blocked with no roll.
- **Suite:** dungeoncrawler-content
- **Expected:** Disable attempt returns `blocked: true`, `reason: "insufficient_proficiency"`; no skill roll is made.
- **Roles:** N/A

### TC-HAZ-27 (Failure): Critical failure on disable — triggers hazard
- **Description:** A critical failure on any disable attempt triggers the hazard immediately.
- **Suite:** dungeoncrawler-content
- **Expected:** Disable roll result = critical_failure → `hazard_triggered: true`; hazard effect fires immediately.
- **Roles:** N/A

### TC-HAZ-28 (Failure): Critical failure on counteract — triggers hazard
- **Description:** A critical failure on a counteract attempt against a magical hazard triggers the hazard.
- **Suite:** dungeoncrawler-content
- **Expected:** Counteract roll result = critical_failure → `hazard_triggered: true`.
- **Roles:** N/A
- **Note to PM:** Conditional on dc-cr-spells-ch07 (counteract rules).

### TC-HAZ-29 (Failure): XP awarded twice for same hazard — blocked
- **Description:** Attempting to award XP for a hazard that has already been overcome in this session is blocked.
- **Suite:** dungeoncrawler-content
- **Expected:** Second XP award attempt for same hazard_id returns `xp: 0`; `xp_awarded_flag` remains `true`.
- **Roles:** N/A

---

## Notes to PM

1. **Hazard XP table values (TC-HAZ-22):** AC references Table 10–14 (PF2E CRB) but does not enumerate exact XP values per level delta. Dev will need these values to populate the data model; QA automation needs them to assert correctness. Recommend BA extract and confirm the full table values before implementation.

2. **Counteract dependency (TC-HAZ-20, TC-HAZ-28):** These two TCs depend on counteract rules being implemented (dc-cr-spells-ch07). Recommend sequencing dc-cr-hazards into the same release as or after dc-cr-spells-ch07.

3. **XP cross-dependency:** dc-cr-encounter-creature-xp-table test plan (TC-XPT) already confirmed that hazard XP uses a separate table (Table 10–14), not the creature XP table. These two features must use distinct XP lookup functions — flag for Dev.

4. **Security AC exemption confirmed:** No new routes or user-facing input surfaces. All TCs are data-model/logic assertions only.
