# Acceptance Criteria: dc-cr-class-barbarian

## Gap analysis reference
- DB sections: core/ch03/Barbarian (REQs 762–840+)
- Track B: no existing BarbariannService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-character-creation ✓, dc-cr-conditions (in-progress Release B)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Barbarian exists as a selectable playable class in character creation with Strength as key ability boost at level 1.
- [ ] `[NEW]` Barbarian HP = 12 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Expert Fortitude and Will, Trained Reflex; Trained simple and martial weapons; Trained light and medium armor; 3 + INT modifier skills with Athletics fixed as trained.

### Instinct Selection (Level 1)
- [ ] `[NEW]` At level 1, player selects one Instinct: Animal, Dragon, Fury, Giant, or Spirit.
- [ ] `[NEW]` Each instinct has an anathema behavioral restriction stored on the character; violating it removes all instinct-dependent abilities until 1 day downtime re-centering.
- [ ] `[NEW]` Animal Instinct: grants animal unarmed attacks during Rage (Ape, Bear, Bull, Cat, Deer, Frog, Shark, Snake, Wolf — each with correct damage die, type, and traits); Rage gains morph/primal/transmutation traits.
- [ ] `[NEW]` Dragon Instinct: player selects dragon type; Draconic Rage increases damage 2→4 and changes type to breath weapon type; Rage gains arcane/evocation/elemental traits.
- [ ] `[NEW]` Fury Instinct: no anathema; grants one additional 1st-level barbarian feat.
- [ ] `[NEW]` Giant Instinct: character may wield oversized weapons (one size larger, normal Price/Bulk); Rage damage 2→6 while using oversized weapon; clumsy 1 applies (cannot be removed).
- [ ] `[NEW]` Spirit Instinct: Rage damage type changes to negative or positive (chosen each Rage); weapon acts as ghost touch property rune while raging; Rage gains divine/necromancy traits.

### Rage [one-action] (Level 1)
- [ ] `[NEW]` Rage is a one-action ability (traits: concentrate, emotion, mental).
- [ ] `[NEW]` On activation: grant temp HP = level + CON modifier.
- [ ] `[NEW]` While raging: +2 damage on melee Strikes (halved for agile weapons/unarmed attacks).
- [ ] `[NEW]` While raging: –1 AC penalty applied.
- [ ] `[NEW]` While raging: concentrate-trait actions are blocked EXCEPT those also with the rage trait; Seek is explicitly permitted.
- [ ] `[NEW]` Rage duration: 1 minute, ends early if no perceived enemies or if unconscious.
- [ ] `[NEW]` Rage cannot be voluntarily ended early by the player.
- [ ] `[NEW]` After Rage ends: temp HP disappears; 1-minute cooldown before Rage can be used again.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 3: Deny Advantage — immune to flat-footed condition from hidden/undetected/flanking/surprise attacks from creatures of same or lower level; does not prevent those creatures from granting flanking to allies.
- [ ] `[NEW]` Level 5: Brutality — weapon proficiency (simple, martial, unarmed) increases to Master; critical specialization effects apply while raging.
- [ ] `[NEW]` Level 7: Juggernaut — Fortitude save proficiency increases to Master; successes on Fortitude saves become critical successes.
- [ ] `[NEW]` Level 7: Weapon Specialization — +2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary; instinct specialization ability unlocked (instinct-specific bonus).
- [ ] `[NEW]` Level 9: Lightning Reflexes — Reflex save proficiency increases to Expert.
- [ ] `[NEW]` Level 9: Raging Resistance — while raging, gain damage resistance = 3 + CON modifier; damage type determined by instinct (Animal: piercing+slashing; Dragon: piercing+breath type; Fury: bludgeoning+one of cold/electricity/fire; Giant: physical weapon; Spirit: negative+undead attacks).
- [ ] `[NEW]` Level 11: Mighty Rage — class DC proficiency increases to Expert; gain Mighty Rage free-action that triggers on Rage activation, allowing immediate use of a rage-trait action (or 2-action rage-trait activity with 2-action Rage).
- [ ] `[NEW]` Level 13: Greater Juggernaut — Fortitude save proficiency increases to Legendary; critical Fortitude failures become failures; failures against damage effects halve damage taken.
- [ ] `[NEW]` Level 13: Medium Armor Expertise — light armor, medium armor, unarmored defense proficiency increases to Expert.
- [ ] `[NEW]` Level 15: Greater Weapon Specialization — damage bonus becomes +4 (Expert) / +6 (Master) / +8 (Legendary).
- [ ] `[NEW]` Level 15: Indomitable Will — Will save proficiency increases to Master; successes become critical successes.
- [ ] `[NEW]` Level 17: Heightened Senses — Perception proficiency increases to Master.
- [ ] `[NEW]` Level 17: Quick Rage — removes the 1-minute Rage cooldown; after one full turn without raging, Barbarian can Rage again immediately.
- [ ] `[NEW]` Level 19: Armor of Fury — light armor, medium armor, unarmored defense proficiency increases to Master.
- [ ] `[NEW]` Level 19: Devastator — class DC proficiency increases to Master; melee Strikes ignore 10 points of resistance to physical damage types.

### Feat Progression
- [ ] `[NEW]` Barbarian gains a class feat at level 1 and every even-numbered level (2, 4, 6 … 20).
- [ ] `[NEW]` General feats at levels 3, 7, 11, 15, 19; skill feats at every even level; ancestry feats at levels 5, 9, 13, 17.
- [ ] `[NEW]` Ability boosts at levels 5, 10, 15, 20.

### Key Trait Rules
- [ ] `[NEW]` Flourish trait: only one flourish action per turn enforced.
- [ ] `[NEW]` Open trait: only usable as first action of a turn before any attack or open action.
- [ ] `[NEW]` Rage trait on abilities: requires active Rage; ability ends if Rage ends.

---

## Edge Cases

- [ ] `[NEW]` Rage activation while already raging: blocked (Rage is already active).
- [ ] `[NEW]` Attempting a concentrate-trait action while raging (without rage trait): blocked with clear message.
- [ ] `[NEW]` Anathema violation: instinct abilities correctly removed; re-centering downtime re-enables them.
- [ ] `[NEW]` Mighty Rage free-action: only available as trigger on Rage activation; cannot be used outside that window.
- [ ] `[NEW]` Giant Instinct clumsy 1: cannot be removed by any means while raging with oversized weapon.
- [ ] `[NEW]` Dragon Instinct Draconic Rage: breath weapon damage type correctly sourced from selected dragon type.
- [ ] `[NEW]` Raging Resistance type: correctly differs per instinct; Fury Instinct secondary type (cold/electricity/fire) is player-chosen at selection.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Rage cooldown enforced: cannot Rage again within 1 minute (without Quick Rage at level 17).
- [ ] `[TEST-ONLY]` Level-gated features do not appear before required level.
- [ ] `[TEST-ONLY]` Temp HP from Rage disappears on rage end (does not persist as regular HP).
- [ ] `[TEST-ONLY]` Invalid instinct selection (not one of the 5) is rejected.
- [ ] `[TEST-ONLY]` AC penalty from Rage correctly applies and is removed when Rage ends.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
