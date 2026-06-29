# Acceptance Criteria: dc-cr-class-bard

## Gap analysis reference
- DB sections: core/ch03/Bard (REQs 882–940+)
- Track B: no existing BardService; all implementation is [NEW]
- Depends on: dc-cr-character-class ✓, dc-cr-spellcasting (deferred — groom now, activate when spellcasting ships), dc-cr-focus-spells (deferred)

---

## Happy Path

### Identity & Base Statistics
- [ ] `[NEW]` Bard exists as a selectable playable class with Charisma as key ability boost at level 1.
- [ ] `[NEW]` Bard HP = 8 + CON modifier per level.
- [ ] `[NEW]` Initial proficiencies: Expert Perception; Expert Will, Trained Fortitude and Reflex; Trained Occultism and Performance (automatic) plus 4 + INT skills; Trained simple weapons plus longsword/rapier/sap/shortbow/shortsword/whip; Trained light armor only; Trained occult spell attacks and DCs.

### Muse Selection (Level 1 Subclass)
- [ ] `[NEW]` At level 1, player selects one Muse: Enigma, Maestro, or Polymath.
- [ ] `[NEW]` Each Muse grants one bonus feat and one spell added to repertoire:
  - Enigma → Bardic Lore feat + true strike.
  - Maestro → Lingering Composition feat + soothe.
  - Polymath → Versatile Performance feat + unseen servant.

### Occult Spellcasting
- [ ] `[NEW]` Bard uses occult spell tradition; spell attacks and DCs scale with Charisma.
- [ ] `[NEW]` Instrument replaces material and somatic component requirements (one hand free); can also replace verbal components.
- [ ] `[NEW]` Bard uses a Spell Repertoire (spontaneous casting — known spells, not prepared). Starts with 2 first-level occult spells + 5 cantrips at level 1.
- [ ] `[NEW]` One spell added per new slot tier as levels increase per advancement table.
- [ ] `[NEW]` Cantrips auto-heighten to half level rounded up.
- [ ] `[NEW]` Each level-up allows swapping one known spell for another of the same level.
- [ ] `[NEW]` Signature Spells (level 3): one spell per spell level designated as signature; can be heightened freely without learning each level separately.

### Composition Spells & Focus Pool
- [ ] `[NEW]` Bard starts with focus pool of 1 Focus Point (max 3 with feats).
- [ ] `[NEW]` Focus spells (compositions) cost 1 Focus Point; composition cantrips are free.
- [ ] `[NEW]` Refocus = 10 minutes performing, writing a composition, or engaging muse.
- [ ] `[NEW]` Composition spells auto-heighten to half level rounded up.
- [ ] `[NEW]` Starting composition focus spell: Counter Performance (reaction, protects allies from auditory/visual effects).
- [ ] `[NEW]` Starting composition cantrip: Inspire Courage (free; buffs ally attacks, damage, saves vs fear).
- [ ] `[NEW]` Composition trait enforced: only one composition per turn; only one active at a time; new composition immediately ends previous.

### Class Feature Unlocks by Level
- [ ] `[NEW]` Level 3: Lightning Reflexes — Reflex saves increase to Expert.
- [ ] `[NEW]` Level 7: occult spell attack rolls and DCs increase to Expert.
- [ ] `[NEW]` Level 9: Great Fortitude — Fortitude saves increase to Expert.
- [ ] `[NEW]` Level 9: Resolve — Will saves increase to Master; successes become critical successes.
- [ ] `[NEW]` Level 11: Bard Weapon Expertise — simple weapons, unarmed, and specific martial weapons increase to Expert; during active composition, critical hits apply critical specialization effects.
- [ ] `[NEW]` Level 11: Vigilant Senses — Perception increases to Master.
- [ ] `[NEW]` Level 13: light armor and unarmored defense increase to Expert.
- [ ] `[NEW]` Level 13: Weapon Specialization — +2/+3/+4 damage at Expert/Master/Legendary.
- [ ] `[NEW]` Level 15: occult spell attack rolls and DCs increase to Master.
- [ ] `[NEW]` Level 17: Greater Resolve — Will saves increase to Legendary; successes become critical successes; critical failures become failures; failures against damaging effects take half damage.
- [ ] `[NEW]` Level 19: occult spell attack rolls and DCs increase to Legendary.
- [ ] `[NEW]` Level 19: Magnum Opus — add 2 common 10th-level occult spells to repertoire; gain one unique 10th-level spell slot (cannot be used with slot-manipulation features).

### Spell Slots
- [ ] `[NEW]` Bard gains spell slots per advancement table (2 at level 1, scaling through 9th-level slots at level 17, 10th-level unique slot at level 19).
- [ ] `[NEW]` Metamagic actions must immediately precede Cast a Spell; interruption (including free actions) wastes the metamagic.

---

## Edge Cases

- [ ] `[NEW]` Attempting to cast two compositions in the same turn: second is blocked.
- [ ] `[NEW]` Casting a new composition while one is active: previous composition immediately ends.
- [ ] `[NEW]` Heightening a signature spell to any level: permitted without learning each level.
- [ ] `[NEW]` Focus pool at 0: composition focus spells blocked; cantrip compositions still work.
- [ ] `[NEW]` Instrument replaces somatic/material: correctly applies when instrument is held in one hand.

---

## Failure Modes

- [ ] `[TEST-ONLY]` Invalid Muse selection rejected.
- [ ] `[TEST-ONLY]` Level-gated class features do not appear before required level.
- [ ] `[TEST-ONLY]` Spell slots do not exceed advancement table values per level.
- [ ] `[TEST-ONLY]` Spell repertoire size enforced; spells above advanced alchemy level (N/A — cap is by spell level/slot) are correctly gated.

---

## Security acceptance criteria

- Security AC exemption: game-mechanic class logic; no new routes or user-facing input beyond existing character creation and leveling forms
