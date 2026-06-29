# Acceptance Criteria: dc-cr-class-alchemist

## Gap analysis reference
- DB sections: core/ch03/Alchemist (REQs 644–722+)
- Track B: no existing AlchemistService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-equipment-system (in-progress)

---

## Happy Path

### Identity & Role
- [ ] `[NEW]` Alchemist exists as a selectable playable class in character creation.
- [ ] `[NEW]` Alchemist class description surfaces that alchemical items (not spellcasting) define the identity.
- [ ] `[NEW]` Character creation enforces Intelligence as the Alchemist key ability score; class DC and relevant modifiers reference INT.
- [ ] `[NEW]` Alchemist starting HP = (8 + CON modifier) at level 1; HP increases by (8 + CON modifier) each subsequent level.

### Research Field (Level 1)
- [ ] `[NEW]` At level 1, player selects one research field: Bomber, Chirurgeon, or Mutagenist.
- [ ] `[NEW]` Research field selection is stored on the character and locked after level 1 (cannot be changed).
- [ ] `[NEW]` Bomber field grants: advanced alchemy produces bombs; extra bonus from Calculated Splash/Expanded Splash unlocked per feat prereqs.
- [ ] `[NEW]` Chirurgeon field grants: advanced alchemy produces healing elixirs; extra bonus to Medicine checks.
- [ ] `[NEW]` Mutagenist field grants: advanced alchemy produces mutagens; can benefit from drawbacks being ignored per higher-level features.

### Infused Reagents (Level 1)
- [ ] `[NEW]` Alchemist receives infused reagent batches per day = level + INT modifier (minimum 1).
- [ ] `[NEW]` Infused reagent count refreshes at daily preparations.
- [ ] `[NEW]` Infused reagents are consumed when using Advanced Alchemy or Quick Alchemy.

### Advanced Alchemy (Level 1)
- [ ] `[NEW]` During daily preparations, alchemist can spend infused reagent batches to create alchemical items from formula book at no monetary cost (infused items only).
- [ ] `[NEW]` Advanced Alchemy items created are limited to item level ≤ advanced alchemy level (= character level).
- [ ] `[NEW]` Advanced Alchemy items are infused: they expire at next daily preparations (nonpermanent effects end; active afflictions persist).

### Quick Alchemy (Level 1)
- [ ] `[NEW]` Quick Alchemy [one-action, manipulate]: spend 1 infused reagent batch to create one alchemical item from formula book; item is infused and temporary.
- [ ] `[NEW]` Quick Alchemy items expire at start of alchemist's next turn if not used.
- [ ] `[NEW]` Quick Alchemy can only create items of level ≤ advanced alchemy level.

### Formula Book (Level 1)
- [ ] `[NEW]` Alchemist starts with a formula book containing formulas for level-0 and level-1 alchemical items per research field starter list.
- [ ] `[NEW]` Formulas can be added to the formula book (via crafting, purchasing, or finding).
- [ ] `[NEW]` Quick Alchemy and Advanced Alchemy can only produce items in the alchemist's formula book.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 5: Field Discovery granted based on research field.
  - Bomber: each advanced alchemy batch may produce any 3 bombs (not required to be identical).
  - Chirurgeon: each advanced alchemy batch may produce any 3 healing elixirs (not identical required).
  - Mutagenist: each advanced alchemy batch may produce any 3 mutagens (not identical required).
- [ ] `[NEW]` Level 5: Powerful Alchemy — Quick Alchemy items with saving throws use alchemist's class DC instead of item's default DC.
- [ ] `[NEW]` Level 7: Alchemical Weapon Expertise — proficiency in simple weapons, alchemical bombs, and unarmed attacks increases to Expert.
- [ ] `[NEW]` Level 7: Iron Will — Will save proficiency increases to Expert.
- [ ] `[NEW]` Level 7: Perpetual Infusions — designate 2 alchemical items (from research field eligible list, in formula book) that can be created via Quick Alchemy for free (no reagent cost). Items may be swapped each level.
  - Bomber: 2 chosen 1st-level bombs.
  - Chirurgeon: 2 chosen 1st-level healing elixirs (10-min immunity to HP healing from perpetual infusions after use).
  - Mutagenist: 2 chosen 1st-level mutagens.
