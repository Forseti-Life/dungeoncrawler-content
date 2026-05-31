# Acceptance Criteria: dc-cr-skills-arcana-borrow-spell

## Gap analysis reference
- DB sections: core/ch04/Arcana (Int) (REQs 1615–1618)
- Depends on: dc-cr-skill-system ✓, dc-cr-spellcasting (deferred)

---

## Happy Path

- [ ] `[NEW]` Arcana covers arcane magic knowledge, arcane creature identification, and planar lore (Elemental, Astral, Shadow planes).
- [ ] `[NEW]` Untrained characters can use Arcana to Recall Knowledge about arcane topics.
- [ ] `[NEW]` Borrow an Arcane Spell is an exploration activity requiring Trained Arcana AND the character must be an arcane prepared spellcaster (spellbook user).
- [ ] `[NEW]` Success: borrowed spell can be prepared normally in an open slot. Failure: slot remains open and retry is blocked until next preparation cycle.

## Edge Cases
- [ ] `[NEW]` Borrow an Arcane Spell blocked for untrained characters and non-arcane-prepared spellcasters.

## Failure Modes
- [ ] `[TEST-ONLY]` Borrow Arcane Spell retry blocked until next prep on failure.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration handlers
