# Acceptance Criteria: dc-cr-skills-calculator-hardening

## Gap analysis reference
- DB sections: core/ch04 General Skill Actions (REQs 1554–1568, 1600, 2323) — enforcement gaps
- Depends on: dc-cr-skill-system ✓, dc-cr-character-leveling

---

## Happy Path

### Trained-Only Action Gating
- [ ] `[EXTEND]` `calculateSkillCheck()` returns an error/blocked result when an untrained character attempts a trained-only action.
- [ ] `[EXTEND]` Error message clearly states the character is untrained in the required skill.

### Proficiency Rank Ceiling Enforcement
- [ ] `[EXTEND]` `submitSkillIncrease()` enforces level ≥ 7 before allowing Expert → Master increase.
- [ ] `[EXTEND]` `submitSkillIncrease()` enforces level ≥ 15 before allowing Master → Legendary increase.
- [ ] `[EXTEND]` Blocked increase returns clear error, does not silently no-op.

### Armor Check Penalty
- [ ] `[EXTEND]` `calculateSkillCheck()` applies the character's armor check penalty to Strength- and Dexterity-based skill rolls.
- [ ] `[EXTEND]` Armor check penalty is NOT applied to attack-trait actions (e.g., Grapple, Trip, Disarm when used as attack actions).
- [ ] `[EXTEND]` Zero armor check penalty for unarmored characters = no penalty applied.

---

## Edge Cases
- [ ] `[EXTEND]` Expert → Master below level 7: blocked with clear level-requirement error.
- [ ] `[EXTEND]` Master → Legendary below level 15: blocked with clear level-requirement error.

## Failure Modes
- [ ] `[TEST-ONLY]` Untrained character attempting trained-only action: blocked (not silently degraded).
- [ ] `[TEST-ONLY]` Armor check penalty on Dex-based attack trait action: NOT applied.
- [ ] `[TEST-ONLY]` Rank ceiling bypass via direct API call: still enforced server-side.

## Security acceptance criteria
- Security AC exemption: internal calculator service hardening; no new routes or user-facing input beyond existing character creation and leveling forms