- [ ] `[NEW]` Level 9: Alchemical Expertise — class DC proficiency increases to Expert.
- [ ] `[NEW]` Level 9: Alertness — Perception proficiency increases to Expert.
- [ ] `[NEW]` Level 9: Double Brew — Quick Alchemy may spend up to 2 reagent batches to create up to 2 alchemical items in one action (need not be identical).
- [ ] `[NEW]` Level 11: Juggernaut — Fortitude save proficiency increases to Master; successes on Fortitude saves become critical successes.
- [ ] `[NEW]` Level 11: Perpetual Potency — upgrades Perpetual Infusions eligible item levels: Bomber → 3rd-level bombs, Chirurgeon → 6th-level healing elixirs, Mutagenist → 3rd-level mutagens.
- [ ] `[NEW]` Level 13: Greater Field Discovery granted per research field:
  - Bomber: splash radius increases to 10 ft (15 ft with Expanded Splash feat).
  - Chirurgeon: elixirs of life created via Quick Alchemy heal maximum HP (no roll).
  - Mutagenist: may be under 2 mutagen effects simultaneously; 3rd mutagen causes loss of one prior benefit (player's choice) but all drawbacks persist. Non-mutagen polymorph while under 2: lose both benefits, retain both drawbacks.
- [ ] `[NEW]` Level 13: Medium Armor Expertise — light armor, medium armor, unarmored defense proficiency increases to Expert.
- [ ] `[NEW]` Level 13: Weapon Specialization — +2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.
- [ ] `[NEW]` Level 15: Alchemical Alacrity — Quick Alchemy may spend up to 3 reagent batches to create up to 3 items in one action; one item is automatically stowed.
- [ ] `[NEW]` Level 15: Evasion — Reflex save proficiency increases to Master; successes on Reflex saves become critical successes.
- [ ] `[NEW]` Level 17: Alchemical Mastery — class DC proficiency increases to Master.
- [ ] `[NEW]` Level 17: Perpetual Perfection — upgrades Perpetual Potency eligible level to 11th for all research fields.
- [ ] `[NEW]` Level 19: Medium Armor Mastery — light armor, medium armor, unarmored defense proficiency increases to Master.

### Class Feat Progression
- [ ] `[NEW]` Alchemist gains a class feat at level 1 and every even-numbered level (2, 4, 6 … 20).
- [ ] `[NEW]` Alchemist gains a skill feat at level 2 and every 2 levels thereafter.
- [ ] `[NEW]` System tracks and applies all class feature unlocks per the advancement table.

### Additive Feats Rules
- [ ] `[NEW]` Additive trait feats add one substance to a bomb or elixir; only one additive per item; a second additive spoils the item.
- [ ] `[NEW]` Additive actions are usable only when creating infused alchemical items.
- [ ] `[NEW]` Additive level adds to the modified item's level; combined level must not exceed advanced alchemy level.
- [ ] `[NEW]` Infused item expiry: all nonpermanent effects end at next daily preparations; active afflictions (slow-acting poisons) persist until their own duration expires.

---

## Edge Cases

- [ ] `[NEW]` Infused reagent count of 0: Quick Alchemy and Advanced Alchemy are blocked (no reagents available); UI shows clear error.
- [ ] `[NEW]` Attempting Quick Alchemy for an item above advanced alchemy level: blocked with error.
- [ ] `[NEW]` Attempting Quick Alchemy for an item not in formula book: blocked with error.
- [ ] `[NEW]` Perpetual Infusion item swap at level-up: old selection is replaced; new items from eligible list only.
- [ ] `[NEW]` Mutagenist Greater Field Discovery: attempting 3rd mutagen correctly removes benefits of one (player-chosen) while retaining all drawbacks.
- [ ] `[NEW]` Chirurgeon Perpetual Infusion immunity: 10-minute immunity correctly tracks per character and does not block other elixir HP healing.
- [ ] `[NEW]` Double Brew: spending 2 reagent batches in one Quick Alchemy action, creating 2 different items — both expire at start of next turn.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Invalid research field selection (not Bomber/Chirurgeon/Mutagenist) is rejected.
- [ ] `[TEST-ONLY]` Level-gated class features do not appear before the required level.
- [ ] `[TEST-ONLY]` Infused item expiry is enforced; items do not persist beyond their duration.
- [ ] `[TEST-ONLY]` Alchemist class DC uses INT correctly; a character with incorrect key ability is rejected.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input surfaces beyond existing character creation and leveling forms
