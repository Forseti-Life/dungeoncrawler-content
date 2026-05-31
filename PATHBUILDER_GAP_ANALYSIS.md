# Pathbuilder 2e Parity - Comprehensive Gap Analysis
**Date**: February 19, 2026  
**Status**: Foundation Built, **~20% System Parity**

---

## Executive Summary

User's assessment is **100% correct**: While we have excellent technical infrastructure, we're missing most character options and calculations. Realistically at **~20% parity** with Pathbuilder 2e.

### What We Have ✅ (20%)
- Interactive ability score system (PF2e rules compliant)
- 8 ancestries with 110+ level 1 feats
- 4 classes with 25+ level 1 feats
- 35+ backgrounds  
- 16 skills list
- Basic equipment UI
- Character sheet display

### Critical Missing Systems ❌ (80%)
- **Spellcasting** (0% complete - blocks 11 of 23 classes)
- **Class Features** (2% complete - instincts, bloodlines, domains, etc. missing)
- **Combat Calculations** (30% complete - attack/damage/AC formulas incomplete)
- **Level Progression** (0% complete - stuck at level 1 forever)
- **19 Missing Classes** (83% of class roster unimplemented)
- **32+ Missing Ancestries** (80% of ancestry roster missing)
- **1,865+ Missing Feats** (93% of all feat content missing across all types and levels)
- **Skill Progression** (0% - no proficiency increases, no skill feats)
- **Equipment Stats** (0% - no weapon attack bonuses, no AC bonuses, no runes)
- **Derived Combat Stats** (20% - missing most calculations for playable sheet)

---

## Detailed Gap Analysis by System

### 1. ❌ **SPELLCASTING SYSTEM** (0% Complete) - CRITICAL BLOCKER

**Current State**: Nothing implemented  
**Pathbuilder State**: Complete spell management for 11 caster classes

#### Missing Components:

**Spell Lists** (0/4 traditions):
- [ ] Arcane spell list (~400 spells)
- [ ] Divine spell list (~400 spells)  
- [ ] Occult spell list (~400 spells)
- [ ] Primal spell list (~400 spells)

**Spell Selection UI**:
- [ ] Cantrip selection interface (5 cantrips for most casters)
- [ ] Spell repertoire for spontaneous casters (Sorcerer, Bard: 5-6 spells known at level 1)
- [ ] Spellbook for prepared casters (Wizard: unlimited spells in book, prepare 4-5)
- [ ] Prepared spell slots (Cleric, Druid: prepare from full list)
- [ ] Focus spell selection (all casters get 1-2 focus spells)
- [ ] Innate spells from ancestry/heritage/feats

**Spell Mechanics** (0% implemented):
- [ ] Spell DC calculation: `10 + spellcasting mod + proficiency (trained = 2)`
- [ ] Spell attack bonus: `spellcasting mod + proficiency`
- [ ] Spell slot tracking (cantrips unlimited, spell slots by level)
- [ ] Heightening system (casting lower spells in higher slots)
- [ ] Focus point pool (1-3 points, regain on rest)

**Display**:
- [ ] Spell slots per day display
- [ ] Prepared spells list
- [ ] Spell repertoire display
- [ ] Focus spell panel
- [ ] Innate spell frequencies

**Affected Classes** (11 of 23 BLOCKED):
- **Core**: Wizard ⚠️ (has feats, no spells), Sorcerer ❌, Cleric ❌, Druid ❌, Bard ❌
- **APG**: Oracle ❌, Witch ❌
- **SoM**: Magus ❌, Summoner ❌
- **Dark Archive**: Psychic ❌
- **Partial spellcasters**: Ranger (focus), Champion (focus)

**Impact**: **Cannot create 11 of 23 classes properly**. Wizard exists but is non-functional without spells.

**Priority**: 🔥 **CRITICAL** - Top blocker for ~47% of classes

---

### 2. ❌ **CLASS FEATURES** (2% Complete) - CRITICAL BLOCKER

**Current State**: Basic HP, key ability, proficiencies only  
**Pathbuilder State**: All class features at all levels (500+ unique class features)

#### Missing Class-Defining Features by Class:

**Alchemist** (0% features):
- [ ] Research Field selection (Bomber/Chirurgeon/Mutagenist/Toxicologist)
- [ ] Advanced Alchemy feature
- [ ] Infused Reagents calculation (level + INT)
- [ ] Quick Alchemy action
- [ ] Formula book starting formulas
- [ ] Mutagenic flashback, perpetual infusions (by field)

**Barbarian** (0% features):
- [ ] Instinct selection (**REQUIRED at level 1**):
  - Animal, Dragon, Fury, Giant, Spirit, Superstition
- [ ] Rage action (main class mechanic)
- [ ] Rage damage bonus (+2 at level 1)
- [ ] Anathema by instinct
- [ ] Instinct abilities (e.g., Dragon Claws, Spirit Rage resistance)

**Bard** (0% features):
- [ ] Muse selection (**REQUIRED at level 1**):
  - Enigma, Maestro, Polymath, Warrior
- [ ] Composition Spells (Counter Performance, Inspire Courage, etc.)
- [ ] Occult spellcasting (see Spellcasting gap)
- [ ] Muse-specific benefits

