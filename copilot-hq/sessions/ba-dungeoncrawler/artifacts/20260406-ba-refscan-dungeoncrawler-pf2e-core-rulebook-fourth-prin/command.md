# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260406-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 5884–6183 of 103266 (5% through this book)  
**Features generated this cycle so far:** 21 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 5884–6183
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-06

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
   - Set `books[0].last_line` → 6183
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260406-dungeoncrawler-release-b`
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

## Source material (lines 5884–6183)

```
don’t take the –2 circumstance penalty.

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

4121791

Appendix

13TH LEVEL
DWARVEN WEAPON EXPERTISE

FEAT 13

DWARF

Prerequisites Dwarven Weapon Familiarity
Your dwarven affinity blends with your training,
granting you great skill with dwarven weapons.
Whenever you gain a class feature that grants you
expert or greater proficiency in certain weapons, you also
gain that proficiency for battle axes, picks, warhammers,
and all dwarven weapons in which you are trained.

37

16415386

4121792

4121792


Core Rulebook

Elf

As an ancient people, elves have seen great change and have the perspective that can
come only from watching the arc of history. After leaving the world in ancient times, they
returned to a changed land, and they still struggle to reclaim their ancestral homes, most
notably from terrible demons that have invaded parts of their lands. To some, the elves
are objects of awe—graceful and beautiful, with immense talent and knowledge. Among
themselves, however, the elves place far more importance on personal freedom than on
living up to these ideals.
Elves combine otherworldly grace, sharp intellect,
and mysterious charm in a way that is practically
magnetic to members of other ancestries. They
are often voraciously intellectual, though their
studies delve into a level of detail
that most shorter-lived peoples
find excessive or inefficient.
Valuing kindness and beauty,
elves ever strive to improve
their manners, appearance,
and culture.
Elves are often rather
private people, steeped in
the secrets of their groves and
kinship groups. They’re slow to
build friendships outside their
kinsfolk, but for a specific reason:
they subtly and deeply attune
to their environment and their
companions. There’s a physical
element to this attunement, but
it isn’t only superficial. Elves
who spend their lives among
shorter‑lived peoples often develop
a skewed perception of their own
mortality and tend to become
morose after watching generation
after generation of companions
age and die. These elves are called
the Forlorn.
If you want a character who is
magical, mystical, and mysterious,
you should play an elf.

16415387

16415387

You Might...
• Carefully
curate
your
relationships with people
with shorter lifespans, either
keeping a careful emotional
distance or resigning yourself
to outliving them.
• Adopt specialized or obscure interests
simply for the sake of mastering them.
• Have features such as eye color, skin

tone, hair, or mannerisms that reflect the
environment in which you live.

Others Probably...
• Focus on your appearance, either
admiring your grace or treating you as
if you’re physically fragile.
• Assume you practice archery,
cast spells, fight demons, and have
perfected one or more fine arts.

4121792

• Worry that you privately look
down on them, or feel like you’re
condescending and aloof.

Physical Description
While generally taller than humans, elves
possess a fragile grace, accentuated by
long features and sharply pointed ears.
Their eyes are wide and almond-shaped,
featuring large and vibrant-colored
pupils that make up the entire visible
portion of the eye. These pupils give
them an alien look and allow them to see
sharply even in very little light.
Elves gradually adapt to their
environment and their companions,
and they often take on physical traits
reflecting their surroundings. An elf who
has dwelled in primeval forests for centuries,
for example, might exhibit verdant hair and
gnarled fingers, while one who’s lived in a
desert might have golden pupils and skin.
Elven fashion, like the elves themselves, tends

38

16415387

4121793

4121793

16415388

16415388


Ancestries & backgrounds

to reflect their surroundings. Elves living in the forests and other wilderness locales
wear clothing that plays off the terrain and flora of their homes, while those who live
in cities tend to wear the latest fashions.
Elves reach physical adulthood around the age of 20, though they aren’t
considered to be fully emotionally mature by other elves until closer to the passing
of their first century, once they’ve experienced more, held several occupations, and
outlived a generation of shorter-lived people. A typical elf can live to around 600
years old.

Hit Points

Society

Ability Boosts

Elven culture is deep, rich, and on the decline. Their society peaked millennia ago,
long before they fled the world to escape a great calamity. They’ve since returned,
but rebuilding is no easy task. Their inborn patience and intellectual curiosity make
elves excellent sages, philosophers, and wizards, and their societies are built upon
their inherent sense of wonder and knowledge. Elven architecture displays their deep
appreciation of beauty, and elven cities are wondrous works of art.
Elves hold deeply seated ideals of individualism, allowing each elf to explore
multiple occupations before alighting on a particular pursuit or passion that suits
her best. Elves bear notorious grudges against rivals, which the elves call ilduliel, but
these antagonistic relationships can sometimes blossom into friendships over time.

Dexterity
Intelligence
Free

6

Introduction

Medium

Ancestries &
Backgrounds

Speed

Classes

Size

30 feet

Skills

Ability Flaw
Constitution

Languages

Elves are often emotional and capricious, yet they hold high ideals close to their
hearts. As such, many are chaotic good. They prefer deities who share their love of
all things mystic and artistic. Desna and Shelyn are particular favorites, the former
for her sense of wonder and the latter for her appreciation of artistry. Calistria is the
most notorious of elven deities, as she represents many of the elven ideals taken to
the extreme.

Common
Elven
Additional languages equal to
your Intelligence modifier (if it’s
positive). Choose from Celestial,
Draconic, Gnoll, Gnomish,
Goblin, Orcish, Sylvan, and
any other languages to which
you have access (such as
the languages prevalent in
your region).

Names

Traits

An elf keeps their personal name secret among their family, while giving a nickname
when meeting other people. This nickname can change over time, due to events in
the elf’s life or even on a whim. A single elf might be known by many names by
associates of different ages and regions. Elven names consist of multiple syllables and
are meant to flow lyrically—at least in the Elven tongue. They so commonly end in
“-el” or “-ara” that other cultures sometimes avoid names ending in these syllables
to avoid sounding too elven.

Elf
Humanoid

Alignment and Religion

2

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

4121793

Appendix

Low-Light Vision
You can see in dim light as
though it were bright light,
so you ignore the concealed
condition due to dim light.

Sample Names
Aerel, Amrunelara, Caladrel, Dardlara, Faunra, Heldalel, Jathal, Lanliss, Oparal,
Seldlon, Soumral, Talathel, Tessara, Variel, Yalandlara, Zordlon

Elf Heritages

Elves live long lives and adapt to their environment after dwelling there for a long
time. Choose one of the following elven heritages at 1st level.

Arctic Elf
You dwell deep in the frozen north and have gained incredible resilience against cold
environments, granting you cold resistance equal to half your level (minimum 1). You
treat environmental cold effects as if they were one step less extreme (incredible cold
becomes extreme, extreme cold becomes severe, and so on).

Cavern Elf
You were born or spent many years in underground tunnels or caverns where light
is scarce. You gain darkvision.

39

16415388

4121794

4121794
```
