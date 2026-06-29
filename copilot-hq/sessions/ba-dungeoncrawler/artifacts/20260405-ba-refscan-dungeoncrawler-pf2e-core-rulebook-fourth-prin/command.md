# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260405-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 5584–5883 of 103266 (5% through this book)  
**Features generated this cycle so far:** 20 / 30 cap  
**Progress state file:** tmp/ba-scan-progress/dungeoncrawler.json  

## Your task

Read the source material below and extract **implementable game features** for the dungeoncrawler product.

For each distinct mechanic, rule, creature, spell, item, or system described in the text:
1. Decide if it is **relevant** to the dungeoncrawler digital game (skip pure lore, typography, credits).
2. If relevant and NOT already implemented (check `features/` directory), create a feature stub.
3. Stop when you have generated **30 total features this cycle** (across all scan tasks this release).

## Creating a feature stub

For each feature, create `features/dc-<slug>/feature.md` using this template:

```markdown
# Feature Brief: <title>

- Work item id: dc-<slug>
- Website: dungeoncrawler
- Module: dungeoncrawler_content (or dungeoncrawler_tester)
- Status: pre-triage
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-05

## Goal

<One paragraph: what this feature adds to the dungeoncrawler game. Written for a PM who will decide whether to include it.>

## Source reference

> <Direct quote or paraphrase of the relevant paragraph(s) from the reference material>

## Implementation hint

<Brief note on what Drupal module work this likely implies — content type, field, API endpoint, AI prompt change, etc.>

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
```

## Feature slug convention

Use the book abbreviation + short descriptor:
- Core Rulebook → `dc-cr-<descriptor>`
- Advanced Players Guide → `dc-apg-<descriptor>`
- Bestiary 1/2/3 → `dc-b1-<descriptor>` / `dc-b2-` / `dc-b3-`
- Secrets of Magic → `dc-som-<descriptor>`
- Gamemastery Guide → `dc-gmg-<descriptor>`
- Guns and Gears → `dc-gg-<descriptor>`
- Gods and Magic → `dc-gam-<descriptor>`

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json`:
   - Set `books[0].last_line` → 5883
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260405-dungeoncrawler-release-b`
2. Write outbox: list each feature stub created (id + one-line description), total count, lines covered.

## Book outline (for orientation)

# Outline: PF2E Core Rulebook - Fourth Printing

## Source Information
- **File**: PF2E Core Rulebook - Fourth Printing.txt
- **Size**: 103,265 lines
- **Publisher**: Paizo Inc.
- **Edition**: Fourth Printing, Second Edition

## Document Structure

### Front Matter
The document began with publication information, including game designers (Logan Bonner, Jason Bulmahn, Stephen Radney-MacFarland, and Mark Seifter), additional writing credits, editorial staff, cover and interior artists, art direction, and publishing information.

### Table of Contents (Line ~92)
The table of contents provided a comprehensive overview of the book's 11 chapters plus appendices.

---

## Main Content Sections

### Chapter 1: Introduction (Page 2)
This chapter introduced the basics of roleplaying games, provided an overview of the rules, and presented an example of play. It covered how to build a character and how to level up characters after adventuring.

### Chapter 2: Ancestries & Backgrounds (Page 32)
This chapter allowed players to choose their character's ancestry (dwarves, elves, gnomes, goblins, halflings, or humans) and select a background that established what the character did before becoming an adventurer. The chapter also detailed languages.

### Chapter 3: Classes (Page 66)
This chapter presented 12 character classes including fighters, clerics, wizards, and alchemists. It detailed animal companions, familiars, and multiclass archetypes that expanded character abilities.

### Chapter 4: Skills (Page 232)
This chapter covered the execution of acrobatic maneuvers, tricking enemies, tending to allies' wounds, and learning about strange creatures and magic through skill training.

### Chapter 5: Feats (Page 254)
This chapter explained how to expand capabilities by selecting general feats that improved statistics or granted new actions. It included skill feats tied directly to character skills.

### Chapter 6: Equipment (Page 270)
This chapter provided a vast arsenal of armor, weapons, and gear for adventure preparation.

### Chapter 7: Spells (Page 296)
This chapter taught how to kindle magic. It included rules for spellcasting, hundreds of spell descriptions, focus spells used by certain classes, and rituals.

### Chapter 8: The Age of Lost Omens (Page 416)
This chapter explored the world of Golarion, allowing characters to delve into secrets of ancient empires and claim heroic destinies in the Age of Lost Omens.

### Chapter 9: Playing the Game (Page 442)
This chapter contained comprehensive rules for playing the game, using actions, and calculating statistics. It covered encounters, exploration, downtime, and everything needed for combat.

