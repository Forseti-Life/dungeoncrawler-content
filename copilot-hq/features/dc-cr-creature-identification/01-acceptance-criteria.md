# Acceptance Criteria: dc-cr-creature-identification

## Gap analysis reference
- DB sections: core/ch10/Creature Identification (1 req)
- Depends on: dc-cr-skill-system ✓, dc-cr-recall-knowledge

---

## Happy Path
- [ ] `[NEW]` Recall Knowledge skill routing by creature trait:
  - Aberrations, constructs, humanoids, oozes → Arcana
  - Animals, beasts, fungi, plants → Nature
  - Celestials, fiends, monitors, undead → Religion
  - Dragons, elementals → Arcana or Nature
  - Fey → Nature or Occultism
  - Spirits, creatures of spiritual origin → Occultism
  - Any creature → appropriate Lore subcategory (GM-adjudicated)
- [ ] `[NEW]` Multiple applicable skills allowed; character may use any one they are trained in (or untrained for untrained Recall Knowledge).
- [ ] `[NEW]` DC resolution for creatures: level-based DC + rarity adjustment (see dc-cr-dc-rarity-spell-adjustment).
- [ ] `[NEW]` Degrees: Crit Success = all standard info + bonus fact; Success = standard creature info; Failure = no info; Crit Failure = false info presented as true.

## Edge Cases
- [ ] `[NEW]` Creature type not in mapping: defaults to GM Lore check.
- [ ] `[NEW]` Crit Fail: system presents false info (player does not see a "failed" indicator).

## Failure Modes
- [ ] `[TEST-ONLY]` Wrong skill used to identify: if untrained in correct skill but not wrong skill, still must use a valid skill.

## Security acceptance criteria
- Security AC exemption: game-mechanic skill routing; no new routes or user-facing input beyond existing encounter handlers
