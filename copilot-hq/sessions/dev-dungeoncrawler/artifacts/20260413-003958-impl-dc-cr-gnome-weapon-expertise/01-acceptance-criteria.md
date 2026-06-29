# Acceptance Criteria: dc-cr-gnome-weapon-expertise

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Expertise is selectable as a Gnome feat 13 when Gnome Weapon Familiarity is present.

### Proficiency cascade
- [ ] `[NEW]` When a class feature grants expert or greater proficiency in a weapon or weapon group, the same rank is granted to glaive.
- [ ] `[NEW]` The same cascade applies to kukri.
- [ ] `[NEW]` The same cascade applies to trained gnome weapons.

---

## Edge Cases
- [ ] `[NEW]` Only gnome weapons the character is already trained in receive the cascade.
- [ ] `[NEW]` The feat responds to later class-granted proficiency upgrades instead of snapshotting only at selection time.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without Gnome Weapon Familiarity cannot select or benefit from the feat.
- [ ] `[TEST-ONLY]` Non-class proficiency changes do not incorrectly trigger the cascade.

## Security acceptance criteria
- Security AC exemption: passive proficiency event handling only; no new route surface
