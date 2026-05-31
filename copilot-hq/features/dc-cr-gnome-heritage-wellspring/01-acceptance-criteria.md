# Acceptance Criteria: dc-cr-gnome-heritage-wellspring

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-first-world-magic (tradition-override interaction)

---

## Happy Path

### Tradition Selection
- [ ] `[NEW]` At heritage selection, player chooses one non-primal magical tradition: arcane, divine, or occult.
- [ ] `[NEW]` Chosen tradition is stored in `character.wellspring_tradition` (persistent character field).

### At-Will Cantrip from Chosen Tradition
- [ ] `[NEW]` Player selects one cantrip from the chosen tradition's spell list at heritage selection.
- [ ] `[NEW]` Cantrip is stored as an at-will innate spell using the chosen tradition (not primal).
- [ ] `[NEW]` Cantrip is automatically heightened to ceil(character_level / 2).

### Tradition Override for Gnome Ancestry Feats
- [ ] `[NEW]` Any primal innate spell gained from a gnome ancestry feat (e.g., First World Magic) is overridden to the `wellspring_tradition` at the time the feat is applied.
- [ ] `[NEW]` The override is automatic — no player action required at feat selection.
- [ ] `[NEW]` Override applies to future feats as well (all gnome ancestry feats with innate primal spells).

---

## Edge Cases
- [ ] `[NEW]` Primal tradition choice is not available; player must choose arcane, divine, or occult.
- [ ] `[NEW]` If `wellspring_tradition` changes (not normally possible without a character rebuild), all gnome-ancestry innate spells re-override to the new value.

## Failure Modes
- [ ] `[TEST-ONLY]` Cantrip is at-will (not once/day); tradition override is on the spell's tradition tag, not the cantrip casting frequency.
- [ ] `[TEST-ONLY]` Override applies to gnome ancestry feat innate spells ONLY — not to class spells or non-gnome-ancestry innate spells.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input beyond character creation