**Champion** (0% features):
- [ ] Cause selection (**REQUIRED at level 1**):
  - Paladin (LG), Redeemer (NG), Liberator (CG)
  - Tyrant (LE), Desecrator (NE), Antipaladin (CE)
- [ ] Deity selection (90+ deities) with edicts/anathema
- [ ] Champion's Reaction (varies by cause)
- [ ] Divine Ally (not at level 1, but framework needed)
- [ ] Deific weapon

**Cleric** (0% features):
- [ ] Deity selection (**REQUIRED at level 1**) - 90+ deities
- [ ] Divine Font (**REQUIRED**): Heal or Harm (3-5 extra spell slots)
- [ ] Domain selection (2-3 domains from deity's 3-4 options)
- [ ] Domain spells (focus spells)
- [ ] Deity's favored weapon
- [ ] Anathema tracking
- [ ] Divine spellcasting (see Spellcasting gap)

**Druid** (0% features):
- [ ] Druidic Order selection (**REQUIRED at level 1**):
  - Animal, Leaf, Storm, Wild
- [ ] Primal spellcasting (see Spellcasting gap)
- [ ] Order spells (focus spells by order)
- [ ] Wild Shape for Wild order
- [ ] Shield Block feat (Leaf order)
- [ ] Tempest Strike feat (Storm order)
- [ ] Druidic language
- [ ] Anathema

**Fighter** ✅ (90% complete):
- [x] Attack of Opportunity (has it)
- [x] Shield Block feat (available)
- [ ] Combat Flexibility (higher level, not urgent)

**Monk** (0% features):
- [ ] Flurry of Blows action (MAP -4/-8 instead of -5/-10)
- [ ] Powerful Fist (1d6 unarmed damage)
- [ ] Graceful Legend feature
- [ ] Ki spells (focus spells)
- [ ] Stance selection (via feats, but need stance framework)

**Ranger** ⚠️ (40% complete):
- [x] Basic features exist
- [ ] Hunter's Edge selection (**REQUIRED at level 1**):
  - Flurry (two attacks at -2), Precision (+1d8 damage), Outwit (flat-footed)
- [ ] Hunt Prey action (main class mechanic)
- [ ] Trackless Step
- [ ] Nature's Edge

**Rogue** ⚠️ (40% complete):
- [x] Basic features exist
- [ ] Racket selection (**REQUIRED at level 1**):
  - Ruffian, Thief, Scoundrel, Mastermind (each changes key stats/weapons)
- [ ] Sneak Attack (1d6 precision damage)
- [ ] Surprise Attack
- [ ] Rogue's Racket benefits
- [ ] Deny Advantage

**Sorcerer** (0% features):
- [ ] Bloodline selection (**REQUIRED at level 1**):
  - Aberrant, Angelic, Demonic, Diabolic, Draconic, Elemental, Fey, Hag, Imperial, Undead, Wyrmblessed
- [ ] Bloodline spell (focus spell)
- [ ] Blood Magic effect
- [ ] Occult/Divine/Primal/Arcane tradition (by bloodline)
- [ ] Sorcerer spellcasting (see Spellcasting gap)

**Wizard** ⚠️ (30% complete):
- [x] Basic features, has level 1 feats
- [ ] Arcane School/Thesis selection (optional but common):
  - Schools: Abjuration, Conjuration, Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation, Universalist
  - Theses: Improved Familiar Attunement, Metamagical Experimentation, Spell Blending, Spell Substitution
- [ ] School spell (focus spell if school picked)
- [ ] Arcane Bond (familiar or bonded item)
- [ ] Arcane spellcasting (see Spellcasting gap) - **CRITICAL**

**APG Classes** (0% features for all 4):
- [ ] Investigator: Methodology, Strategic Strike, devise a stratagem
- [ ] Oracle: Mystery, curse, revelation spells, divine spellcasting
- [ ] Swashbuckler: Style, panache, finisher, precise strike
- [ ] Witch: Patron, familiar, hex cantrips, occult spellcasting

**SoM Classes** (0% features):
- [ ] Magus: Hybrid Study, Spellstrike, arcane spellcasting
- [ ] Summoner: Eidolon, Act Together, evolution feat, spellcasting

**G&G Classes** (0% features):
- [ ] Gunslinger: Way, initial deed, slinger's reload
- [ ] Inventor: Innovation, overdrive, unstable trait

**Dark Archive** (0% features):
- [ ] Psychic: Conscious Mind, subconscious mind, unleash psyche, occult spellcasting
- [ ] Thaumaturge: Implement, esoterica, exploit vulnerability

**Rage of Elements** (0% features):
- [ ] Kineticist: Kinetic gate, impulses, channel elements, Gather Element

**Impact**: Classes are empty shells. Cannot be played without their core mechanics.

**Priority**: 🔥 **CRITICAL** - Required for classes to function

---

### 3. ⚠️ **DERIVED COMBAT STATS** (30% Complete) - HIGH PRIORITY

**Current State**: Shows ability modifiers, basic HP/AC  
**Pathbuilder State**: All combat stats calculated correctly

#### Missing Calculations:

**Attack Bonuses** (0% implemented):
```
Correct formula: Stat Mod + Proficiency + Item Bonus + Circumstance + Status
Proficiency = Level + 0/2/4/6/8 (Untrained/Trained/Expert/Master/Legendary)
```

Missing:
- [ ] Melee Strike: STR mod + weapon proficiency + potency rune + conditions
- [ ] Ranged Strike: DEX mod + weapon proficiency + potency rune + conditions  
- [ ] Spell Attack: Casting mod + spellcasting proficiency + item bonus
- [ ] Unarmed Strike: STR mod + unarmed proficiency
- [ ] Finesse weapons: Best of STR/DEX
- [ ] MAP (Multiple Attack Penalty): -5/-10 or -4/-8 (Agile weapon)

**Armor Class** (30% implemented):
```
Correct formula: 10 + DEX (capped by armor) + Armor bonus + Armor proficiency + Potency rune + Circumstance + Status
```

Current: Shows `10 + DEX mod` only  
Missing:
- [ ] Armor bonus (+0 unarmored, +1 light, +3 medium, +5 heavy)
- [ ] Proficiency bonus (by class: trained/expert/master in armor types)
- [ ] DEX cap (e.g., +2 for chain mail, unlimited for unarmored)
- [ ] Potency runes (+1/+2/+3)
- [ ] Shield bonus (+2 when raised)
- [ ] Class features (e.g., Barbarian unarmored defense bonus)

**Saving Throws** (40% implemented):
```
Correct formula: Stat Mod + Proficiency + Item Bonus + Circumstance + Status
```

Current: Shows `stat mod + 2` (assumes trained)  
Missing:
- [ ] Actual class proficiency levels (some expert at level 1)
- [ ] Proficiency progression (e.g., Fighter Reflex → expert at 3, master at 11)
- [ ] Item bonuses (Resilient runes: +1/+2/+3)
- [ ] Level scaling (proficiency includes level!)

**Other Combat Stats**:
- [ ] **Class DC**: `10 + key ability mod + trained (2)` (for class abilities)
- [ ] **Spell DC**: `10 + casting mod + spellcasting proficiency` (for spells)
- [ ] **Perception**: `WIS mod + proficiency + item + circumstance`
- [ ] **Initiative**: Uses Perception (or Stealth if unnoticed)
- [ ] **Speed**: Base speed - armor penalty (e.g., -5 for medium, -10 heavy without 16 STR)

**Damage Output** (0% implemented):
- [ ] Weapon damage dice (by weapon: 1d4, 1d6, 1d8, 1d10, 1d12, 2d6, etc.)
- [ ] Stat bonus to damage (STR for melee/thrown, none for ranged except propulsive)
- [ ] Striking runes (+1d weapon die per rune level)
- [ ] Property rune damage (Flaming +1d6 fire, etc.)
- [ ] Sneak Attack (Rogue: +1d6 precision, +1d6 every odd level)
- [ ] Precision Edge (Ranger: +1d8 when Hunt Prey)
- [ ] Rage damage (Barbarian: +2 at level 1, increases with specialization)

**Impact**: Character sheet shows incorrect numbers, unusable in actual play.

**Priority**: 🔥 **HIGH** - Required for sheet to be usable at table

---

### 4. ❌ **LEVEL PROGRESSION SYSTEM** (0% Complete) - HIGH PRIORITY

**Current State**: Characters stuck at level 1 forever  
**Pathbuilder State**: Full progression from levels 1-20

#### Missing Progression Features:

**Ability Score Increases**:
- [ ] 4 ability boosts at level 5 (any 4 different stats)
- [ ] 4 ability boosts at level 10
- [ ] 4 ability boosts at level 15
- [ ] 4 ability boosts at level 20

**Class Features by Level** (0/20 levels implemented):
- [ ] Level 2-20 class features (varies by class, ~5-10 features per level range)
- [ ] Proficiency increases at specific levels (e.g., Fighter weapon → expert at 5)
- [ ] Class feature improvements (e.g., Sneak Attack dice increase)

**Feat Progression**:
- [ ] **Ancestry Feats**: Gained at levels 1, 5, 9, 13, 17 (5 total)
- [ ] **Class Feats**: Gained at levels 1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20 (11 total)
- [ ] **Skill Feats**: Gained at levels 2, 4, 6, 8, 10, 12, 14, 16, 18, 20 (10 total)
- [ ] **General Feats**: Gained at levels 3, 7, 11, 15, 19 (5 total)

**Skill Increases** (proficiency progression):
- [ ] Gain skill increase at levels 3, 5, 7, 9, 11, 13, 15, 17, 19 (9 total)
- [ ] Can boost: Trained → Expert, Expert → Master, Master → Legendary
- [ ] Or train new skill to Trained

**HP Progression**:
- [x] Current: Shows level 1 HP only
- [ ] Missing: `HP per level = Class HP + CON mod` (e.g., Fighter: 10 + CON per level)

**Spell Progression** (for casters):
- [ ] Spell slots increase by level (e.g., level 5 Wizard has 1st/2nd/3rd level slots)
- [ ] Cantrips heighten to half level (e.g., level 5 = 3rd level cantrips)
- [ ] Spells known increase (spontaneous casters)
- [ ] Focus points increase (max 3)

**Proficiency Progression**:
- [ ] Weapons: Automatic increases at class-specific levels
- [ ] Armor: Automatic increases at class-specific levels
- [ ] Saves: Automatic increases at class-specific levels
- [ ] Spellcasting: Trained → Expert → Master → Legendary
- [ ] Skills: Via skill increases
- [ ] Perception: Automatic increases

**Impact**: Characters can't level up, system is level 1 only. Makes 80% of feats unusable.

**Priority**: 🔥 **HIGH** - Required for campaign play

---

### 5. ❌ **ANCESTRIES & HERITAGES** (20% Complete)

**Current**: 8 ancestries, 110+ level 1 feats  
**Pathbuilder**: 40+ ancestries, 1000+ ancestry feats (all levels)

#### Ancestries with Feats (8):
- ✅ Human (7 feats)
- ✅ Elf (7 feats)
- ✅ Dwarf (6 feats)
- ✅ Gnome (7 feats)
- ✅ Goblin (7 feats)
- ✅ Halfling (7 feats)
- ✅ Orc (6 feats)
- ✅ Half-Elf (data exists, check feats)

#### Ancestries in System But NO Feats (6):
- ⚠️ Catfolk (data exists, 0 feats)
- ⚠️ Kobold (data exists, 0 feats)
- ⚠️ Ratfolk (data exists, 0 feats)
- ⚠️ Tengu (data exists, 0 feats)
- ⚠️ Leshy (data exists, 0 feats)
- ⚠️ Half-Orc (data exists, check feats)

#### Missing Core/Uncommon Ancestries (26+):
- **Core Uncommon**: Android, Aphorite, Beastkin, Changeling, Dhampir, Duskwalker, Fetchling, Fleshwarp, Ganzi, Ifrit, Oread, Sprite, Strix, Suli, Sylph, Undine

- **Ancestry Guide**: Anadi, Automaton, Azarketi, Conrasu, Gnoll, Goloma, Grippli, Kitsune, Lizardfolk, Poppet, Shisk, Shoony, Skeleton, Surki, Vanara, Vishkanya

- **Versatile Heritages** (special): Aasimar, Tiefling, Dhampir, Duskwalker, Changeling, Aphorite, Ganzi, Ifrit, Oread, Suli, Sylph, Undine, Beastkin, Reflection (14 versatile heritages that overlay any ancestry)

#### Missing Heritage Implementation:
- Heritage special abilities not implemented (most just descriptive text)
- Versatile heritage overlay system doesn't exist
- Heritage feat prerequisites not enforced

#### Missing Higher-Level Feats:
- **Level 5 ancestry feats**: ~350 feats across 40 ancestries
- **Level 9 ancestry feats**: ~250 feats
- **Level 13 ancestry feats**: ~150 feats
- **Level 17 ancestry feats**: ~100 feats
- **Total missing ancestry feats**: ~800 feats

**Impact**: Players limited to 8-14 ancestries, can't progress past level 1 feats.

**Priority**: 🟡 **MEDIUM** - Content expansion

---

### 6. ❌ **CLASSES & CLASS FEATS** (17% Complete)

**Current**: 4 classes with level 1 feats (Fighter, Rogue, Wizard, Ranger)  
**Pathbuilder**: 23 classes, 2000+ class feats (all levels)

#### Classes with Level 1 Feats (4):
- ✅ Fighter (6 feats)
- ✅ Rogue (4 feats)
- ✅ Wizard (6 feats) - but unusable without spells
- ✅ Ranger (5 feats)

#### Classes Defined But NO Feats (12):
- ❌ Alchemist (data exists, 0 feats)
- ❌ Barbarian (data exists, 0 feats) - instinct selection critical
- ❌ Bard (data exists, 0 feats) - muse selection critical
- ❌ Champion (data exists, 0 feats) - cause selection critical
- ❌ Cleric (missing entirely!)
- ❌ Druid (data exists?, 0 feats) - order selection critical
- ❌ Monk (data exists?, 0 feats) - stance feats important
- ❌ Sorcerer (data exists?, 0 feats) - bloodline selection critical
- ❌ Investigator (data exists, 0 feats)
- ❌ Oracle (data exists, 0 feats) - mystery selection critical
- ❌ Swashbuckler (data exists, 0 feats) - style selection critical
- ❌ Witch (data exists, 0 feats) - patron selection critical

#### Missing Classes Entirely (7):
- ❌ Magus (Secrets of Magic)
- ❌ Summoner (Secrets of Magic)
- ❌ Gunslinger (Guns & Gears)
- ❌ Inventor (Guns & Gears)
- ❌ Psychic (Dark Archive)
- ❌ Thaumaturge (Dark Archive)
- ❌ Kineticist (Rage of Elements)

#### Missing Higher-Level Class Feats:
- **Level 2 feats**: ~120 feats (23 classes × ~5 feats each)
- **Level 4-20 feats**: ~1,800 feats across all classes
- **Total missing class feats**: ~1,900 feats

**Impact**: 19 of 23 classes unplayable. Cannot progress beyond level 1.

**Priority**: 🔥 **CRITICAL** - Most classes blocked

---

### 7. ❌ **SKILL FEATS** (0% Complete) - MEDIUM PRIORITY

**Current State**: None  
**Pathbuilder State**: 200+ skill feats

#### Missing Skill Feat System:

**Skill Feat Selection**:
- [ ] Level 2, 4, 6, 8, 10, 12, 14, 16, 18, 20 (10 skill feats by level 20)
- [ ] Background grants 1 skill feat at level 1
- [ ] Some classes grant bonus skill feats (Rogue, Investigator)

**Common Skill Feats** (Level 1, need ~50):
- [ ] Assurance (auto-10 on checks) - 16 versions, one per skill
- [ ] Armor Assist, Battle Medicine, Cat Fall
- [ ] Experienced Smuggler, Experienced Tracker
- [ ] Forager, Group Coercion, Group Impression
- [ ] Glad-Hand, Hobnobber, Intimidating Glare
- [ ] Lengthy Diversion, Lie to Me, Pickpocket
- [ ] Quick Climb, Quick Coercion, Quick Disguise
- [ ] Quick Jump, Quick Recognition, Quick Squeeze
- [ ] Quiet Allies, Rapid Mantel, Read Lips
- [ ] Recognize Spell, Ride, Shameless Request
- [ ] Shield Block (also general feat), Sign Language
- [ ] Specialty Crafting, Steady Balance, Streetwise
- [ ] Student of the Canon, Survey Wildlife, Terrain Stalker
- [ ] Titan Wrestler, Train Animal, Trick Magic Item
- [ ] Underwater Marauder, Unmistakable Lore, Wary Disarmament

**Higher-Level Skill Feats**: ~150 more feats (levels 2-20)

**Impact**: Missing 10+ feats per character. Limits skill versatility significantly.

**Priority**: 🟡 **MEDIUM** - Nice to have, not critical for function

---

### 8. ❌ **GENERAL FEATS** (0% Complete) - MEDIUM PRIORITY

**Current State**: None  
**Pathbuilder State**: 100+ general feats

#### Missing General Feat System:

**General Feat Selection**:
- [ ] Levels 3, 7, 11, 15, 19 (5 general feats by level 20)
- [ ] Humans get bonus general feat at level 1 (via Natural Ambition or General Training ancestry feat)

**Common General Feats** (Level 1, need ~30):
- [ ] Adopted Ancestry, Ancestral Paragon
- [ ] Armor Proficiency, Breath Control
- [ ] Canny Acumen (boost save/perception)
- [ ] Diehard (avoid death)
- [ ] Fast Recovery, Feather Step
- [ ] Fleet (speed +5)
- [ ] Incredible Initiative (+2 initiative)
- [ ] Ride, Shield Block
- [ ] Toughness (max HP increase)
- [ ] Trick Magic Item
- [ ] Untrained Improvisation
- [ ] Weapon Proficiency

**Higher-Level General Feats**: ~70 more feats (levels 3-20)

**Impact**: Missing 5-6 feats per character. Limits customization.

**Priority**: 🟡 **MEDIUM** - Nice to have

---

### 9. ⚠️ **EQUIPMENT & INVENTORY** (30% Complete) - HIGH PRIORITY

**Current State**: Basic checkbox list with gold budget  
**Pathbuilder State**: Full inventory with stats, bulk, runes

#### Missing Equipment Features:

**Weapon System** (0% stats implemented):
- [ ] Weapon proficiency by class (Simple, Martial, Advanced, Unarmed)
- [ ] Attack bonus calculation (see Derived Stats)
- [ ] Damage die (1d4, 1d6, 1d8, 1d10, 1d12, etc.)
- [ ] Critical hits (×2 damage + critical specialization effect)
- [ ] Weapon traits:
  - [ ] Agile (-4 MAP instead of -5)
  - [ ] Finesse (use DEX for attack)
  - [ ] Deadly (extra die on crit)
  - [ ] Fatal (bigger die on crit)
  - [ ] Reach, Trip, Disarm, etc.
- [ ] Weapon runes:
  - [ ] Striking/Greater Striking/Major Striking (+1d/+2d/+3d weapon dice)
  - [ ] Potency (+1/+2/+3 to hit)
  - [ ] Property runes (Flaming, Frost, Shock, Holy, Unholy, etc.)

**Armor System** (0% stats implemented):
- [ ] Armor proficiency by class (Unarmored, Light, Medium, Heavy)
- [ ] AC bonus calculation (see Derived Stats)
- [ ] DEX cap (+5 unarmored, +4 light, +2 medium, +0 heavy)
- [ ] Check penalty (-1 to -5 on STR/DEX checks)
- [ ] Speed penalty (-5 medium, -10 heavy if STR < requirement)
- [ ] Strength requirement (10-18 depending on armor)
- [ ] Armor traits (Bulwark, Flexible, Noisy, etc.)
- [ ] Armor runes:
  - [ ] Resilient/Greater Resilient/Major Resilient (+1/+2/+3 saves)
  - [ ] Potency (+1/+2/+3 AC)
  - [ ] Property runes (Energy Resistant, Fortification, etc.)

**Shields**:
- [ ] Shield AC bonus (+2 when raised)
- [ ] Hardness (damage reduction)
- [ ] HP (shield can break)
- [ ] Shield Block reaction

**Inventory Management**:
- [ ] Bulk system:
  - [ ] Light items (L): 10 per 1 Bulk
  - [ ] Normal items: 1 Bulk, 2 Bulk, etc.
  - [ ] Encumbered (5 + STR mod Bulk): -10 speed, clumsy 1
  - [ ] Overloaded (10 + STR mod Bulk): can't move
- [ ] Worn vs stowed (10 items worn, rest stowed)
- [ ] Containers (backpack, sack, etc.)
- [ ] Quantity tracking (arrows, gold, potions)

**Consumables**:
- [ ] Alchemical items database:
  - [ ] Bombs (acid flask, alchemist's fire, etc.)
  - [ ] Elixirs (healing potions, mutagens)
  - [ ] Poisons
  - [ ] Tools
- [ ] Potions (healing, buffs)
- [ ] Scrolls (for spellcasters)
- [ ] Wands
- [ ] Ammunition tracking (arrows, bolts, bullets)

**Magic Items**:
- [ ] Permanent items (Bag of Holding, etc.)
- [ ] Worn items (Bracers, Cloaks, etc.)
- [ ] Item activation (invested, activated, etc.)

**Impact**: Can't calculate correct attack/AC/damage. Equipment is cosmetic only.

**Priority**: 🔥 **HIGH** - Required for functional combat stats

---

### 10. ❌ **ACTIONS & ACTIVITIES** (0% Complete) - LOW PRIORITY

**Current State**: None  
**Pathbuilder State**: Actions panel with full action list

#### Missing Action System:

**Basic Actions** (3-action economy):
- [ ] ◆ Strike (attack)
- [ ] ◆ Stride (move)
- [ ] ◆◆ Cast a Spell (varies)
- [ ] ◆ Raise a Shield (+2 AC)
- [ ] ◆ Interact (use item)
- [ ] Release (drop item)
- [ ] ◆ Seek (search)
- [ ] ◆ Hide
- [ ] ◆ Sneak
- [ ] ◆ Step (5 ft without provoking)
- [ ] Crawl, Drop Prone, Stand

**Skill Actions**:
- [ ] ◆ Climb, Swim, Jump (Athletics)
- [ ] ◆ Grapple, Shove, Trip, Disarm
- [ ] ◆◆ High Jump, Long Jump
- [ ] ◆ Hide, Sneak (Stealth)
- [ ] ◆ Demoralize (Intimidation)
- [ ] ◆ Feint (Deception)
- [ ] ◆ Create a Diversion
- [ ] ◆ Recall Knowledge (any mental skill)
- [ ] ◆ Aid (help ally)

**Class-Specific Actions**:
- [ ] ◆ Rage (Barbarian)
- [ ] ◆ Hunt Prey (Ranger)
- [ ] ◆◆ Flurry of Blows (Monk)
- [ ] ⚡ Attack of Opportunity (Fighter reaction)
- [ ] ⚡ Champion's Reaction (Champion reaction, varies by cause)

**Feat-Granted Actions**:
- [ ] ◆◆ Power Attack
- [ ] ◆◆ Sudden Charge
- [ ] ◆ Point-Blank Shot

**Reactions** (⚡):
- [ ] Attack of Opportunity
- [ ] Shield Block
- [ ] Champion's Reaction

**Free Actions** (🆓):
- [ ] Drop item
- [ ] Command animal
- [ ] Release spell

**Impact**: Reference only. Can't track action economy in combat. Low priority since groups usually play on VTT or theater of mind.

**Priority**: 🟢 **LOW** - Nice UI feature, not essential

---

### 11. ❌ **OTHER MISSING SYSTEMS**

#### **Languages** (60% complete):
- [x] Shows ancestry base languages
- [x] Shows bonus languages from INT  
- [ ] Missing: Language selection UI (choose from 40+ languages)
- [ ] Missing: Regional/uncommon languages by access
- [ ] Missing: Druidic (Druid only)

#### **Deities** (5% complete):
- [x] Text field for deity name
- [ ] Missing: 90+ deity database (Core, Lost Omens)
- [ ] Missing: Deity details:
  - [ ] Alignment
  - [ ] Portfolio
  - [ ] Edicts (required behavior)
  - [ ] Anathema (forbidden behavior)
  - [ ] Domains (3-4 per deity for Cleric)
  - [ ] Favored weapon
  - [ ] Divine font preference
- [ ] Missing: Cleric domain selection based on deity
- [ ] Missing: Champion deity restriction (must match cause alignment)
- [ ] Missing: Deity-specific spells

#### **Senses** (50% complete):
- [x] Low-Light Vision displayed
- [x] Darkvision displayed
- [ ] Missing: Senses from feats/items
- [ ] Missing: Range display (e.g., Darkvision 60 ft)
- [ ] Missing: Scent, Tremorsense, Lifesense, etc.

#### **Hero Points** (50% complete):
- [x] Displays hero points (1 at start)
- [ ] Missing: Spending UI (reroll check, avoid death)
- [ ] Missing: Reroll tracking
- [ ] Missing: Death prevention (spend all hero points to stabilize)

#### **Conditions** (0% complete):
- [ ] No condition tracking (Blinded, Confused, Drained, Dying, Enfeebled, Fatigued, Frightened, Paralyzed, Petrified, Prone, Quickened, Sickened, Slowed, Stunned, Stupefied, Unconscious, Wounded)
- [ ] No penalty/bonus calculations from conditions
- [ ] No duration tracking

#### **Exploration Mode** (0% complete):
- [ ] No exploration activities:
  - Avoid Notice, Defend, Detect Magic, Follow the Expert, Hustle, Investigate, Refocus, Repeat a Spell, Scout, Search, Track
- [ ] No travel speed calculations
- [ ] No random encounter tracking

#### **Downtime** (0% complete):
- [ ] No downtime activities (Craft, Earn Income, Treat Disease, Long-Term Rest, etc.)
- [ ] No crafting system beyond buying items

#### **Archetypes** (0% complete):
- [ ] 100+ archetypes missing:
  - Multiclass dedications (all 23 classes)
  - Prestige archetypes (Hellknight, Pathfinder Agent, etc.)
  - Magic archetypes (Elementalist, Flexible Spellcaster, etc.)
  - Martial archetypes (Dual-Weapon Warrior, Archer, etc.)
  - General archetypes (Medic, Linguist, etc.)
- [ ] Archetype feat integration
- [ ] Dedication feat requirements (multiclass dedications replace class feats)

#### **Versatile Heritage System** (0% complete):
- [ ] No Aasimar/Tiefling/Dhampir/etc. overlay system
- [ ] No heritage merging with base ancestry
- [ ] No versatile heritage feat chains

#### **Character Export** (0% complete):
- [ ] No PDF export
- [ ] No printable sheet
- [ ] No JSON export for VTT integration
- [ ] No character sharing

#### **Variant Rules** (0% complete):
- [ ] No Free Archetype variant
- [ ] No Dual-Class variant
- [ ] No Proficiency without Level variant
- [ ] No Stamina variant

---

## Parity Scorecard

| System | Current | Pathbuilder | % | Priority |
|--------|---------|-------------|---|----------|
| **Core Systems** |
| Ability Scores | Full | Full | **100%** | ✅ Done |
| Ancestries | 8 | 40+ | **20%** | 🟡 Medium |
| Heritages | Partial | Full | **50%** | 🟡 Medium |
| Backgrounds | 35+ | 60+ | **60%** | 🟢 Low |
| Classes (basic data) | 16 | 23 | **70%** | 🟡 Medium |
| **Feats** |
| Ancestry Feats (L1) | 110 | 280 | **39%** | 🟡 Medium |
| Ancestry Feats (all) | 110 | 1000 | **11%** | 🟡 Medium |
| Class Feats (L1) | 25 | 140 | **18%** | 🔥 Critical |
| Class Feats (all) | 25 | 2000 | **1%** | 🟡 Medium |
| Skill Feats | 0 | 200 | **0%** | 🟡 Medium |
| General Feats | 0 | 100 | **0%** | 🟡 Medium |
| **Class Features** |
| Class Features | ~10 | 500+ | **2%** | 🔥 **CRITICAL** |
| Spellcasting | 0 | Full | **0%** | 🔥 **CRITICAL** |
| **Combat** |
| Derived Stats | Partial | Full | **30%** | 🔥 **HIGH** |
| Equipment Stats | 0 | Full | **0%** | 🔥 **HIGH** |
| Actions UI | 0 | Full | **0%** | 🟢 Low |
| **Progression** |
| Level System | 0 | 1-20 | **0%** | 🔥 **HIGH** |
| Skill Progression | 0 | Full | **0%** | 🟡 Medium |
| **Other** |
| Deities | 5% | Full | **5%** | 🟡 Medium |
| Archetypes | 0 | 100+ | **0%** | 🟢 Low |
| Conditions | 0 | Full | **0%** | 🟢 Low |
| **OVERALL** | | | **~20%** | |

---

## Priority Roadmap

### 🔥 Phase 1: Critical Blockers (4 weeks)

**Goal**: Make existing classes functional and playable

#### Week 1: Spellcasting Foundation
- [ ] Create spell database structure (start with 50 most common spells)
- [ ] Build spell selection UI (cantrips + 1st level)
- [ ] Implement spell DC/attack calculations
- [ ] Add spell slots tracking
- [ ] Test with Wizard (already has feats)

#### Week 2: Spellcasting Expansion  
- [ ] Add Cleric class with domains
- [ ] Add Divine spellcasting
- [ ] Add domain selection UI
- [ ] Add deity database (20 most common deities)
- [ ] Test prepared casting vs known spells

#### Week 3: Class Features
- [ ] Barbarian: Instincts selection + Rage mechanic
- [ ] Sorcerer: Bloodline selection + bloodline spells
- [ ] Rogue: Racket selection implementation
- [ ] Ranger: Hunter's Edge implementation
- [ ] Champion: Cause + deity selection

#### Week 4: Combat Calculations
- [ ] Weapon attack bonuses (proficiency + stat)
- [ ] Weapon damage calculation
- [ ] AC calculation with armor bonuses
- [ ] Proficiency system implementation
- [ ] Strike display with correct numbers

**Deliverable**: 8 playable classes (Fighter, Rogue, Wizard, Ranger, Cleric, Barbarian, Sorcerer, Champion) with correct combat stats.

---

### 🔥 Phase 2: Core Content (3 weeks)

**Goal**: Add remaining Core Rulebook classes and expand content

#### Week 5: More Classes
- [ ] Add Bard (muses, composition spells, occult casting)
- [ ] Add Druid (orders, wild shape, primal casting)
- [ ] Add Monk (stances, Flurry of Blows, unarmed combat)
- [ ] Add Alchemist (research fields, quick alchemy, formulas)

#### Week 6: Class Feats
- [ ] Add level 1 feats for 8 remaining classes
- [ ] Add level 2 feats for all 12 Core classes
- [ ] Add level 4 feats (critical for level 4 characters)

#### Week 7: Ancestry Expansion
- [ ] Add feats for 6 existing ancestries (Catfolk, Kobold, Ratfolk, Tengu, Leshy, Half-Orc)
- [ ] Add 5 new ancestries (Anadi, Kitsune, Automaton, Azarketi, Gnoll)
- [ ] Add versatile heritages system framework

**Deliverable**: 12 Core Rulebook classes fully playable at levels 1-4.

---

### 🟡 Phase 3: Progression System (2 weeks)

**Goal**: Enable leveling beyond level 1

#### Week 8: Level 2-5 System
- [ ] Build level-up UI
- [ ] Implement skill increases
- [ ] Implement ability boosts (level 5)
- [ ] Add feat progression (ancestry/class/skill/general)
- [ ] Update HP calculation per level
- [ ] Add proficiency progression

#### Week 9: Level 6-10 System
- [ ] Extend to level 10
- [ ] Add spell slot progression
- [ ] Add class feature scaling
- [ ] Test full progression path

**Deliverable**: Characters can level from 1 to 10.

---

### 🟡 Phase 4: Equipment & Combat (2 weeks)

**Goal**: Proper equipment stats and inventory

#### Week 10: Equipment Stats
- [ ] Weapon damage dice database
- [ ] Weapon traits implementation
- [ ] Armor AC bonuses
- [ ] DEX cap enforcement
- [ ] Speed penalties
- [ ] Runes framework (potency, striking, resilient)

#### Week 11: Inventory System
- [ ] Bulk calculation
- [ ] Encumbered/overloaded states
- [ ] Consumables tracking
- [ ] Magic item database (50 common items)
- [ ] Shield system

**Deliverable**: Equipment has correct stats, combat numbers accurate.

---

### 🟢 Phase 5: Polish & Expansion (Ongoing)

**Goal**: Add remaining content and quality of life

#### Weeks 12+:
- [ ] Add APG classes (Investigator, Oracle, Swashbuckler, Witch)
- [ ] Add SoM classes (Magus, Summoner)
- [ ] Add skill feats & general feats
- [ ] Expand spell database (to 200+ spells)
- [ ] Add archetypes system
- [ ] Add levels 11-20 progression
- [ ] Add rare ancestries
- [ ] Add actions UI panel
- [ ] Add conditions tracking
- [ ] Add PDF export
- [ ] Add character sharing

**Deliverable**: 80% feature parity with Pathbuilder 2e.

---

## Quick Wins for Next Session (8 hours)

To maximize impact quickly:

### 1. **Spell Selection UI** (3 hours)
- Create basic cantrip selection (5 cantrips for Wizard)
- Add 1st level spell selection (Wizard spellbook: 10 known)
- Show spell slots (Wizard level 1: 2 × 1st level)
- Calculate spell DC: `10 + INT + 2 (trained)`

**Impact**: Wizard becomes playable. Framework for 10 other casters.

### 2. **Cleric Class** (2 hours)  
- Add Cleric class data
- Add 6 essential domains (Healing, Fire, Death, Knowledge, Nature, Sun)
- Add divine font selection (3 extra heal or harm)
- Add 8 Cleric level 1 feats

**Impact**: Most popular spellcasting class available.

### 3. **Combat Stats Fix** (2 hours)
- Implement weapon attack formula
- Implement weapon damage dice
- Implement AC with armor bonus
- Update character sheet with correct calculations

**Impact**: Character sheet becomes usable in actual play.

### 4. **Barbarian Instincts** (1 hour)
- Add instinct selection UI (Animal, Dragon, Fury, Giant, Spirit, Superstition)
- Add Rage action description
- Add 8 Barbarian level 1 feats (instinct feats + basics)

**Impact**: Barbarian becomes playable.

**Total**: 8 hours = ~10-15% more parity, 4 more playable classes

---

## Conclusion

User's intuition is **absolutely correct**: We have solid infrastructure but are missing:

- **80% of character creation choices** (classes, ancestries, feats, spells)
- **Critical mechanical calculations** (attack bonuses, damage, spell DCs)
- **Entire progression system** (leveling, feat selection, ability boosts)

### Current State: ~20% Parity
- ✅ UI/UX foundation excellent
- ✅ Ability scores perfect
- ✅ 8 ancestries with 110 feats
- ✅ 4 classes with 25 feats
- ⚠️ Missing spellcasting entirely
- ⚠️ Missing class features
- ⚠️ Missing leveling system
- ⚠️ Missing 19 classes
- ⚠️ Missing 1,800+ feats

### Realistic Timeline to 80% Parity: 
**12-16 weeks** of focused development.

### Immediate Next Steps:
1. Spell selection system (critical for 11 classes)
2. Combat stat calculations (critical for playability)
3. Class feature implementation (makes classes feel distinct)
4. Level progression system (enables campaigns)

**The foundation is excellent. Now we need to systematically fill in the missing game content.**
