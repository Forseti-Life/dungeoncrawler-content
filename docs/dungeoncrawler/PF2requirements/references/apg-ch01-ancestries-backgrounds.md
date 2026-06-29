# PF2E Advanced Player's Guide — Chapter 1: Ancestries & Backgrounds
## Extracted Requirements for DungeonCrawler System

---

## SECTION: Ancestries Overview

### Paragraph — New Ancestries
> The APG introduces five new ancestries (Catfolk, Kobold, Orc, Ratfolk, Tengu) and five versatile heritages (Changeling, Dhampir, Aasimar, Duskwalker, Tiefling). Versatile heritages replace the character's normal heritage choice and overlay on top of any core ancestry.

- REQ: Add five new ancestry entries to the ancestry database.
- REQ: Versatile heritages are a distinct system: they replace the heritage slot, stack with any base ancestry, and grant access to a separate feat list plus the character's original ancestry feat list.
- REQ: A character with a versatile heritage mechanically gains only one heritage (the versatile one) — the original ancestry heritage abilities are not gained.

---

## SECTION: Catfolk (Amurrun)

### Paragraph — Catfolk Ancestry Stat Block
> Uncommon. HP: 8. Size: Medium. Speed: 25 ft. Ability Boosts: Dexterity, Charisma, Free. Ability Flaw: Wisdom. Languages: Amurrun, Common + Int modifier additional languages. Traits: Catfolk, Humanoid. Senses: Low-light vision.
> Ancestral abilities: (1) Land on Your Feet — when falling, take only half normal fall damage and don't land prone.

- REQ: Catfolk ancestry data: HP 8, Medium, Speed 25, Dex+Cha boosts, Wis flaw, Low-light vision.
- REQ: Catfolk passive ability: `Land on Your Feet` — halves fall damage and prevents Prone on landing.

### Paragraph — Catfolk Heritages (choose 1 at 1st level)
> - **Clawed Catfolk**: claw unarmed attack (1d6 slashing, brawling group, agile/finesse/unarmed).
> - **Hunting Catfolk**: imprecise scent 30 ft; +2 circumstance to Track a creature you've smelled.
> - **Jungle Catfolk**: ignore difficult terrain from undergrowth; greater difficult terrain from undergrowth = difficult terrain.
> - **Nine Lives Catfolk**: when reduced to 0 HP by a critical hit, become dying 1 instead of the normal dying value for a critical hit.
> - **Sharp-Eared Catfolk** (implied from heritage list): additional auditory sense enhancements.
> - **Well-Met Traveler** (heritage effect): social interaction bonuses.

- REQ: Catfolk heritage options include at minimum: Clawed (unarmed claw attack), Hunting (scent 30 ft), Jungle (terrain ignore), Nine Lives (crit-hit dying mitigation).
- REQ: Claw unarmed attack: 1d6 slashing, brawling group, traits: agile, finesse, unarmed.

---

## SECTION: Kobold

### Paragraph — Kobold Ancestry Stat Block
> Uncommon. HP: 6. Size: Small. Speed: 25 ft. Ability Boosts: Dexterity, Charisma, Free. Ability Flaw: Constitution. Languages: Common, Draconic + Int modifier additional. Traits: Humanoid, Kobold. Senses: Darkvision.
> Draconic Exemplar: chosen at 1st level — chromatic or metallic dragon type (Black/Copper=Acid, Blue/Bronze=Electricity, Brass/Gold/Red=Fire, Green=Poison, Silver/White=Cold). Determines breath weapon shape, damage type, saving throw for related abilities.

- REQ: Kobold ancestry data: HP 6, Small, Speed 25, Dex+Cha boosts, Con flaw, Darkvision.
- REQ: Kobold choose a Draconic Exemplar at 1st level — stores dragon type, damage type, breath shape, and saving throw type. Used by multiple kobold abilities.

**Table: Draconic Exemplars**

| Dragon | Breath Shape | Damage Type | Saving Throw |
|---|---|---|---|
| Black | Line | Acid | Reflex |
| Blue | Line | Electricity | Reflex |
| Green | Cone | Poison | Fortitude |
| Red | Cone | Fire | Reflex |
| White | Cone | Cold | Reflex |
| Brass | Line | Fire | Reflex |
| Bronze | Line | Electricity | Reflex |
| Copper | Line | Acid | Reflex |
| Gold | Cone | Fire | Reflex |
| Silver | Cone | Cold | Reflex |

