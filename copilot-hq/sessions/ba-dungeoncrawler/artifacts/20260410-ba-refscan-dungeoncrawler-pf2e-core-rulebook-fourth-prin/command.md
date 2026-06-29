# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260410-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 7084–7383 of 103266 (7% through this book)  
**Features generated this cycle so far:** 66 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 7084–7383
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-10

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
   - Set `books[0].last_line` → 7383
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260410-dungeoncrawler-release-b`
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

## Source material (lines 7084–7383)

```
have protected them or offered a sympathetic ear. Goblins tend to assume for their
own protection that members of taller ancestries, which goblins often refer to
colloquially as “longshanks,” won’t treat them kindly. Learning to trust longshanks
is difficult for a goblin, and it’s been only in recent years that such a partnership
has even been an option. However, their attitude as a people is changing rapidly,
and their short lifespans and poor memories help them adapt quickly.

Alignment and Religion
Even well-intentioned goblins have trouble following the rules, meaning they’re
rarely lawful. Most goblin adventurers are chaotic neutral or chaotic good.
Organized worship confounds goblins, and most of them would rather pick their
own deities, choosing powerful monsters, natural wonders, or anything else they
find fascinating. Longshanks might have books upon books about the structures of
divinity, but to a goblin, anything can be a god if you want it to. Goblins who spend
time around people of other ancestries might adopt some of their beliefs, though,
and many goblin adventurers adopt the worship of Cayden Cailean.

Names
Goblins keep their names simple. A good name should be easy to pronounce, short
enough to shout without getting winded, and taste good to say. The namer often
picks a word that rhymes with something they like so that writing songs is easier.
Since there aren’t any real traditions regarding naming in goblin culture, children
often name themselves once they’re old enough to do something resembling talking.

Hit Points
6

Size
Small

Speed
25 feet

Ak, Bokker, Frum, Guzmuk, Krobby, Loohi, Mazmord, Neeka, Omgot, Ranzak,
Rickle, Tup, Wakla, Yonk, Zibini

Introduction
Ancestries &
Backgrounds
Classes
Skills

Ability Boosts
Dexterity
Charisma
Free

Ability Flaw
Wisdom

Languages
Common
Goblin
Additional languages equal to
your Intelligence modifier (if it’s
positive). Choose from Draconic,
Dwarven, Gnoll, Gnomish,
Halfling, Orcish, and any other
languages to which you have
access (such as the languages
prevalent in your region).

Traits

Sample Names

2

Goblin
Humanoid

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

4121801

Appendix

Darkvision

Goblin Heritages

Goblins, especially those of different tribes, have all sorts of physiological differences,
which they often discover only through hazardous “experiments.” Choose one of the
following goblin heritages at 1st level.

You can see in darkness and
dim light just as well as you
can see in bright light, though
your vision in darkness is in
black and white.

Charhide Goblin
Your ancestors have always had a connection to fire and a thicker skin, which allows
you to resist burning. You gain fire resistance equal to half your level (minimum 1).
You can also recover from being on fire more easily. Your flat check to remove
persistent fire damage is DC 10 instead of DC 15, which is reduced to DC 5 if
another creature uses a particularly appropriate action to help.

Irongut Goblin
You can subsist on food that most folks would consider spoiled. You can keep yourself
fed with poor meals in a settlement as long as garbage is readily available, without using
the Subsist downtime activity. You can eat and drink things when you are sickened.
You gain a +2 circumstance bonus to saving throws against afflictions, against
gaining the sickened condition, and to remove the sickened condition. When you
roll a success on a Fortitude save affected by this bonus, you get a critical success
instead. All these benefits apply only when the affliction or condition resulted from
something you ingested.

47

16415396

4121802

4121802


Core Rulebook

Razortooth Goblin
Your family’s teeth are formidable weapons. You gain
a jaws unarmed attack that deals 1d6 piercing damage.
Your jaws are in the brawling group and have the finesse
and unarmed traits.

Snow Goblin

Unbreakable Goblin
You’re able to bounce back from injuries easily due to an
exceptionally thick skull, cartilaginous bones, or some
other mixed blessing. You gain 10 Hit Points from your
ancestry instead of 6. When you fall, reduce the falling
damage you take as though you had fallen half the distance.

