# Acceptance Criteria: dc-cr-halfling-keen-eyes

## Gap analysis reference
- DB sections: core/ch02 (Halfling ancestry traits)
- Depends on: dc-cr-halfling-ancestry

---

## Happy Path

### Seek bonus
- [ ] `[NEW]` Halfling characters gain a +2 circumstance bonus on Seek checks against hidden or undetected creatures within 30 feet.

### Flat-check reduction
- [ ] `[NEW]` Against concealed targets, the targeting flat-check DC is reduced from 5 to 3 for halflings.
- [ ] `[NEW]` Against hidden targets, the targeting flat-check DC is reduced from 11 to 9 for halflings.

### Automatic grant
- [ ] `[NEW]` Keen Eyes is granted automatically as part of the halfling ancestry and does not require a separate selection.

---

## Edge Cases
- [ ] `[NEW]` The Seek bonus does not apply beyond 30 feet.
- [ ] `[NEW]` Flat-check reductions apply only to concealed/hidden targets, not to undetected or observed targets.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-halfling characters use the default flat-check DCs.
- [ ] `[TEST-ONLY]` Halflings do not get the Seek bonus when the target is outside 30 feet.

## Security acceptance criteria
- Security AC exemption: passive ancestry trait only; no new route surface
