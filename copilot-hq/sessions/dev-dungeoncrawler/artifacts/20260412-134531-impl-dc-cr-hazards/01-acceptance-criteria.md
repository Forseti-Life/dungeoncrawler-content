# Acceptance Criteria: dc-cr-hazards

## Gap analysis reference
- DB sections: core/ch10/Hazards (22 reqs)
- Depends on: dc-cr-encounter-rules ✓, dc-cr-conditions ✓

---

## Happy Path

### Detection
- [ ] `[NEW]` Hazards have a Stealth DC for detection.
- [ ] `[NEW]` Hazards without minimum proficiency: all PCs auto-roll secret Perception vs Stealth DC when entering area.
- [ ] `[NEW]` Hazards with minimum proficiency: only actively Searching characters of qualifying proficiency rank attempt the check.
- [ ] `[NEW]` Detect Magic can reveal magical hazards (without min proficiency requirement) but does not allow disabling.

### Triggering
- [ ] `[NEW]` Passive triggers (floor plate, sensor): activate automatically if not detected before triggering condition.
- [ ] `[NEW]` Active triggers (opening door): activate only if PC explicitly takes the triggering action.

### Simple vs Complex Hazards
- [ ] `[NEW]` Simple hazard: has one reaction; effect resolves in one step.
- [ ] `[NEW]` Complex hazard: reaction starts encounter/initiative; performs a routine each round.
- [ ] `[NEW]` Complex hazard routine: preprogrammed set of actions per round (as specified in stat block).
- [ ] `[NEW]` If PCs already in encounter mode when complex hazard triggers: hazard enters at its own initiative (using its Stealth modifier).

### Disabling
- [ ] `[NEW]` Disable a Device action: 2 actions, skill check vs hazard's disable DC.
- [ ] `[NEW]` Critical failure on any disable attempt: triggers the hazard.
- [ ] `[NEW]` Complex hazards may require multiple successes; critical success = two successes on one component.
- [ ] `[NEW]` Minimum proficiency may apply to disabling (same as detecting requirement).
- [ ] `[NEW]` Character must have detected (or been told about) the hazard to attempt disabling.
- [ ] `[NEW]` Reset: hazards may auto-reset after specified time or require manual reset per stat block.

### Hazard Stats
- [ ] `[NEW]` Hazards have AC, saving throw modifiers, Hardness, HP, and Broken Threshold in stat block.
- [ ] `[NEW]` Hazard at or below BT: broken (non-functional, repairable). At 0 HP: destroyed.
- [ ] `[NEW]` Hitting a hazard typically triggers it unless the attack destroys it outright.
- [ ] `[NEW]` Hazards immune to effects that can't target objects unless stat block specifies otherwise.

### Magical Hazards
- [ ] `[NEW]` Magical hazards have a spell level and counteract DC; counteract per ch09 rules.
- [ ] `[NEW]` Crit fail on counteract: triggers the hazard.

### XP
- [ ] `[NEW]` Hazard XP awarded on overcoming (disable, avoid, or endure); awarded only once per hazard per party.
- [ ] `[NEW]` Hazard XP table: simple hazards award less XP than complex hazards; scales by hazard level vs party level.

---

## Edge Cases
- [ ] `[NEW]` Hazard triggered before detection: PCs take full effect; no save from detection.
- [ ] `[NEW]` Broken hazard: cannot activate; may still be repairable.

## Failure Modes
- [ ] `[TEST-ONLY]` Disable attempt with insufficient proficiency (minimum proficiency hazard): blocked (no roll allowed).
- [ ] `[TEST-ONLY]` Crit fail on disable: hazard triggers immediately.
- [ ] `[TEST-ONLY]` XP awarded twice for same hazard: blocked.

## Security acceptance criteria
- Security AC exemption: game-mechanic hazard system; no new routes or user-facing input beyond existing encounter and exploration handlers
