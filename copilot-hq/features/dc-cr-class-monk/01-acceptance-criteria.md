# Acceptance Criteria: dc-cr-class-monk

## Gap analysis reference
- DB sections: core/ch03/Monk (REQs 1256–1323)
- Track B: no existing MonkService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Monk exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Monk HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Untrained all armor, Expert unarmored defense.
- [ ] `[NEW]` Monk fist base damage = 1d6 (not 1d4); no lethal/nonlethal penalty on unarmed attacks.

### Flurry of Blows (Level 1)
- [ ] `[NEW]` Flurry of Blows is a 1-action ability that makes two unarmed Strikes; usable once per turn.
- [ ] `[NEW]` MAP increases normally after Flurry of Blows (both attacks count).

### Ki Spells & Focus Pool
- [ ] `[NEW]` Ki spells are focus spells; focus pool starts at 1 Focus Point (max 3 with feats); Wisdom is spellcasting ability.
- [ ] `[NEW]` Ki spell feats (Ki Rush, Ki Strike, etc.) each grant +1 Focus Point when taken.

### Stance Unarmed Attacks
- [ ] `[NEW]` Each stance feat provides unique unarmed attack profiles (damage die, traits) that replace or supplement base unarmed attacks while the stance is active.
- [ ] `[NEW]` Stance restriction: only one stance active at a time; entering a new stance ends the previous.
- [ ] `[NEW]` Mountain Stance: +4 item bonus to AC; +2 circumstance vs Shove/Trip; DEX cap to AC = +0; –5 ft Speed; requires touching ground. Item AC stacks with armor potency runes on mage armor / explorer's clothing.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every even level; ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Flurry of Blows when only one unarmed attack remains MAP-viable: both strikes still attempted; second may have MAP penalty.
- [ ] `[NEW]` Stunning Fist (requires Flurry of Blows): Fortitude vs class DC check only when BOTH flurry strikes hit same creature; incapacitation rules applied correctly vs higher-level creatures.
- [ ] `[NEW]` Ki spells require active focus points; casting with 0 FP is blocked.
- [ ] `[NEW]` Mountain Stance DEX cap of +0: correctly overrides character DEX bonus to AC even with bonuses from other sources.
- [ ] `[NEW]` Fuse Stance (level 20): correctly grants all effects of both selected stances simultaneously.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Armor equip blocked: monk cannot wear non-explorer's-clothing armor without explicitly training via feat.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Flurry of Blows usable only once per turn (second use blocked).
- [ ] `[TEST-ONLY]` Two stances simultaneously: not permitted (without Fuse Stance feat).

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
