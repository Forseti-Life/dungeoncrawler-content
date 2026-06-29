# Acceptance Criteria: dc-cr-burrow-elocutionist

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Burrow Elocutionist is selectable as a Gnome ancestry feat at level 1.

### Communication effect
- [ ] `[NEW]` Character can communicate with burrowing creatures using the feat's special language effect.
- [ ] `[NEW]` Burrowing creatures can answer questions in a way the character understands.

---

## Edge Cases
- [ ] `[NEW]` The effect applies only to creatures tagged as burrowing creatures.
- [ ] `[NEW]` The feat grants communication, not general language fluency with all animals.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-burrowing creatures do not gain a dialogue channel from this feat.
- [ ] `[TEST-ONLY]` Characters without the feat cannot use the special burrow-language interaction.

## Security acceptance criteria
- Security AC exemption: interaction capability flag only; no new route surface
