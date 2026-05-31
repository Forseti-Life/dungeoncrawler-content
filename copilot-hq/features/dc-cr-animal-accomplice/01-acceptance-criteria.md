# Acceptance Criteria: dc-cr-animal-accomplice

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-familiar

---

## Happy Path

### Availability
- [ ] `[NEW]` Animal Accomplice is selectable as a Gnome feat 1.

### Familiar grant
- [ ] `[NEW]` Selecting the feat grants a familiar using the standard familiar rules.
- [ ] `[NEW]` The familiar can be chosen from the normal familiar type catalog.

---

## Edge Cases
- [ ] `[NEW]` Burrow-speed animals may be recommended in UI copy but are not mandatory.
- [ ] `[NEW]` Non-spellcasting gnome characters can still receive the familiar from this feat.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without the feat do not gain a familiar through this path.
- [ ] `[TEST-ONLY]` Invalid familiar types are rejected by the selection flow.

## Security acceptance criteria
- Security AC exemption: familiar grant/configuration only; no new route surface
