# Acceptance Criteria: dc-cr-first-world-magic

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry Feats)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-gnome-heritage-wellspring (tradition-override interaction)

---

## Happy Path

### Availability
- [ ] `[NEW]` First World Magic is a Gnome ancestry feat (level 1); selectable at character creation and at level 1 ancestry feat slots.

### Fixed Primal Cantrip
- [ ] `[NEW]` Player selects one primal cantrip from the primal spell list at feat acquisition time.
- [ ] `[NEW]` Selected cantrip is fixed — cannot be swapped after acquisition (unlike Fey-touched Heritage).
- [ ] `[NEW]` Cantrip is stored as a primal innate spell castable at will.
- [ ] `[NEW]` Cantrip is automatically heightened to ceil(character_level / 2).

### Wellspring Override
- [ ] `[NEW]` If character has the Wellspring Gnome heritage, the cantrip's tradition is overridden to `character.wellspring_tradition` at the moment the feat is applied.
- [ ] `[NEW]` Override is automatic; no player action required at feat selection.

---

## Edge Cases
- [ ] `[NEW]` First World Magic and Fey-touched Heritage can both be taken (different slots); each grants a separate at-will innate cantrip.
- [ ] `[NEW]` The two cantrips may be the same spell — system must allow duplicate cantrip registrations (each is a separate innate spell record).

## Failure Modes
- [ ] `[TEST-ONLY]` The cantrip is at will (not once/day or once/encounter); no use counter applies.
- [ ] `[TEST-ONLY]` The cantrip is fixed at acquisition — no in-play or per-day swap available (swap is only for Fey-touched Heritage).

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry feat; no new routes or user-facing input
