# Acceptance Criteria: dc-cr-goblin-weapon-frenzy

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-goblin-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Goblin Weapon Frenzy is selectable as a Goblin feat 5 when Goblin Weapon Familiarity is already present.

### Critical specialization trigger
- [ ] `[NEW]` On a critical hit with a goblin weapon, the appropriate weapon critical specialization effect is applied.
- [ ] `[NEW]` The effect works for `dogslicer`, `horsechopper`, and other goblin-tagged weapons that the character is proficient with.

---

## Edge Cases
- [ ] `[NEW]` Critical hits with non-goblin weapons do not trigger Goblin Weapon Frenzy.
- [ ] `[NEW]` The feature piggybacks on the existing critical specialization lookup instead of duplicating weapon-effect logic.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters missing Goblin Weapon Familiarity cannot select or use Goblin Weapon Frenzy.
- [ ] `[TEST-ONLY]` A normal hit does not trigger a specialization effect from this feat.

## Security acceptance criteria
- Security AC exemption: combat-resolution hook only; no new route surface
