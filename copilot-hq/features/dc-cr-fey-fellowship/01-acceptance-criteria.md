# Acceptance Criteria: dc-cr-fey-fellowship

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry Feats)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-encounter-rules (creature-type tags), social encounter / Diplomacy subsystem

---

## Happy Path

### Availability
- [ ] `[NEW]` Fey Fellowship is a Gnome ancestry feat (level 1); selectable at character creation and at level 1 ancestry feat slots.

### Combat/Perception Bonuses vs. Fey
- [ ] `[NEW]` +2 circumstance bonus to Perception checks against fey creatures (detects/locates fey).
- [ ] `[NEW]` +2 circumstance bonus to all saving throws against fey creatures.
- [ ] `[NEW]` Bonuses apply only when the opposing entity has the fey creature-type tag.

### Immediate Social Diplomacy
- [ ] `[NEW]` In a social encounter involving a fey creature, the character may attempt a Diplomacy check to Make an Impression immediately (1 action), without the usual 1-minute conversation requirement.
- [ ] `[NEW]` The immediate check incurs a –5 penalty.
- [ ] `[NEW]` If the immediate check fails, the character may still attempt the normal 1-minute conversation retry with no further penalty.

### Glad-Hand Interaction
- [ ] `[NEW]` If the character has the Glad-Hand skill feat AND the target is a fey creature, the –5 penalty on the immediate Diplomacy check is waived.

---

## Edge Cases
- [ ] `[NEW]` The circumstance bonuses are against "fey creatures" (fey trait on encounter entity) — not against non-fey targets in the same encounter.
- [ ] `[NEW]` Multiple circumstance bonuses vs. fey do not stack; only the highest applies.

## Failure Modes
- [ ] `[TEST-ONLY]` The –5 penalty is on the IMMEDIATE attempt only; the 1-minute retry has no penalty from this feat.
- [ ] `[TEST-ONLY]` Glad-Hand waiver applies only when target is fey; non-fey targets with Glad-Hand still incur the –5.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry feat; no new routes or user-facing input
