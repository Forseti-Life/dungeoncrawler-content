# Acceptance Criteria: dc-cr-skills-acrobatics-actions

## Gap analysis reference
- DB sections: core/ch04/Acrobatics (Dex) (REQs 1602–1614)
- Depends on: dc-cr-skill-system ✓, dc-cr-skills-calculator-hardening (planned)

---

## Happy Path

### Escape (Acrobatics alternative)
- [ ] `[EXTEND]` Escape action accepts Acrobatics modifier as an alternative to unarmed attack modifier; player selects which to use.

### Balance [1 action, Move]
- [ ] `[NEW]` Balance is a 1-action move action; character is flat-footed during the action.
- [ ] `[NEW]` Balance degrees of success: Critical Success = move at full Speed; Success = move at full Speed (counts as difficult terrain for tracking purposes); Failure = movement stops or character falls prone; Critical Failure = character falls prone and turn ends.
- [ ] `[NEW]` Sample DCs applied: Untrained (roots/cobblestones), Trained (wooden beam), Expert (gravel), Master (tightrope), Legendary (razor edge).
- [ ] `[NEW]` Balance requires a surface to balance on; attempting in midair without flight is blocked.

### Tumble Through [1 action, Move]
- [ ] `[NEW]` Tumble Through allows movement through an occupied enemy space (must enter their space to trigger the check).
- [ ] `[NEW]` Can substitute Climb/Fly/Swim for Stride in appropriate environments (e.g., underwater, aerial combat).
- [ ] `[NEW]` Success = character passes through (space counts as difficult terrain); Failure = movement stops and enemy reactions trigger.

### Maneuver in Flight [1 action, Move, Trained]
- [ ] `[NEW]` Maneuver in Flight requires a fly Speed AND Trained Acrobatics; blocked otherwise.
- [ ] `[NEW]` Sample DCs applied: Trained (steep ascent/descent), Expert (against wind or hover), Master (reverse direction), Legendary (gale force winds).
- [ ] `[NEW]` Failure on Maneuver in Flight triggers a Reflex save or the character falls.

### Squeeze [Exploration, Trained]
- [ ] `[NEW]` Squeeze is an exploration activity; requires Trained Acrobatics; speed through tight space = 1 min/5 ft (Critical Success: 1 min/10 ft).
- [ ] `[NEW]` Critical Failure: character becomes stuck; they can escape with a follow-up Squeeze check (any non-critical-failure result frees them).
- [ ] `[NEW]` Sample DCs applied: Trained (barely fits shoulders), Master (barely fits head).

---

## Edge Cases

- [ ] `[NEW]` Balance check not called when character has sure footing (e.g., on normal flat ground).
- [ ] `[NEW]` Tumble Through vs immovable/incorporeal enemies: GM determines applicability; system presents check.
- [ ] `[NEW]` Maneuver in Flight requires active fly speed (not just jump or levitate).

---

## Failure Modes

- [ ] `[TEST-ONLY]` Maneuver in Flight blocked for characters without fly speed or untrained Acrobatics.
- [ ] `[TEST-ONLY]` Squeeze blocked for characters with untrained Acrobatics.
- [ ] `[TEST-ONLY]` Balance Critical Failure: both prone AND turn-ending trigger correctly.
- [ ] `[TEST-ONLY]` Stuck character from Squeeze critical failure: movement through that space blocked until freed.

---

## Security acceptance criteria

- Security AC exemption: skill action logic; no new routes or user-facing input beyond existing encounter/exploration phase handlers
