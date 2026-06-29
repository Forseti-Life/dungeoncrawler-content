# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-m  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 7984–8283 of 103266 (8% through this book)  
**Features generated this cycle so far:** 58 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 7984–8283
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-14

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
   - Set `books[0].last_line` → 8283
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260412-dungeoncrawler-release-m`
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

## Source material (lines 7984–8283)

```
& Treasure

4121807

Appendix

You are easily able to ward off attempts to play on your fears and
emotions. When you roll a success on a saving throw against an
emotion effect, you get a critical success instead. If your heritage
is gutsy halfling, when you roll a critical failure on a saving throw
against an emotion effect, you get a failure instead.

13TH LEVEL
CEASELESS SHADOWS

FEAT 13

HALFLING

Prerequisites Distracting Shadows
You excel at going unnoticed, especially among a crowd. You no longer
need to have cover or be concealed to Hide or Sneak. If you would have
lesser cover from creatures, you gain cover and can Take Cover, and if
you would have cover from creatures, you gain greater cover.

HALFLING WEAPON EXPERTISE

FEAT 13

HALFLING

Prerequisites Halfling Weapon Familiarity
Your halfling affinity blends with your class training, granting you great
skill with halfling weapons. Whenever you gain a class feature that grants
you expert or greater proficiency in a given weapon or weapons, you also
gain that proficiency in the sling, halfling sling staff, shortsword, and all
halfling weapons in which you are trained.

53

16415402

4121808

4121808


Core Rulebook

Human

As unpredictable and varied as any of Golarion’s peoples, humans have exceptional drive
and the capacity to endure and expand. Though many civilizations thrived before humanity
rose to prominence, humans have built some of the greatest and the most terrible societies
throughout the course of history, and today they are the most populous people in the
realms around the Inner Sea.
Humans’ ambition, versatility, and exceptional potential
have led to their status as the world’s predominant ancestry.
Their empires and nations are vast, sprawling things,
and their citizens carve names for themselves with the
strength of their sword arms and the power of their spells.
Humanity is diverse and tumultuous, running the gamut
from nomadic to imperial, sinister to saintly. Many of
them venture forth to explore, to map the expanse of
the multiverse, to search for long-lost treasure, or to
lead mighty armies to conquer their neighbors—for
no better reason than because they can.
If you want a character who can be
just about anything, you should play
a human.

You Might...
• Strive to achieve
greatness, either in
your own right or on
behalf of a cause.
• Seek to understand your
purpose in the world.
• Cherish your relationships
with family and friends.

16415403

16415403

Others Probably...
• Respect your flexibility, your
adaptability, and—in most cases—
your open‑mindedness.
• Distrust your intentions, fearing
you seek only power or wealth.
• Aren’t sure what to expect
from you and are hesitant to
assume your intentions.

Physical Description
Humans’ physical characteristics
are as varied as the world’s
climes. Humans have a wide variety of skin and hair
colors, body types, and facial features. Generally
speaking, their skin has a darker hue the closer to
the equator they or their ancestors lived.
Humans reach physical adulthood around
the age of 15, though mental maturity occurs a
few years later. A typical human can live to be around 90
years old. Humans often intermarry with people of other

ancestries, giving rise to children who bear the traits of
both parents. The most notable half-humans are half-elves
and half-orcs.

Society
Human variety also manifests in terms of their
governments, attitudes, and social norms.
Though the oldest of human cultures can
trace their shared histories thousands of years
into the past,
when compared
to the societies
of the elves or
dwarves, human
civilizations seem in a
state of constant flux as
empires fragment and new
kingdoms subsume the old.

4121808

Alignment and Religion
Humanity is perhaps the most
heterogeneous of all the ancestries,
with a capacity for great evil and boundless
good. Some humans assemble into vast
raging hordes, while others build
sprawling cities. Considered as a whole,
most humans are neutral, yet they
tend to congregate into nations or
communities of a shared alignment,
or at least a shared tendency
toward an alignment. Humans
also worship a wide range
of gods and practice many
different religions, tending to
seek favor from any divine
being they encounter.

