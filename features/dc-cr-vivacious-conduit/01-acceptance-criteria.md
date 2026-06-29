# Acceptance Criteria: dc-cr-vivacious-conduit

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-rest-watch-starvation, dc-cr-skills-medicine-actions

---

## Happy Path

### Availability
- [ ] `[NEW]` Vivacious Conduit is selectable as a Gnome feat 9.

### Rest-based healing
- [ ] `[NEW]` After a 10-minute rest, the character regains HP equal to Constitution modifier × half level.
- [ ] `[NEW]` The healing stacks with Treat Wounds rather than replacing it.

---

## Edge Cases
- [ ] `[NEW]` Half level uses the system's standard rounding rule for the feat and stays consistent across all levels.
- [ ] `[NEW]` Negative Constitution modifiers do not produce negative healing.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without the feat receive only the baseline rest/Treat Wounds healing.
- [ ] `[TEST-ONLY]` The bonus is not applied outside a valid 10-minute rest window.

## Security acceptance criteria
- Security AC exemption: passive healing adjustment only; no new route surface
