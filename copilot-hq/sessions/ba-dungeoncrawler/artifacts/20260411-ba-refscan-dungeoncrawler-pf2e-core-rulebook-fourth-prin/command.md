- Status: done
- Completed: 2026-04-11T03:13:05Z

# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260411-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 7384–7683 of 103266 (7% through this book)  
**Features generated this cycle so far:** 40 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 7384–7683
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-11

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
   - Set `books[0].last_line` → 7683
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260411-dungeoncrawler-release-b`
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

## Source material (lines 7384–7683)

```
FEAT 1

GOBLIN

Taller folk rarely pay attention to the shadows at their feet, and you take full advantage of
this. You can move 5 feet farther when you take the Sneak action, up to your Speed.
In addition, as long as you continue to use Sneak actions and succeed at your Stealth check,
you don’t become observed if you don’t have cover or greater cover and aren’t concealed at
the end of the Sneak action, as long as you have cover or greater cover or are concealed at
the end of your turn.

5TH LEVEL
GOBLIN WEAPON FRENZY

FEAT 5

GOBLIN

Prerequisites Goblin Weapon Familiarity
You know how to wield your people’s vicious weapons. Whenever you score a critical hit
using a goblin weapon, you apply the weapon’s critical
specialization effect.

GOBLIN ADVENTURERS
To some degree, almost every
goblin is an adventurer, surviving
life on the edge using skill and
wits. Goblins explore and hunt
for treasures by nature, though
some become true adventurers
in their own rights, often after
being separated from their group
or tribe.
Goblins often have the
acrobat, criminal, entertainer,
gladiator, hunter, and street
urchin backgrounds. Consider
playing an alchemist, since many
goblins love fire, or a bard, since
many goblins love songs. As
scrappy survivors, goblins are
often rogues who dart about the
shadows, though their inherently
charismatic nature also draws
them to the pursuit of magical
classes such as sorcerer.

2
Introduction
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

9TH LEVEL
CAVE CLIMBER

FEAT 9

Crafting
& Treasure

GOBLIN

After years of crawling and climbing through caverns,
you can climb easily anywhere you go. You gain a climb
Speed of 10 feet.

SKITTERING SCUTTLE

4121803

Appendix

FEAT 9

GOBLIN

Prerequisites Goblin Scuttle
You can scuttle farther and faster when maneuvering alongside
allies. When you use Goblin Scuttle, you can Stride up to half your Speed
instead of Stepping.

13TH LEVEL
GOBLIN WEAPON EXPERTISE

FEAT 13

GOBLIN

Prerequisites Goblin Weapon Familiarity
Your goblin affinity blends with your class training, granting you great
skill with goblin weapons. Whenever you gain a class feature that grants
you expert or greater proficiency in a given weapon or weapons, you
also gain that proficiency in the dogslicer, horsechopper, and all goblin
weapons in which you are trained.

VERY, VERY SNEAKY

FEAT 13

GOBLIN

Prerequisites Very Sneaky
You can move up to your Speed when you use the Sneak action, and you no
longer need to have cover or greater cover or be concealed to Hide or Sneak.

49

16415398

4121804

4121804


Core Rulebook

Halfling

Claiming no place as their own, halflings control few settlements larger than villages.
Instead, they frequently live among humans within the walls of larger cities, carving out
small communities alongside taller folk. Many halflings lead perfectly fulfilling lives in the
shadows of their larger neighbors, while others prefer a nomadic existence, traveling the
world and taking advantage of opportunities and adventures as they come.
Optimistic and cheerful, blessed with uncanny luck, and
driven by powerful wanderlust, halflings make up for
their short stature with an abundance of bravado and
curiosity. At once excitable and easygoing, they are the
best kind of opportunists, and their passions favor joy
over violence. Even in the jaws of danger, halflings rarely
lose their sense of humor.

You Might...
• Get along well with a wide variety of people and
enjoy meeting new friends.
• Find it difficult to resist indulging your curiosity,
even when you know it’s going to lead to trouble.
• Hold a deep and personal hatred of the practice of
slavery and devote yourself to freeing those who
still labor against their will.

16415399

16415399

Many taller people dismiss halflings due to their size
or, worse, treat them like children. Halflings use these
prejudices and misconceptions to their advantage, gaining
access to opportunities and performing deeds of daring
mischief or heroism. A halfling’s curiosity is tempered
by wisdom and caution, leading to calculated risks and
narrow escapes.
While their wanderlust and curiosity sometimes drive
them toward adventure, halflings also carry strong ties
to house and home, often spending above their means to
achieve comfort in their homelife.
If you want to play a character who must contend with
these opposing drives toward adventure and comfort,
you should play a halfling.

4121804

Others Probably...
• Appreciate your ability to always find a silver
lining or something to laugh about, no matter
how dire the situation.
• Think you bring good luck with you.
• Underestimate your strength, endurance, and
fighting prowess.

Physical Description
Halflings are short humanoids who look vaguely like
smaller humans. They rarely grow to be more than 3
feet in height. Halfling proportions vary, with some
looking like shorter adult humans with slightly larger
heads and others having proportions closer to those of
a human child.
Most halflings prefer to walk barefoot rather than
wearing shoes, and those who do so develop roughly
calloused soles on their feet over time. Tufts of thick,
often-curly hair warm the tops of their broad, tanned
feet. Halfling skin tones tend toward rich, tawny shades
like amber or oak, and their hair color ranges from a light
golden blond to raven black.

50

16415399

4121805

4121805

16415400

16415400


Ancestries & backgrounds

Halflings reach physical adulthood around the age of 20. A typical halfling can live
to be around 150 years old.

Hit Points

Society

Size

Despite their jovial and friendly nature, halflings don’t usually tend to congregate.
They have no cultural homeland in the Inner Sea region, and they instead weave
themselves throughout the societies of the world. Halflings eke out whatever
living they can manage, many performing menial labor or holding simple service
jobs. Some halflings reject city life, instead turning to the open road and traveling
from place to place in search of fortune and fame. These nomadic halflings often
travel in small groups, sharing hardships and simple pleasures among close friends
and family.
Wherever halflings go, they seamlessly blend into the society they find themselves
in, adapting to the culture of the predominant ancestry around them and adding
their uniquely halfling twists, creating a blend of cultural diffusion that enriches
both cultures.

Small

Alignment and Religion

Languages

Halflings are loyal to their friends and their family, but they aren’t afraid to do what
needs to be done in order to survive. Halfling alignments vary, typically closely in
keeping with the alignment of the other ancestries that live around them. Halflings
favor gods that either grant luck, like Desna, or encourage guile, like Norgorber,
and many appreciate Cayden Cailean’s role as a liberator, as well as any religions
common among other ancestries around them.

Common
Halfling
Additional languages equal
to your Intelligence modifier
(if it’s positive). Choose from
Dwarven, Elven, Gnomish,
Goblin, and any other languages
to which you have access (such
as the languages prevalent in
your region).

Names
Halfling names are usually two to three syllables, with a gentle sound that avoids
hard consonants. Preferring their names to sound humble, halflings see overly long
or complex names as a sign of arrogance. This goes only for their own people,
however—halflings have names that suit them, and they understand that elves and
humans might have longer names to suit their own aesthetics. Humans in particular
have a tendency to refer to halflings by nicknames, with “Lucky” being common to
the point of absurdity.

6

Speed
25 feet

2
Introduction
Ancestries &
Backgrounds
Classes
Skills

Ability Boosts
Dexterity
Wisdom
Free

Ability Flaw
Strength

Traits
Halfling
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
```
