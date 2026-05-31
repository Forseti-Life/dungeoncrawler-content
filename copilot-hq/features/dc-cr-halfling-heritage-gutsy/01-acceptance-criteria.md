# Acceptance Criteria: dc-cr-halfling-heritage-gutsy

## Gap analysis reference
- DB sections: core/ch02 (Halfling heritages)
- Depends on: dc-cr-halfling-ancestry, dc-cr-heritage-system

---

## Happy Path

### Heritage selection
- [ ] `[NEW]` Gutsy Halfling is selectable as a halfling heritage at character creation.

### Emotion save upgrade
- [ ] `[NEW]` When a Gutsy Halfling rolls a success on a saving throw against an emotion effect, the result upgrades to a critical success.

---

## Edge Cases
- [ ] `[NEW]` The upgrade applies only to effects with the `emotion` trait.
- [ ] `[NEW]` A critical success remains a critical success; the heritage does not create a new higher result tier.

## Failure Modes
- [ ] `[TEST-ONLY]` A failed or critically failed save is not improved by this heritage.
- [ ] `[TEST-ONLY]` Non-emotion effects resolve with normal save outcomes.

## Security acceptance criteria
- Security AC exemption: passive heritage resolution only; no new route surface
