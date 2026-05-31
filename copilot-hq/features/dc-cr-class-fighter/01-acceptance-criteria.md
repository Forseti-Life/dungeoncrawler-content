# Acceptance Criteria: dc-cr-class-fighter

## Gap analysis reference
- DB sections: core/ch03/Fighter (REQs 1172–1250+)
- Track B: no existing FighterService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-equipment-system (in-progress Release B)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Fighter exists as a selectable playable class with STR or DEX as key ability boost at level 1 (player chooses).
- [ ] `[NEW]` Fighter HP = 10 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Expert Fortitude and Expert Reflex, Trained Will; Expert simple and martial weapons + unarmed attacks (unique to Fighter at level 1); Trained advanced weapons; Trained all armor categories (light, medium, heavy).

### Attack of Opportunity (Level 1)
- [ ] `[NEW]` Fighter gains Attack of Opportunity at level 1 as a class feature (reaction; trigger: creature in reach uses manipulate/moves/ranged attacks/leaves a square; make a melee Strike).

### Class Feature Unlocks by Level
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20; skill feats at every even level; general feats at 3, 7, 11, 15, 19; ancestry feats at 5, 9, 13, 17.

### Key Combat Trait Rules
- [ ] `[NEW]` Press trait: can only be used when under MAP (not first action of turn); cannot be Readied; failure on a press action does not apply crit-fail effects.
- [ ] `[NEW]` Stance trait: only one stance active at a time per encounter; 1-round cooldown on stance actions; stances end on knockout/violation/end of encounter.
- [ ] `[NEW]` Flourish trait: only one flourish action per turn enforced.

### Feat Progression
- [ ] `[NEW]` Fighter gains a class feat at level 1 and every even-numbered level (2, 4, 6…20).

---

## Edge Cases

- [ ] `[NEW]` Press actions blocked when no prior attack has been made this turn (not under MAP).
- [ ] `[NEW]` Stance change: previous stance immediately ends; new stance subject to 1-round cooldown.
- [ ] `[NEW]` Attack of Opportunity: only once per triggering event; does not count toward MAP.
- [ ] `[NEW]` Power Attack: counts as 2 MAP attacks; +1 extra damage die (becomes +2 at 10th, +3 at 18th); correctly scales.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Attempting a press action before any other attack this turn: blocked.
- [ ] `[TEST-ONLY]` Two stances simultaneously: not permitted.
- [ ] `[TEST-ONLY]` Flourish limit: second flourish in same turn blocked.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
