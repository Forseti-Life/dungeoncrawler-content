# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-t  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 8284–8583 of 103266 (8% through this book)  
**Features generated this cycle so far:** 0 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 8284–8583
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-21

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
   - Set `books[0].last_line` → 8583
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260412-dungeoncrawler-release-t`
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

## Source material (lines 8284–8583)

```

16415404

4121810

4121810


Core Rulebook

A half-orc has a shorter lifespan than other humans,
living to be roughly 70 years old.

Playing a Half-Orc
You can create a half-orc character by selecting the
half‑orc heritage at 1st level. This gives you access to
orc and half‑orc ancestry feats in addition to human
ancestry feats.

You Might...
• Ignore, embrace, or actively counter the common
stereotypes about half-orcs.

Others Probably...
• Assume you enjoy and excel at fighting but aren’t
inclined toward magical or intellectual pursuits.
• Pity you for the tragic circumstances they assume
were involved in your birth.
• Get out of your way and back down rather than
face your anger.

Human Heritages

Unlike other ancestries, humans don’t have significant
physiological differences defined by their lineage. Instead,
their heritages either reveal their potential as a people or
reflect lineages from multiple ancestries. Choose one of the
following human heritages at 1st level.

Half-Elf
Either one of your parents was an elf, or one or both were
half-elves. You have pointed ears and other telltale signs
of elf heritage. You gain the elf trait, the half-elf trait, and
low‑light vision. In addition, you can select elf, half-elf,
and human feats whenever you gain an ancestry feat.

16415405

16415405

• Make the most of your size and strength, either
physically or socially.
• Keep your distance from people of most other
ancestries, in case they unfairly reject you due to
your orc ancestors.

4121810

Half-Orc
One of your parents was an orc, or one or both were
half-orcs. You have a green tinge to your skin and other
indicators of orc heritage. You gain the orc trait, the
half-orc trait, and low‑light vision. In addition, you can
select orc, half‑orc, and human feats whenever you gain
an ancestry feat.

Skilled Heritage
Your ingenuity allows you to train in a wide variety of
skills. You become trained in one skill of your choice.
At 5th level, you become an expert in the chosen skill.

Versatile Heritage
Humanity’s versatility and ambition have fueled its
ascendance to be the most common ancestry in most
nations throughout the world. Select a general feat of
your choice for which you meet the prerequisites (as with
your ancestry feat, you can select this general feat at any
point during character creation).

Human Ancestry Feats

At 1st level, you gain one ancestry feat, and you gain an
additional ancestry feat every 4 levels thereafter (at 5th,
9th, 13th, and 17th level). As a human, you choose from
among the following ancestry feats.

56

16415405

4121811

4121811

16415406

16415406


Ancestries & backgrounds

UNCONVENTIONAL WEAPONRY

1ST LEVEL
ADAPTED CANTRIP

FEAT 1

HUMAN

FEAT 1

HUMAN

The short human life span lends perspective and has taught
you from a young age to set aside differences and work with
others to achieve greatness. You gain a +4 circumstance
bonus on checks to Aid.

GENERAL TRAINING

FEAT 1

HUMAN

Your adaptability manifests in your mastery of a range of
useful abilities. You gain a 1st-level general feat. You must
meet the feat’s prerequisites, but if you select this feat during
character creation, you can select the feat later in the process
in order to determine which prerequisites you meet.
Special You can select this feat multiple times, choosing a
different feat each time.

HAUGHTY OBSTINACY

Introduction

HUMAN

Prerequisites spellcasting class feature
Through study of multiple magical traditions, you’ve altered
a spell to suit your spellcasting style. Choose one cantrip
from a magical tradition other than your own. If you have a
spell repertoire or a spellbook, replace one of the cantrips you
know or have in your spellbook with the chosen spell. If you
prepare spells without a spellbook (if you’re a cleric or druid,
for example), one of your cantrips must always be the chosen
spell, and you prepare the rest normally. You can cast this
cantrip as a spell of your class’s tradition.
If you swap or retrain this cantrip later, you can choose its
replacement from the same alternate tradition or a different one.

COOPERATIVE NATURE

FEAT 1

2

FEAT 1

You’ve familiarized yourself with a particular weapon, potentially
from another ancestry or culture. Choose an uncommon simple
or martial weapon with a trait corresponding to an ancestry
(such as dwarf, goblin, or orc) or that is common in another
culture. You gain access to that weapon, and for the purpose of
determining your proficiency, that weapon is a simple weapon.
If you are trained in all martial weapons, you can choose
an uncommon advanced weapon with such a trait. You gain
access to that weapon, and for the purpose of determining your
proficiency, that weapon is a martial weapon.

Ancestries &
Backgrounds
Classes
Skills
Feats
Equipment

5TH LEVEL
ADAPTIVE ADEPT

Spells

FEAT 5

HUMAN

Prerequisites Adapted Cantrip, can cast 3rd-level spells
You’ve continued adapting your magic to blend your class’s
tradition with your adapted tradition. Choose a cantrip or
1st‑level spell from the same magical tradition as your cantrip
from Adapted Cantrip. You gain that spell, adding it to your spell
repertoire, spellbook, or prepared spells just like the cantrip from
Adapted Cantrip. You can cast this spell as a spell of your class’s
magical tradition. If you choose a 1st-level spell, you don’t gain
access to the heightened versions of that spell, meaning you can’t
prepare them if you prepare spells and you can’t learn them or
select the spell as a signature spell if you have a spell repertoire.

CLEVER IMPROVISER

FEAT 5

The Age of
Lost OMENS
Playing the
Game
Game
mastering
Crafting
& Treasure

4121811

Appendix

HUMAN

You’ve learned how to handle situations when you’re out of
your depth. You gain the Untrained Improvisation general
feat. In addition, you can attempt skill actions that normally
require you to be trained, even if you are untrained.

HUMAN

Your powerful ego makes it harder for others to order you
around. If you roll a success on a saving throw against a
mental effect that attempts to directly control your actions,
you critically succeed instead. If a creature rolls a failure on a
check to Coerce you using Intimidation, it gets a critical failure
instead (so it can’t try to Coerce you again for 1 week).

NATURAL AMBITION

FEAT 1

HUMAN

You were raised to be ambitious and always reach for the stars,
leading you to progress quickly in your chosen field. You gain a
1st-level class feat for your class. You must meet the prerequisites,
but you can select the feat later in the character creation process
in order to determine which prerequisites you meet.

NATURAL SKILL

FEAT 1

HUMAN

Your ingenuity allows you to learn a wide variety of skills. You
gain the trained proficiency rank in two skills of your choice.

9TH LEVEL
COOPERATIVE SOUL

FEAT 9

HUMAN

Prerequisites Cooperative Nature
You have developed a soul-deep bond with your comrades and
maintain an even greater degree of cooperation with them. If you
are at least an expert in the skill you are Aiding, you get a success
on any outcome rolled to Aid other than a critical success.

INCREDIBLE IMPROVISATION [free-action]

FEAT 9

HUMAN

Prerequisites Clever Improviser
Frequency once per day
Trigger You attempt a check using a skill you’re untrained in.
A stroke of brilliance gives you a major advantage with a skill
despite your inexperience. Gain a +4 circumstance bonus to
the triggering skill check.

57

16415406

4121812

4121812


Core Rulebook

MULTITALENTED

FEAT 9

HUMAN

You’ve learned to split your focus between multiple classes
with ease. You gain a 2nd-level multiclass dedication feat
(for more about multiclass archetypes, see page 219), even if
you normally couldn’t take another dedication feat until you
```
