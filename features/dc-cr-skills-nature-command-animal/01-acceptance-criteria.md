# Acceptance Criteria: dc-cr-skills-nature-command-animal

## Gap analysis reference
- DB sections: core/ch04/Nature (Wis) (REQs 1699–1703)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Nature)
- [ ] `[NEW]` Nature covers: animals, beasts, fey, plants, fungi, weather, terrain, natural environments, creatures from nature categories.
- [ ] `[NEW]` Untrained use permitted for Recall Knowledge.

### Command an Animal [1 action, Auditory, Trained]
- [ ] `[NEW]` Command an Animal costs 1 action, requires Trained Nature.
- [ ] `[NEW]` Target must be an animal (not magical beast, not undead, not mindless).
- [ ] `[NEW]` Commands must be simple and within an animal's capabilities.
- [ ] `[NEW]` DC = Will save of the target animal; untrained animals default to passive behavior.
- [ ] `[NEW]` Degrees: Crit Success = two actions this turn + cooperative for 1 minute; Success = one action this round; Failure = no action; Crit Failure = animal panics (runs/attacks).
- [ ] `[NEW]` Trained/bonded animals (from Handle Animal or companion rules) use a lower DC.

---

## Edge Cases
- [ ] `[NEW]` Command a non-animal creature (beast, mindless): blocked.
- [ ] `[NEW]` Command requires auditory (mute or deaf characters may have penalty/blocked depending on implementation).

## Failure Modes
- [ ] `[TEST-ONLY]` Crit Fail: animal panics; it does not simply comply.
- [ ] `[TEST-ONLY]` Command an Animal untrained: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter handlers
