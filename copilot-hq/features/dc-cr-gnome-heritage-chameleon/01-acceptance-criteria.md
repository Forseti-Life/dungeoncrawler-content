# Acceptance Criteria: dc-cr-gnome-heritage-chameleon

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-stealth (terrain-tag system)

---

## Happy Path

### Heritage Availability
- [ ] `[NEW]` Chameleon Gnome heritage is selectable when Gnome ancestry is chosen.

### Passive Stealth Bonus
- [ ] `[NEW]` When character is in a terrain whose color/pattern roughly matches their current coloration, +2 circumstance bonus applied to all Stealth checks.
- [ ] `[NEW]` The bonus is lost when the environment's coloration or pattern changes significantly.
- [ ] `[NEW]` The bonus is conditional: system applies it only when terrain-tag and character coloration-tag are compatible.

### Minor Color Shift (1 action)
- [ ] `[NEW]` Character can spend 1 action to make minor localized color shifts, enabling the Stealth bonus in matching terrain.

### Dramatic Color Shift
- [ ] `[NEW]` A dramatic full-body coloration change (to match different terrain) takes up to 1 hour (downtime activity).

---

## Edge Cases
- [ ] `[NEW]` Stealth bonus applies only in matching terrain — not generically in all environments.
- [ ] `[NEW]` Multiple circumstance bonuses to Stealth do not stack; only the highest applies.

## Failure Modes
- [ ] `[TEST-ONLY]` Bonus does not persist after terrain changes to non-matching environment.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input