Names
Unlike many ancestral cultures, which
generally cleave to specific traditions and
shared histories, humanity’s diversity has resulted
in a near-infinite set of names. The humans of
northern tribes have different names than those
dwelling in southern nation‑states. Humans
throughout much of the world speak Common
(though some continents on Golarion have

54

16415403

4121809

4121809


Ancestries & backgrounds

their own regional common languages), yet their names are as varied as their beliefs
and appearances.

Hit Points

Ethnicities

Size

A variety of human ethnic groups—many of which have origins on distant lands—
populates the continents bordering Golarion’s Inner Sea. Human characters can be
any of these ethnicities, regardless of what lands they call home. Information about
Golarion’s human ethnicities appears on page 430 in Chapter 8.
Characters of human ethnicities in the Inner Sea region speak Common (also known
as Taldane), and some ethnicities grant access to an uncommon language.

Medium

8

Speed
25 feet

A half-elf is born to an elf and a human, or to two half-elves. The life of a half-elf
can be difficult, often marked by a struggle to fit in. Half-elves don’t have their own
homeland on Golarion, nor are populations of half-elves particularly tied to one
another, since they often have very disparate human and elven traditions. Instead,
most half-elves attempt to find acceptance in either human or elven settlements.
Half-elves often appear primarily human, with subtly pointed ears and a taller
stature than most full-blooded humans. Half-elves lack the almost alien eyes of their
elf parents, though they do have a natural presence—and often a striking beauty—
that leads many to become artists or entertainers. Despite this innate appeal, many
half‑elves have difficulty forming lasting bonds with either humans or elves due to the
distance they feel from both peoples as a whole.
Half-elves live longer than other humans, often reaching an age around 150 years.
This causes some of them to fear friendship and romance with humans, knowing
that they’ll likely outlive their companions.

Languages
Common
Additional languages equal to
1 + your Intelligence modifier
(if it’s positive). Choose from
the list of common languages
and any other languages to
which you have access (such
as the languages prevalent in
your region).

Traits
Human
Humanoid

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

16415404

16415404

Introduction

Ability Boosts
Two free ability boosts

Half-Elves

2

Playing a Half-Elf
You can create a half-elf character by selecting the half-elf heritage at 1st level. This
gives you access to elf and half-elf ancestry feats in addition to human ancestry feats.

4121809

Appendix

You Might...
• Keep to yourself and find it difficult to form close bonds with others.
• Strongly embrace or reject one side or the other of your parentage.
• Identify strongly with and relate to other people with mixed ancestries.

Others Probably...
• Find you more attractive than humans and more approachable than elves.
• Dismiss your human ethnicity and culture in light of your elven heritage.
• Downplay the challenges of being caught between two cultures.

Half-Orcs

A half-orc is the offspring of a human and an orc, or of two half-orcs. Because some
intolerant people see orcs as more akin to monsters than people, they sometimes
hate and fear half-orcs simply due to their lineage. This commonly pushes half-orcs
to the margins of society, where some find work in manual labor or as mercenaries,
and others fall into crime or cruelty. Many who can’t stand the indignities heaped on
them in human society find a home among their orc kin or trek into the wilderness
to live in peace, apart from society’s judgment.
Humans often assume half-orcs are unintelligent or uncivilized, and half-orcs rarely
find acceptance among societies with many such folk. To an orc tribe, a half-orc is
considered smart enough to make a good war leader but weaker physically than other
orcs. Many half-orcs thus end up having low status among orc tribes unless they can
prove their strength.

OTHER HALVES
By default, half-elves and
half-orcs descend from
humans, but your GM might
allow you to be the offspring
of an elf, orc, or different
ancestry. In these cases, the
GM will let you select the
half-elf or half-orc heritage
as the heritage for this other
ancestry. The most likely
other parent of a half-elf
are gnomes and halflings,
and the most likely parents
of a half-orc are goblins,
halflings, and dwarves.

55
```
- Agent: ba-dungeoncrawler
