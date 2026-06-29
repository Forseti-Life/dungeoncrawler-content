# Acceptance Criteria: dc-cr-gnome-weapon-familiarity

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Familiarity is selectable as a Gnome feat 1.

### Granted proficiencies
- [ ] `[NEW]` Selecting the feat grants trained proficiency with glaive.
- [ ] `[NEW]` Selecting the feat grants trained proficiency with kukri.
- [ ] `[NEW]` Selecting the feat grants access to uncommon gnome weapons.

### Proficiency remap
- [ ] `[NEW]` Martial gnome weapons count as simple weapons for proficiency calculation.

---

## Edge Cases
- [ ] `[NEW]` The remap applies only to gnome-tagged martial weapons.
- [ ] `[NEW]` The feat does not automatically equip any weapons.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-gnome characters cannot select the feat.
- [ ] `[TEST-ONLY]` Gnome Weapon Specialist and Gnome Weapon Expertise remain gated until Gnome Weapon Familiarity is present.

## Security acceptance criteria
- Security AC exemption: feat + proficiency data only; no new route surface
