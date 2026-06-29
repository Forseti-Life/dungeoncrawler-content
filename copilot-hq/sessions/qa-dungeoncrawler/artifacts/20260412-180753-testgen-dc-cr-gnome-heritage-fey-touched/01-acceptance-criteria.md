# Acceptance Criteria: dc-cr-gnome-heritage-fey-touched

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-gnome-heritage-wellspring (tradition override path)

---

## Happy Path

### Traits
- [ ] `[NEW]` Character gains the fey trait in addition to Gnome and Humanoid.

### At-Will Primal Cantrip
- [ ] `[NEW]` Player selects one cantrip from the primal spell list at heritage selection time.
- [ ] `[NEW]` Cantrip is stored on the character record as a primal innate spell castable at will.
- [ ] `[NEW]` Cantrip is automatically heightened to a spell level equal to ceil(character_level / 2).

### Daily Cantrip Swap
- [ ] `[NEW]` Character can swap the selected cantrip once per day via a 10-minute activity tagged with the concentrate trait.
- [ ] `[NEW]` Replacement cantrip must be chosen from the primal spell list.
- [ ] `[NEW]` Swap resets at daily preparation (24-hour period / long rest).

### Wellspring Override Integration
- [ ] `[NEW]` If character also has Wellspring Gnome heritage (not normally stacked — but tradition-override flag applies if a multiclass/variant rule grants both), the cantrip tradition is overridden to the Wellspring tradition.

---

## Edge Cases
- [ ] `[NEW]` fey trait adds to character traits — does NOT replace Gnome or Humanoid.
- [ ] `[NEW]` Only one cantrip swap allowed per day; attempting a second swap in the same day is blocked with a system message.

## Failure Modes
- [ ] `[TEST-ONLY]` Cantrip is at-will (not once/day); the daily-swap is the limited action, not the casting itself.
- [ ] `[TEST-ONLY]` Heightening must be dynamic — if character level increases, the cantrip level re-calculates automatically.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input beyond character creation
