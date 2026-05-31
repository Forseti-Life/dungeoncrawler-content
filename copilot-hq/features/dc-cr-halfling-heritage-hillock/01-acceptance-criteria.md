# Acceptance Criteria: dc-cr-halfling-heritage-hillock

## Gap analysis reference
- DB sections: core/ch02 (Halfling heritages)
- Depends on: dc-cr-halfling-ancestry, dc-cr-heritage-system, dc-cr-skills-medicine-actions

---

## Happy Path

### Heritage selection
- [ ] `[NEW]` Hillock Halfling is selectable as a halfling heritage at character creation.

### Overnight recovery bonus
- [ ] `[NEW]` On overnight rest, a Hillock Halfling regains additional HP equal to character level.

### Treat Wounds snack rider
- [ ] `[NEW]` When another character Treats Wounds on a Hillock Halfling, the patient can trigger the snack rider to add HP equal to character level.

---

## Edge Cases
- [ ] `[NEW]` The snack rider applies only to Treat Wounds and not to other healing sources.
- [ ] `[NEW]` The overnight bonus stacks on top of the normal overnight recovery amount.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without Hillock Halfling receive no bonus healing.
- [ ] `[TEST-ONLY]` The snack rider cannot be applied multiple times to the same Treat Wounds result.

## Security acceptance criteria
- Security AC exemption: passive heritage/healing adjustment only; no new route surface