- REQ: Implement Draconic Exemplar table lookup for kobold abilities.

### Paragraph — Kobold Heritages
> - **Cavern Kobold**: climbing natural stone features — success = half speed, critical success = full speed (does not affect a Climb Speed). Acrobatics Squeeze: success = critical success.
> - **Dragonscaled Kobold**: resistance = half level (min 1) to exemplar's damage type; doubled vs. dragon Breath Weapons.
> - **Spellscale Kobold**: choose one arcane cantrip; cast as arcane innate spell at will; trained in arcane spell attacks and DCs; key spellcasting ability Charisma.
> - **Strongjaw Kobold**: jaws unarmed attack (1d6 piercing, brawling group, finesse/unarmed).
> - **Venomtail Kobold**: produces 1 dose of tail venom per day; Tail Toxin action: once/day, apply venom to piercing/slashing weapon; next successful hit deals persistent poison damage = level.

- REQ: Kobold Cavern heritage: modify climbing checks on natural stone (success → half speed, crit success → full speed), Squeeze: success → crit success.
- REQ: Kobold Dragonscaled heritage: resistance to exemplar damage type = level/2 (min 1); doubled vs. dragon Breath Weapons.
- REQ: Kobold Spellscale heritage: grants 1 at-will arcane cantrip; trained arcane spellcasting; Cha-based.
- REQ: Kobold Strongjaw heritage: jaws unarmed attack (1d6 piercing, brawling, finesse, unarmed).
- REQ: Kobold Venomtail heritage: `Tail Toxin` 1-action, 1/day — apply to weapon, next hit before end of next turn deals persistent poison damage equal to level.

---

## SECTION: Orc

### Paragraph — Orc Ancestry Stat Block
> Uncommon. HP: 10. Size: Medium. Speed: 25 ft. Ability Boosts: Strength, Free. Ability Flaw: none listed (no official flaw). Languages: Common, Orcish + Int modifier additional. Traits: Humanoid, Orc. Senses: Darkvision.

- REQ: Orc ancestry data: HP 10, Medium, Speed 25, Str boost + Free boost, Darkvision. Note: Orcs have no ability flaw.

### Paragraph — Orc Heritages
> - **Badlands Orc**: adapt to harsh conditions — Athletics +2 circumstance to climb and swim in rocky or arid terrain; environmental heat one step less extreme.
> - **Battle-Ready Orc**: trained in all martial weapons at character creation (or expert if already martial trained via class).
> - **Deep Orc**: additional darkvision, extended to greater range; +2 circumstance to saves vs. effects that cause the dazzled condition.
> - **Grave Orc**: born near death — gain negative healing (treated as undead for positive/negative energy). Gain resistance to negative damage equal to half level.
> - **Hold-Scarred Orc**: tough hides grant resistance to piercing and slashing damage equal to half level (this is a notable defensive ability).
> - **Rainfall Orc**: benefits in environmental weather; Acrobatics to balance in rain, etc.

- REQ: Orc heritages cover: terrain adaptation, weapon proficiency bump, darkvision extension, negative healing variant, damage resistance, and weather/environment bonuses.
- REQ: Grave Orc heritage: `negative healing` — harmed by positive energy, healed by negative energy.

---

## SECTION: Ratfolk (Ysoki)

### Paragraph — Ratfolk Ancestry Stat Block
> Uncommon. HP: 6. Size: Small. Speed: 25 ft. Ability Boosts: Dexterity, Intelligence, Free. Ability Flaw: Strength. Languages: Common, Ysoki + Int modifier additional. Traits: Humanoid, Ratfolk. Senses: Low-light vision.

- REQ: Ratfolk ancestry data: HP 6, Small, Speed 25, Dex+Int boosts, Str flaw, Low-light vision.

### Paragraph — Ratfolk Heritages
> - **Desert Rat**: with both hands free, can increase Speed to 30 ft running on all fours; heat one step less extreme; can go 10× longer before starvation/thirst; cold one step more extreme (unless protected).
> - **Longsnout Rat**: imprecise scent 30 ft; +2 circumstance to Perception Seek checks within scent range.
> - **Sewer Rat**: immune to filth fever disease; successful saves vs. disease/poison reduce stage by 2 (or 1 for virulent); critical successes reduce by 3 (or 2 for virulent).
> - **Shadow Rat**: trained in Intimidation (or different skill if already trained); can Coerce animals; no language penalty to Demoralize animals; animals' initial attitude one step worse toward character.
> - **Snow Rat**: cold resistance equal to half level (min 1); environmental cold one step less extreme.
> - **Tunnel Rat**: squeezing through tight spaces treated as difficult terrain rather than greater difficult terrain.