Ancestry Feats

At 1st level, you gain one ancestry feat, and you gain an
additional ancestry feat every 4 levels thereafter (at 5th,
9th, 13th, and 17th level). As a goblin, you can select
from the following ancestry feats.
16415397

16415397

FEAT 1

GOBLIN

Fire fascinates you. Your spells and alchemical items that deal
fire damage gain a status bonus to damage equal to half the
spell’s level or one-quarter the item’s level (minimum 1). You also
gain a +1 status bonus to any persistent fire damage you deal.

FEAT 1

GOBLIN

You know that the greatest treasures often look like refuse, and
you scoff at those who throw away perfectly good scraps. You
gain a +1 circumstance bonus to checks to Subsist, and you can
use Society or Survival when you Subsist in a settlement.
When you Subsist in a city, you also gather valuable junk
that silly longshanks threw away. You can Earn Income using
Society or Survival in the same time as you Subsist, without
spending any additional days of downtime. You also gain a +1
circumstance bonus to this check.
Special If you have the irongut goblin heritage, increase the
bonuses to +2.

GOBLIN LORE

FEAT 1

Trigger An ally ends a move action adjacent to you.
You take advantage of your ally’s movement to adjust your
position. You Step.

GOBLIN SONG[ [one-action]

FEAT 1

GOBLIN

You sing annoying goblin songs, distracting your foes with
silly and repetitive lyrics. Attempt a Performance check
against the Will DC of a single enemy within 30 feet. This has
all the usual traits and restrictions of a Performance check.
You can affect up to two targets within range if you have
expert proficiency in Performance, four if you have master
proficiency, and eight if you have legendary proficiency.
Critical Success The target takes a –1 status penalty to
Perception checks and Will saves for 1 minute.
Success The target takes a –1 status penalty to Perception
checks and Will saves for 1 round.
Critical Failure The target is temporarily immune to attempts
to use Goblin Song for 1 hour.

GOBLIN WEAPON FAMILIARITY

FEAT 1

4121802

GOBLIN

1ST LEVEL

CITY SCAVENGER

GOBLIN SCUTTLE [reaction]
GOBLIN

You are acclimated to living in frigid lands and have
skin ranging from sky blue to navy in color, as well as
blue fur. You gain cold resistance equal to half your level
(minimum 1). You treat environmental cold effects as if
they were one step less extreme (incredible cold becomes
extreme, extreme cold becomes severe, and so on).

BURN IT!

If you would automatically become trained in one of those
skills (from your background or class, for example), you instead
become trained in a skill of your choice. You also become trained
in Goblin Lore.

FEAT 1

GOBLIN

You’ve picked up skills and tales from your goblin community.
You gain the trained proficiency rank in Nature and Stealth.

Others might look upon them with disdain, but you know that
the weapons of your people are as effective as they are sharp.
You are trained with the dogslicer and horsechopper.
In addition, you gain access to all uncommon goblin weapons.
For the purpose of determining your proficiency, martial goblin
weapons are simple weapons and advanced goblin weapons are
martial weapons.

JUNK TINKER

FEAT 1

GOBLIN

You can make useful tools out of even twisted or rusted scraps.
When using the Crafting skill to Craft, you can make level 0
items, including weapons but not armor, out of junk. This reduces
the Price to one-quarter the usual amount but always results in a
shoddy item. Shoddy items normally give a penalty, but you don’t
take this penalty when using shoddy items you made.
You can also incorporate junk to save money while you Craft
any item. This grants you a discount on the item as if you had
spent 1 additional day working to reduce the cost, but the item
is obviously made of junk. At the GM’s discretion, this might
affect the item’s resale value depending on the buyer’s tastes.

ROUGH RIDER

FEAT 1

GOBLIN

You are especially good at riding traditional goblin mounts. You
gain the Ride feat, even if you don’t meet the prerequisites.
You gain a +1 circumstance bonus to Nature checks to use

48

16415397

4121803

4121803

16415398

16415398


Ancestries & backgrounds

Command an Animal on a goblin dog or wolf mount. You can always select a wolf as your
animal companion, even if you would usually select an animal companion with the mount
special ability, such as for a champion’s steed ally.

VERY SNEAKY
```
