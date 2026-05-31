# Acceptance Criteria: dc-cr-goblin-weapon-familiarity

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Goblin Weapon Familiarity is selectable as a Goblin ancestry feat at level 1.

### Granted proficiencies
- [ ] `[NEW]` Selecting the feat grants trained proficiency with `dogslicer`.
- [ ] `[NEW]` Selecting the feat grants trained proficiency with `horsechopper`.

### Weapon access and proficiency remap
- [ ] `[NEW]` Character gains access to uncommon goblin weapons once the feat is selected.
- [ ] `[NEW]` Martial goblin weapons count as simple weapons for proficiency calculation for this character.
- [ ] `[NEW]` Advanced goblin weapons count as martial weapons for proficiency calculation for this character.

---

## Edge Cases
- [ ] `[NEW]` The proficiency remap applies only to goblin-tagged weapons; non-goblin weapon categories are unchanged.
- [ ] `[NEW]` The feat does not auto-equip any weapon; it only changes access and proficiency handling.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-goblin characters cannot select Goblin Weapon Familiarity.
- [ ] `[TEST-ONLY]` Goblin Weapon Frenzy remains unavailable unless Goblin Weapon Familiarity is present.

## Security acceptance criteria
- Security AC exemption: ancestry feat data/update only; no new route surface beyond existing character flows