- REQ: Ratfolk Sewer Rat heritage: disease immunity (filth fever), improved disease/poison save stage reduction (2 on success, 3 on crit; 1/2 for virulent).
- REQ: Ratfolk Desert Rat heritage: on-all-fours Speed 30 (requires both hands free); extended starvation/thirst (10×); heat/cold extremes modified.
- REQ: Ratfolk Shadow Rat heritage: trained Intimidation; animal Coercion without language penalty; animals start one attitude step worse.

---

## SECTION: Tengu

### Paragraph — Tengu Ancestry Stat Block
> Uncommon. HP: 6. Size: Medium. Speed: 25 ft. Ability Boosts: Dexterity, Free. Languages: Common, Tengu + Int modifier additional. Traits: Humanoid, Tengu. Senses: Low-light vision.
> Ancestral ability: **Sharp Beak** — beak unarmed attack (1d6 piercing, brawling group, finesse/unarmed). All tengus have this.

- REQ: Tengu ancestry data: HP 6, Medium, Speed 25, Dex boost + Free boost, Low-light vision.
- REQ: All tengus have the `Sharp Beak` unarmed attack: 1d6 piercing, brawling group, finesse, unarmed.

### Paragraph — Tengu Heritages
> - **Jinxed Tengu**: succeeds at curse/misfortune saves → critical success instead; when gaining doomed condition, flat DC 17 check to reduce doomed value by 1.
> - **Mountainkeeper Tengu**: cast `disrupt undead` as primal innate cantrip at will; can choose divine or primal trait for any tengu heritage/ancestry feat spell.
> - **Skyborn Tengu**: take no damage from falling (regardless of distance).
> - **Stormtossed Tengu**: electricity resistance = half level (min 1); auto-succeed flat check to target creature concealed only by rain or fog.
> - **Taloned Tengu**: talons unarmed attack (1d4 slashing, brawling group, agile/finesse/unarmed/versatile piercing).

- REQ: Tengu Jinxed heritage: curse/misfortune saves — success → crit success; doomed gain → flat DC 17 to reduce by 1.
- REQ: Tengu Skyborn heritage: take 0 damage from any fall, never lands Prone from falling.
- REQ: Tengu Stormtossed heritage: electricity resistance = level/2 (min 1); ignore concealment check for rain/fog targets.
- REQ: Tengu Taloned heritage: talons unarmed attack (1d4 slashing, agile/finesse/unarmed/versatile piercing).

---

## SECTION: Versatile Heritages

### Paragraph — Versatile Heritage Rules
> A versatile heritage replaces the character's normal heritage choice entirely. The character gains the traits and abilities of the versatile heritage, loses access to the ancestry's normal heritage list, but retains access to their original ancestry's ancestry feats in addition to the versatile heritage's feat list.
> If a character could gain the same sense from both their ancestry and their versatile heritage (e.g., low-light vision), they instead gain the superior sense (darkvision).

- REQ: Versatile heritages occupy the heritage slot. Characters with a versatile heritage: no normal ancestry heritage abilities, access to versatile heritage feats + original ancestry feats.
- REQ: Sense upgrade rule: if ancestry grants low-light vision and versatile heritage would also grant low-light vision, the heritage instead grants darkvision.
- REQ: Versatile heritages all have the Uncommon trait.

