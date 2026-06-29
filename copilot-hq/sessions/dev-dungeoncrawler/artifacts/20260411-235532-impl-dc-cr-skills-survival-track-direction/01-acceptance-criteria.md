# Acceptance Criteria: dc-cr-skills-survival-track-direction

## Gap analysis reference
- DB sections: core/ch04/Survival (Wis) (REQs 1730–1737)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Sense Direction [Exploration]
- [ ] `[NEW]` Sense Direction is a free exploration activity; determines cardinal direction and rough location.
- [ ] `[NEW]` No check required in clear conditions; check required in supernatural darkness, magical fog, or featureless planes.
- [ ] `[NEW]` Critical Success: also senses approximate distance from home settlement or last known landmark.

### Cover Tracks [Exploration, Trained]
- [ ] `[NEW]` Cover Tracks is an exploration activity requiring Trained Survival; character moves at half speed.
- [ ] `[NEW]` Pursuers tracking the character's path must succeed at a Survival check vs the character's Stealth or Survival DC.

### Track [Exploration, Trained]
- [ ] `[NEW]` Track is an exploration activity requiring Trained Survival.
- [ ] `[NEW]` DC determined by how old the trail is and environmental conditions.
- [ ] `[NEW]` Degrees: Crit Success = fast movement + full info; Success = follow at half speed; Failure = no progress (can retry in same area); Crit Failure = lost track, cannot retry that specific trail.

---

## Edge Cases
- [ ] `[NEW]` Cover Tracks and Track in the same area: Track DC equals Cover Tracks result.
- [ ] `[NEW]` Track untrained: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Track Crit Fail: trail permanently lost (cannot retry that specific track).
- [ ] `[TEST-ONLY]` Cover Tracks without Trained Survival: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration handlers
