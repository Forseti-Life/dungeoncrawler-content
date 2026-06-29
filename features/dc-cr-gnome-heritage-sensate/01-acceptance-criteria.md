# Acceptance Criteria: dc-cr-gnome-heritage-sensate

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-darkvision (shared sense framework), encounter system (wind-direction model)

---

## Happy Path

### Imprecise Scent Sense
- [ ] `[NEW]` Sensate Gnome has imprecise scent with 30-foot base range.
- [ ] `[NEW]` Sense type is "imprecise" — the creature's position is approximated, not pinpointed.

### Wind Direction Modifier
- [ ] `[NEW]` Range is doubled (60 ft) when the creature is downwind from the character.
- [ ] `[NEW]` Range is halved (15 ft) when the creature is upwind from the character.
- [ ] `[NEW]` System models at minimum a binary wind state (upwind / downwind / neutral) per encounter.

### Perception Bonus vs. Undetected
- [ ] `[NEW]` +2 circumstance bonus to Perception checks specifically to locate an undetected creature within scent range.
- [ ] `[NEW]` Bonus does not apply to Perception checks outside scent range.

---

## Edge Cases
- [ ] `[NEW]` Imprecise scent does not grant the ability to precisely locate invisible creatures — only narrows position to a square.
- [ ] `[NEW]` If the encounter has no wind-direction model, treat range as base 30 ft (neutral; apply no modifier).

## Failure Modes
- [ ] `[TEST-ONLY]` Perception bonus is conditional on scent range — does not apply to all Perception checks.
- [ ] `[TEST-ONLY]` Wind halving applies upwind (not downwind); doubling applies downwind (not upwind).

## Security acceptance criteria
- Security AC exemption: game-mechanic sense; no new routes or user-facing input
