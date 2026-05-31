# Acceptance Criteria: dc-cr-gnome-weapon-specialist

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Specialist is selectable as a Gnome feat 5 when Gnome Weapon Familiarity is present.

### Critical specialization trigger
- [ ] `[NEW]` Critical hits with glaive apply the weapon's critical specialization effect.
- [ ] `[NEW]` Critical hits with kukri apply the weapon's critical specialization effect.
- [ ] `[NEW]` Critical hits with other gnome-tagged weapons apply the weapon's critical specialization effect.

---

## Edge Cases
- [ ] `[NEW]` Non-gnome weapons do not trigger the feat.
- [ ] `[NEW]` The feat reuses the existing critical-specialization lookup rather than duplicating weapon-effect logic.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters missing Gnome Weapon Familiarity cannot select or use the feat.
- [ ] `[TEST-ONLY]` Normal hits do not trigger the specialization effect.

## Security acceptance criteria
- Security AC exemption: combat hook only; no new route surface
