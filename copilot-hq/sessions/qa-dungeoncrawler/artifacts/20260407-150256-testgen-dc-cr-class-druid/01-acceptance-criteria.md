# Acceptance Criteria: dc-cr-class-druid

## Gap analysis reference
- DB sections: core/ch03/Druid (REQs 1116–1171)
- Track B: no existing DruidService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships), dc-cr-animal-companion (planned)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Druid exists as a selectable playable class with Wisdom as key ability boost at level 1.
- [ ] `[NEW]` Druid HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Will, Trained Fortitude and Reflex; Trained light and medium armor (metal armor and shields forbidden); Trained primal spell attacks/DCs (Wisdom).
- [ ] `[NEW]` Druid automatically knows Druidic language at level 1.
- [ ] `[NEW]` Wild Empathy: Druid can use Diplomacy on animals.

### Universal Anathema
- [ ] `[NEW]` Metal armor and shields cannot be equipped by Druid characters; system prevents equipping or provides a blocking warning.
- [ ] `[NEW]` Teaching Druidic to non-druids flagged as anathema; all anathema violations remove primal spellcasting and order benefits until atone ritual completed.

### Druidic Order (Subclass)
- [ ] `[NEW]` At level 1, player selects one Order: Animal, Leaf, Storm, or Wild.
- [ ] `[NEW]` Each order grants one order focus spell; Leaf and Storm orders start with 2 Focus Points (others start with 1).

### Primal Spellcasting (Prepared)
- [ ] `[NEW]` Druid uses prepared primal spellcasting; must prepare spells each day.
- [ ] `[NEW]` Spell attacks and DCs scale with Wisdom modifier.
- [ ] `[NEW]` Spell slots scale per advancement table.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 19: Primal Hierophant — one 10th-level prepared primal spell slot (cannot be used with slot-manipulation features).

### Feat Progression
- [ ] `[NEW]` Druid gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Order Explorer: joining a second order grants access to its 1st-level feats; violating that order's anathema removes only those feats, not the main primal connection.
- [ ] `[NEW]` Wild Shape forms: each unlock feat adds specific forms; attempting unlocked forms that aren't taken yet is blocked.
- [ ] `[NEW]` Form Control: duration extends correctly; spell level reduction (–2, min 1) applied when metamagic is used.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Metal armor equipment blocked for druid characters.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Preparing more spells than available slots blocked.
- [ ] `[TEST-ONLY]` Focus pool at 0: order focus spells blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
