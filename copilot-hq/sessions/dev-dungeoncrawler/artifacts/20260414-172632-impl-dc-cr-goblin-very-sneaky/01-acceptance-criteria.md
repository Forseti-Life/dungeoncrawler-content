# Acceptance Criteria: dc-cr-goblin-very-sneaky

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry feats)
- Depends on: dc-cr-goblin-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-skills-stealth-hide-sneak

---

## Happy Path

### Availability
- [ ] `[NEW]` Very Sneaky is selectable as a Goblin ancestry feat at level 1.

### Sneak movement bonus
- [ ] `[NEW]` A character with Very Sneaky can move 5 feet farther when using Sneak, up to their Speed.

### End-of-turn visibility rule
- [ ] `[NEW]` If the character continues using Sneak actions, succeeds at the Stealth check, and ends their turn with cover/greater cover/concealment, they do not become Observed even if they lacked it at the end of the Sneak action.

---

## Edge Cases
- [ ] `[NEW]` The bonus never allows movement beyond the character's Speed cap for the Sneak action.
- [ ] `[NEW]` The delayed visibility protection applies only if the character still ends the turn with cover or concealment.

## Failure Modes
- [ ] `[TEST-ONLY]` Failing the Sneak check still resolves normal visibility rules.
- [ ] `[TEST-ONLY]` Characters without the feat use the default Sneak distance and visibility timing.

## Security acceptance criteria
- Security AC exemption: action-resolution adjustment only; no new routes or data entry surface