### Chapter 10: Game Mastering (Page 482)
This chapter provided advice for preparing and running games, including rules for setting Difficulty Classes, granting rewards, managing environments, and creating hazards.

### Chapter 11: Crafting & Treasure (Page 530)
This chapter detailed treasure awards ranging from magic weapons to alchemical compounds and transforming statues. It contained rules for activating and wearing alchemical and magical items.

---

## Appendices

### Conditions Appendix (Page 618)
This appendix detailed conditions ranging from dying to slowed to frightened, covering common benefits and drawbacks typically resulting from spells or special actions.

### Character Sheet (Page 624)
A character sheet template was provided for player use.

### Glossary and Index (Page 628)
A combined rules glossary and book index enabled quick location of needed rules.

---

## Summary
The Core Rulebook served as the foundational reference for Pathfinder Second Edition, providing complete rules for both players and Game Masters. Its organization progressed logically from character creation (ancestries, classes) through character capabilities (skills, feats, equipment, spells) to world-setting information and gameplay mechanics, concluding with Game Master tools and reference materials.

---

## Source material (lines 5584–5883)

```
and earns you the disdain of
other dwarves.

Death Warden Dwarf
Your ancestors have been tomb guardians for generations, and the power they
cultivated to ward off necromancy has passed on to you. If you roll a success on a
saving throw against a necromancy effect, you get a critical success instead.

Ancestries &
Backgrounds
Classes
Skills
Feats
Equipment
Spells
The Age of
Lost OMENS
Playing the
Game
Game
mastering
Crafting
& Treasure

4121789

Appendix

Darkvision
You can see in darkness and
dim light just as well as you can
see in bright light, though your
vision in darkness is in black
and white.

Ancient-Blooded Dwarf

Introduction

Ability Boosts

Traits

Dwarf Heritages

2

Forge Dwarf
You have a remarkable adaptation to hot environments from ancestors who inhabited
blazing deserts or volcanic chambers beneath the earth. This grants you fire resistance
equal to half your level (minimum 1), and you treat environmental heat effects as
if they were one step less extreme (incredible heat becomes extreme, extreme heat
becomes severe, and so on).

35

16415384

4121790

4121790


Core Rulebook

Rock Dwarf
Your ancestors lived and worked among the great ancient
stones of the mountains or the depths of the earth. This
makes you solid as a rock when you plant your feet. You
gain a +2 circumstance bonus to your Fortitude or Reflex
DC against attempts to Shove or Trip you. This bonus
also applies to saving throws against spells or effects that
attempt to knock you prone.
In addition, if any effect would force you to move 10
feet or more, you are moved only half the distance.

Strong-Blooded Dwarf
Your blood runs hearty and strong, and you can shake
off toxins. You gain poison resistance equal to half your
level (minimum 1), and each of your successful saving
throws against a poison affliction reduces its stage by 2,
or by 1 for a virulent poison. Each critical success against
an ongoing poison reduces its stage by 3, or by 2 for a
virulent poison.

Ancestry Feats

At 1st level, you gain one ancestry feat, and you gain an
additional ancestry feat every 4 levels thereafter (at 5th,
9th, 13th, and 17th level). As a dwarf, you select from
among the following ancestry feats.
16415385

16415385

1ST LEVEL
DWARVEN LORE

FEAT 1

DWARF

You eagerly absorbed the old stories and traditions of your
ancestors, your gods, and your people, studying in subjects
and techniques passed down for generation upon generation.
You gain the trained proficiency rank in Crafting and
Religion. If you would automatically become trained in one
of those skills (from your background or class, for example),
you instead become trained in a skill of your choice. You also
become trained in Dwarven Lore.

DWARVEN WEAPON FAMILIARITY

FEAT 1

DWARF

Your kin have instilled in you an affinity for hard-hitting
weapons, and you prefer these to more elegant arms. You are
trained with the battle axe, pick, and warhammer.
You also gain access to all uncommon dwarf weapons. For
the purpose of determining your proficiency, martial dwarf
weapons are simple weapons and advanced dwarf weapons
are martial weapons.

ROCK RUNNER

FEAT 1

DWARF

Your innate connection to stone makes you adept at moving
across uneven surfaces. You can ignore difficult terrain caused
by rubble and uneven ground made of stone and earth.

In addition, when you use the Acrobatics skill to Balance on
narrow surfaces or uneven ground made of stone or earth, you
aren’t flat-footed, and when you roll a success at one of these
Acrobatics checks, you get a critical success instead.

STONECUNNING

FEAT 1

DWARF

You have a knack for noticing even small inconsistencies and
craftsmanship techniques in the stonework around you. You
gain a +2 circumstance bonus to Perception checks to notice
unusual stonework. This bonus applies to checks to discover
mechanical traps made of stone or hidden within stone.
If you aren’t using the Seek action or searching, the GM
automatically rolls a secret check for you to notice unusual
stonework anyway. This check doesn’t gain the circumstance
bonus, and it takes a –2 circumstance penalty.

UNBURDENED IRON

FEAT 1

DWARF

You’ve learned techniques first devised by your ancestors
during their ancient wars, allowing you to comfortably wear
massive suits of armor. Ignore the reduction to your Speed
from any armor you wear.
In addition, any time you’re taking a penalty to your Speed
from some other reason (such as from the encumbered
condition or from a spell), deduct 5 feet from the penalty.
For example, the encumbered condition normally gives a
–10-foot penalty to Speed, but it gives you only a –5-foot
penalty. If your Speed is taking multiple penalties, pick only
one penalty to reduce.

VENGEFUL HATRED

4121790

FEAT 1

DWARF

You heart aches for vengeance against those who have
wronged your people. Choose one of the following dwarven
ancestral foes when you gain Vengeful Hatred: drow, duergar,
giant, or orc. You gain a +1 circumstance bonus to damage
with weapons and unarmed attacks against creatures with
that trait. If your attack would deal more than one weapon die
of damage (as is common at higher levels than 1st), the bonus
is equal to the number of weapon dice or unarmed attack dice.
In addition, if a creature critically succeeds at an attack
against you and deals damage to you, you gain your bonus
to damage against that creature for 1 minute regardless of
whether it has the chosen trait.
Special Your GM can add appropriate creature traits to the
ancestral foes list if your character is from a community that
commonly fights other types of enemies.

5TH LEVEL
BOULDER ROLL [two-actions]

FEAT 5

DWARF

Prerequisites Rock Runner
Your dwarven build allows you to push foes around, just like

36

16415385

4121791

4121791


Ancestries & backgrounds

2

DWARVEN
ADVENTURERS

Introduction

Dwarven adventurers tend to
work as treasure hunters or
sellswords. They often leave their
citadels and subterranean cities
in search of wealth to enrich their
homeland or to reclaim long-lost
dwarven treasures or lands taken
by the enemies of their kin.
Typical dwarven backgrounds
include acolyte, artisan, merchant,
miner, and warrior. Dwarves excel
at many of the martial classes,
such as barbarian, fighter, monk,
and ranger, but they also make
excellent clerics and druids.

Ancestries &
Backgrounds

a mighty boulder tumbles through a subterranean cavern. Take a Step into the square of
a foe that is your size or smaller, and the foe must move into the empty space directly
behind it. The foe must move even if doing so places it in harm’s way. The foe can attempt
a Fortitude saving throw against your Athletics DC to block your Step. If the foe attempts
this saving throw, unless it critically succeeds, it takes bludgeoning damage equal to your
level plus your Strength modifier.
If the foe can’t move into an empty space (if it is surrounded by solid objects or other
creatures, for example), your Boulder Roll has no effect.

DWARVEN WEAPON CUNNING

FEAT 5

DWARF

Prerequisites Dwarven Weapon Familiarity
You’ve learned cunning techniques to get the best effects out of your dwarven weapons.
Whenever you critically hit using a battle axe, pick, warhammer, or a dwarf weapon, you
apply the weapon’s critical specialization effect.

9TH LEVEL
MOUNTAIN’S STOUTNESS

FEAT 9

DWARF

Your hardiness lets you withstand more punishment than most before going down. Increase
your maximum Hit Points by your level. When you have the dying condition, the DC of
your recovery checks is equal to 9 + your dying value (instead of 10 + your dying value).
If you also have the Toughness feat, the Hit Points gained from it and this feat
are cumulative, and the DC of your recovery checks
is equal to 6 + your dying value.
16415386

16415386

STONEWALKER

FEAT 9

DWARF

You have a deep reverence for and connection
to stone. You gain meld into stone as a 3rd‑level
divine innate spell that you can cast once
per day.
If you have the Stonecunning dwarf ancestry
feat, you can attempt to find unusual stonework and
stonework traps that require legendary proficiency
in Perception. If you have both Stonecunning and
legendary proficiency in Perception, when
you’re not Seeking and the GM rolls a secret
check for you to notice unusual stonework,
you keep the bonus from Stonecunning and
```
- Agent: ba-dungeoncrawler