### Paragraph — Changeling Heritage
> **Changeling (Uncommon Heritage)**: mother was a hag. Gain the changeling trait. Gain low-light vision (or darkvision if ancestry already has low-light vision).
> Notable feats: Brine May (sea hag child — Swim success → crit success; don't sink without a Swim action), Callow May (green hag child — social benefits), Slag May (annis hag child — cold iron claw unarmed attack, 1d6 slashing, brawling group, grapple/unarmed, cold iron material).

- REQ: Changeling heritage: changeling trait, low-light vision upgrade rule.
- REQ: Slag May feat: cold iron claw unarmed attack (1d6 slashing, brawling, grapple, unarmed, cold iron material type).

### Paragraph — Dhampir Heritage
> **Dhampir (Uncommon Heritage)**: one parent was a vampire. Gain the dhampir trait. Gain **negative healing** — harmed by positive damage, healed by negative effects as if undead. Gain low-light vision (or darkvision if ancestry already has low-light vision).
> Notable feats: Fangs — fangs unarmed attack (1d6 piercing, brawling group, grapple/unarmed).

- REQ: Dhampir heritage: dhampir trait, negative healing (undead positive/negative energy rules apply), low-light vision upgrade rule.
- REQ: Negative healing means: positive damage hurts, negative effects heal; the character is treated as undead for purposes of energy effects.
- REQ: Dhampir Fangs feat: fangs unarmed attack (1d6 piercing, brawling, grapple, unarmed).

### Paragraph — Aasimar Heritage
> **Aasimar (Uncommon Heritage)**: celestial ancestry. Gain the aasimar trait. Gain low-light vision (or darkvision if already has low-light vision).
> Notable lineage feats: Angelkin (angel blood), Lawbringer (archon blood — if succeed on emotion effect save → crit success), Musetouched (azata blood — performance/creativity bonuses).

- REQ: Aasimar heritage: aasimar trait, low-light vision upgrade rule.
- REQ: Lawbringer feat: succeed on emotion effect save → critical success instead.

### Paragraph — Duskwalker Heritage
> **Duskwalker (Uncommon Heritage)**: soul reborn with psychopomp connection. Gain the duskwalker trait. Gain low-light vision (or darkvision if already has it). Neither body nor spirit can become undead. Can find haunts without actively Searching.

- REQ: Duskwalker heritage: duskwalker trait; immune to becoming undead (body and spirit); low-light vision upgrade rule.
- REQ: Duskwalker passive: can detect haunts without actively Searching (still must meet other detection requirements).

### Paragraph — Tiefling Heritage
> **Tiefling (Uncommon Heritage)**: fiendish ancestry. Gain the tiefling trait. Gain low-light vision (or darkvision if already has it).
> Notable lineage feats: Hellspawn (devil blood), Pitborn (demon blood), Grimspawn (daemon blood).

- REQ: Tiefling heritage: tiefling trait, low-light vision upgrade rule.

---

## SECTION: Additional Ancestry Options (Existing Ancestries)

### Paragraph — Expanded Options for Core Ancestries
> APG provides additional heritages, ancestry feats, and versatile heritage feats for all Core Rulebook ancestries (Dwarf, Elf, Gnome, Goblin, Halfling, Human, Half-Elf, Half-Orc, Leshy, etc.). These follow the same mechanics as their base ancestries.

- REQ: All APG supplemental ancestry feats for Core Rulebook ancestries must be loadable as ancestry feat options for those ancestries.
- REQ: APG introduces additional ancestral unarmed attacks for various Core ancestries; each must be added to the unarmed attack table with appropriate stats.

---

## SECTION: Backgrounds

### Paragraph — Background Format
> APG backgrounds follow identical format to Core Rulebook: 2 ability boosts (one fixed, one free), trained in 1–2 skills, gain 1 skill feat. Some backgrounds also grant additional abilities.

- REQ: APG backgrounds follow the same data format as Core Rulebook backgrounds: 2 ability boosts (1 fixed, 1 free), skill training, skill feat grant.

### Paragraph — Rare Backgrounds
> Rare backgrounds require GM/player discussion before selection. They set a character up with a special narrative position and may grant unique mechanical abilities beyond the standard background format.
> - **Haunted**: trained in Occultism + a second GM-determined skill; entity that may Aid certain skill checks (+1 circumstance) but on failure causes Frightened 2 (Frightened 4 on crit fail), not reducible initially.
> - **Returned**: gained the Diehard feat + Boneyard Lore Additional Lore feat; previously died and returned.
> - **Royalty**: trained in Society; gains Courtly Graces skill feat; Connections feat auto-generates noble/common contacts in territory.
> - **Fey-Touched** (Fey's Fortune): once/day free action — fortune effect on a skill check (roll twice, take better result). Restricted: must fulfill fey requests when asked.

- REQ: Rare backgrounds have the Rare trait. They are not available for selection without GM approval.
- REQ: Haunted background: skill check Aid from entity; on failure → Frightened 2 (crit fail → Frightened 4); initial Frightened value is not reducible by prevention effects.
- REQ: Fey-touched background: `Fey's Fortune` — 1/day free action fortune effect on skill check (roll twice, use better).
- REQ: Returned background: grants Diehard feat directly (not as a selection — automatic grant).
