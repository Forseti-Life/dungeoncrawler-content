# Acceptance Criteria: dc-cr-class-rogue

## Gap analysis reference
- DB sections: core/ch03/Rogue (REQs 1392–1457)
- Track B: no existing RogueService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-conditions (in-progress Release B)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Rogue exists as a selectable playable class with DEX as default key ability boost at level 1 (racket may allow STR or CHA instead).
- [ ] `[NEW]` Rogue HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Trained Perception; Expert Reflex AND Expert Will at level 1 (unique double Expert saves), Trained Fortitude; Stealth + racket skills + 7 + INT additional skills; Trained simple weapons, hand crossbow, rapier, sap, shortbow, shortsword; Trained light armor.
- [ ] `[NEW]` Rogue gains a skill feat every level (not just every 2 levels); skill increases every level from 2nd onward.

### Rogue's Racket (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects one Racket: Ruffian, Scoundrel, or Thief.
- [ ] `[NEW]` Ruffian: sneak attack with any simple weapon (not just agile/finesse); crit hit + flat-footed target also applies weapon critical specialization (simple weapons with die ≤ d8); Trained Intimidation and medium armor; can choose STR as key ability.
- [ ] `[NEW]` Scoundrel: successful Feint → target flat-footed against your melee attacks until end of next turn (critical success: flat-footed to ALL melee); Trained Deception and Diplomacy; can choose CHA as key ability.
- [ ] `[NEW]` Thief: finesse melee weapon attacks can use DEX modifier for damage instead of STR; Trained Thievery.

### Sneak Attack (Level 1)
- [ ] `[NEW]` Sneak attack adds precision damage only when the target is flat-footed.
- [ ] `[NEW]` Sneak attack precision damage is ineffective against creatures without vital organs or weak points.
- [ ] `[NEW]` Sneak attack dice scale with level: 1d6 at level 1, +1d6 every 4 levels.

### Surprise Attack (Level 1)
- [ ] `[NEW]` When rolling initiative using Deception or Stealth, creatures that haven't acted yet are flat-footed against the rogue for the first round.

### Debilitations (unlocked via feats)
- [ ] `[NEW]` Debilitations are mutually exclusive: applying a new debilitation replaces the previous one.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Feat progression: class feat at level 1 and every even level (2, 4, 6…20); general feats at 3, 7, 11, 15, 19; skill feats every level (not just even); ancestry feats at 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

---

## Edge Cases

- [ ] `[NEW]` Ruffian sneak attack weapon restriction: d10/d12 simple weapons (e.g., greatclub) do NOT qualify for crit specialization bonus — only ≤d8.
- [ ] `[NEW]` Thief DEX-to-damage: applies only to finesse melee weapons, not ranged or non-finesse.
- [ ] `[NEW]` Scoundrel Feint result tracking: flat-footed status from Feint correctly expires at end of the rogue's next turn.
- [ ] `[NEW]` Sneak attack vs immune targets: damage correctly suppressed for creatures without vital anatomy (oozes, constructs, etc.).
- [ ] `[NEW]` Debilitation replacement: new application immediately ends previous; no stacking.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Sneak attack without flat-footed condition: precision damage not applied.
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Key ability override blocked if racket doesn't permit it.
- [ ] `[TEST-ONLY]` Skill feat cadence: rogue receives one per level (not every other level).

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
