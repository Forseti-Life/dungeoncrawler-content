# Acceptance Criteria: dc-cr-skills-recall-knowledge

## Gap analysis reference
- DB sections: core/ch04/Occultism (Int), core/ch04/Religion (Wis), core/ch04 General Skill Actions (Recall Knowledge rows, REQs 1591–1594, 2329)
- Depends on: dc-cr-skill-system ✓, dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment

---

## Happy Path

### Recall Knowledge [1 action, Secret]
- [ ] `[EXTEND]` Recall Knowledge is a 1-action secret check; roll once and compare to GM-set DC.
- [ ] `[EXTEND]` Degrees: Crit Success = accurate info + bonus detail; Success = accurate info; Failure = nothing; Crit Failure = false info (player not told it is false).
- [ ] `[EXTEND]` Skill routing by topic: Arcana (arcane creatures/magic/planes), Crafting (item construction/artifice), Lore (specific subcategory), Medicine (diseases/poisons/anatomy), Nature (animals/plants/terrain), Occultism (metaphysics/weird philosophies/occult magic), Religion (deities/undead/divine magic), Society (cultures/laws/humanoid organizations).
- [ ] `[EXTEND]` DC resolution: simple DC based on obscurity (GM-defined); creature/hazard DCs level-based; rarity adjustment applied via dc-cr-dc-rarity-spell-adjustment rules.

### Occultism (Int)
- [ ] `[NEW]` Occultism Decipher Writing covers metaphysics, syncretic principles, and weird philosophies (see Decipher Writing AC in dc-cr-skill-system).
- [ ] `[NEW]` Occultism Identify Magic routes to occult tradition items/effects.
- [ ] `[NEW]` Occultism Learn a Spell routes to occult tradition spells.

### Religion (Wis)
- [ ] `[NEW]` Religion Decipher Writing covers religious allegories, homilies, and proverbs.
- [ ] `[NEW]` Religion Identify Magic routes to divine tradition items/effects.
- [ ] `[NEW]` Religion Learn a Spell routes to divine tradition spells.

---

## Edge Cases
- [ ] `[EXTEND]` Wrong tradition skill used for Identify Magic: +5 DC penalty applied.
- [ ] `[EXTEND]` GM-set false info on Crit Fail: player-facing output shows "you recall…" (not "you failed").

## Failure Modes
- [ ] `[TEST-ONLY]` Recall Knowledge Crit Fail: returned info appears truthful to player, not flagged as false.
- [ ] `[TEST-ONLY]` Recall Knowledge with untrained proficiency: permitted (untrained action).
- [ ] `[TEST-ONLY]` Occultism/Religion Identify Magic blocked for wrong tradition without penalty only if hardcoded routing is not in place — must apply +5 DC not block.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes or user-facing input beyond existing encounter handlers
