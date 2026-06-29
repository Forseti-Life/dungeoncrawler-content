# Acceptance Criteria: APG Ancestries and Versatile Heritages

## Feature: dc-apg-ancestries
## Source: PF2E Advanced Player's Guide, Chapter 1

---

## New Ancestries (5)

### Catfolk (Amurrun)
- [ ] Ancestry record: HP 8, Medium, Speed 25, Dexterity boost, Charisma boost, Wisdom flaw, Low-light vision
- [ ] Passive `Land on Your Feet`: falling halves damage and prevents Prone on landing
- [ ] Heritages: Clawed (claw unarmed 1d6 slashing, agile/finesse/unarmed), Hunting (scent 30 ft imprecise), Jungle (ignore difficult terrain from vegetation/rubble), Nine Lives (one-time critical hit death mitigation)

### Kobold
- [ ] Ancestry record: HP 6, Small, Speed 25, Dexterity boost, Charisma boost, Constitution flaw, Darkvision
- [ ] Draconic Exemplar selection at L1 — stores dragon type, damage type, breath weapon shape, save type; used by other kobold abilities
- [ ] Draconic Exemplar lookup table implemented (10 dragon types)
- [ ] Heritage: Cavern — climb natural stone (success → half speed, crit → full speed), squeeze success→crit
- [ ] Heritage: Dragonscaled — resistance to exemplar damage type = level/2 (min 1); doubled vs. dragon breath
- [ ] Heritage: Spellscale — 1 at-will arcane cantrip, trained arcane spellcasting, Cha-based
- [ ] Heritage: Strongjaw — jaws unarmed attack (1d6 piercing, brawling, finesse, unarmed)
- [ ] Heritage: Venomtail — `Tail Toxin` 1-action 1/day: apply to weapon, next hit before end of next turn deals persistent poison = level

### Orc
- [ ] Ancestry record: HP 10, Medium, Speed 25, Strength boost, free boost, Darkvision; **no ability flaw**
- [ ] Heritage set covers: terrain adaptation, weapon proficiency bump, darkvision extension (low-light → darkvision), Grave Orc (negative healing), damage resistance, weather/environment bonuses
- [ ] Grave Orc heritage: `negative healing` — harmed by positive energy, healed by negative energy

### Ratfolk (Ysoki)
- [ ] Ancestry record: HP 6, Small, Speed 25, Dexterity boost, Intelligence boost, Strength flaw, Low-light vision
- [ ] Heritage: Sewer Rat — immune to filth fever; disease/poison save stage reduction (2 on success, 3 on crit; halved for virulent)
- [ ] Heritage: Desert Rat — on all fours Speed 30 (both hands free required); starvation/thirst threshold ×10; heat/cold extremes modified
- [ ] Heritage: Shadow Rat — trained Intimidation; Coerce animals without language penalty; animals start one attitude step worse

### Tengu
- [ ] Ancestry record: HP 6, Medium, Speed 25, Dexterity boost, free boost, Low-light vision
- [ ] All tengus start with `Sharp Beak` unarmed attack: 1d6 piercing, brawling group, finesse, unarmed
- [ ] Heritage: Jinxed — curse/misfortune saves: success→crit success; doomed gain → flat DC 17 to reduce by 1
- [ ] Heritage: Skyborn — take 0 damage from any fall; never lands Prone from falling
- [ ] Heritage: Stormtossed — electricity resistance = level/2 (min 1); ignore concealment from rain/fog when targeting
- [ ] Heritage: Taloned — talons unarmed (1d4 slashing, agile/finesse/unarmed/versatile piercing)

---

## Versatile Heritages (5)

- [ ] Versatile heritages occupy the heritage slot; character has no normal ancestry heritage abilities
- [ ] Characters gain access to versatile heritage feat list **plus** their original ancestry feat list
- [ ] Sense upgrade rule: if ancestry grants low-light vision and versatile heritage would also grant it, heritage instead grants darkvision
- [ ] All versatile heritages have the Uncommon trait (require GM approval to select)

### Aasimar
- [ ] Heritage grants aasimar trait, low-light vision upgrade rule
- [ ] Feat `Lawbringer`: succeed on emotion effect save → critical success instead

### Changeling
- [ ] Heritage grants changeling trait, low-light vision upgrade rule
- [ ] Feat `Slag May`: cold iron claw unarmed attack (1d6 slashing, brawling, grapple, unarmed, cold iron material)

### Dhampir
- [ ] Heritage grants dhampir trait, negative healing, low-light vision upgrade rule
- [ ] Negative healing: positive energy damages, negative energy heals; treated as undead for energy effect rules
- [ ] Feat `Dhampir Fangs`: fangs unarmed attack (1d6 piercing, brawling, grapple, unarmed)

### Duskwalker
- [ ] Heritage grants duskwalker trait; immune to becoming undead (body and spirit); low-light vision upgrade rule
- [ ] Passive: detects haunts without actively Searching (still must meet other detection requirements)

### Tiefling
- [ ] Heritage grants tiefling trait, low-light vision upgrade rule

---

## Additional Ancestry Options (Existing Ancestries)

- [ ] All APG supplemental ancestry feats for Core Rulebook ancestries are loadable as ancestry feat options for those ancestries
- [ ] APG introduces additional ancestral unarmed attacks for Core ancestries; each added to unarmed attack table with correct stats

---

## APG Backgrounds

- [ ] APG backgrounds follow the same data format as CRB backgrounds: 2 ability boosts (1 fixed, 1 free), skill training, skill feat grant
- [ ] Rare backgrounds are tagged Rare and gated behind GM approval
- [ ] Background `Haunted`: Aid from entity; on failure → Frightened 2; on crit fail → Frightened 4; initial Frightened not reducible by prevention effects
- [ ] Background `Fey-Touched`: `Fey's Fortune` 1/day free-action fortune effect on a skill check (roll twice, use better)
- [ ] Background `Returned`: grants Diehard feat automatically (no selection — automatic grant)

---

## Integration Checks

- [ ] All 5 new ancestries appear in ancestry selection list with correct traits, HP, speed, and ability modifiers
- [ ] Versatile heritages appear in heritage selection when any ancestry is chosen; tooltip explains the slot replacement rule
- [ ] Sense upgrade rule resolves automatically at character creation when both ancestry and heritage would grant low-light vision
- [ ] Uncommon trait on versatile heritages gates selection behind GM approval flag
- [ ] APG backgrounds appear in background selection with correct boosts and feat grants
- [ ] Draconic Exemplar table is displayed at character creation for Kobold; stored and referenced by other kobold abilities

## Edge Cases

- [ ] Grave Orc and Dhampir negative healing: `positive_damage_heals = false`, `negative_damage_heals = true`; both apply the undead energy rules
- [ ] Kobold Spellscale: only grants 1 cantrip slot; does not create a full spell list or progression
- [ ] Tengu Skyborn: fall immunity (not just reduction); fall damage = 0 regardless of height
- [ ] Two characters with versatile heritages of different types have independent heritage feat lists
