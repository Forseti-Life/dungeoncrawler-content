# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260407-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 6184–6483 of 103266 (6% through this book)  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 6184–6483
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-07

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
   - Set `books[0].last_line` → 6483
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260407-dungeoncrawler-release-b`
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

## Source material (lines 6184–6483)

```
Core Rulebook

Seer Elf
You have an inborn ability to detect and understand
magical phenomena. You can cast the detect magic cantrip
as an arcane innate spell at will. A cantrip is heightened to
a spell level equal to half your level rounded up.
In addition, you gain a +1 circumstance bonus to
checks to Identify Magic and to Decipher Writing of
a magical nature. These skill actions typically use the
Arcana, Nature, Occultism, or Religion skill.

Whisper Elf
Your ears are finely tuned, able to detect even the slightest
whispers of sound. As long as you can hear normally, you
can use the Seek action to sense undetected creatures in
a 60-foot cone instead of a 30-foot cone. You also gain
a +2 circumstance bonus to locate undetected creatures
that you could hear within 30 feet with a Seek action.

Woodland Elf
You’re adapted to life in the forest or the deep jungle,
and you know how to climb trees and use foliage to your
advantage. When Climbing trees, vines, and other foliage,
you move at half your Speed on a success and at full
Speed on a critical success (and you move at full Speed
on a success if you have Quick Climb). This doesn’t affect
you if you’re using a climb Speed.
You can always use the Take Cover action when you
are within forest terrain to gain cover, even if you’re not
next to an obstacle you can Take Cover behind.

16415389

16415389

Ancestry Feats

At 1st level, you gain one ancestry feat, and you gain
an additional ancestry feat every 4 levels thereafter (at
5th, 9th, 13th, and 17th level). As an elf, you select from
among the following ancestry feats.

automatically become trained in one of those skills (from
your background or class, for example), you instead become
trained in a skill of your choice. You also become trained in
Elven Lore.

ELVEN WEAPON FAMILIARITY

FEAT 1

ELF

You favor bows and other elegant weapons. You are trained
with longbows, composite longbows, longswords, rapiers,
shortbows, and composite shortbows.
In addition, you gain access to all uncommon elf weapons.
For the purpose of determining your proficiency, martial elf
weapons are simple weapons and advanced elf weapons are
martial weapons.

FORLORN

FEAT 1

ELF

Watching your friends age and die fills you with moroseness
that protects you against harmful emotions. You gain a +1
circumstance bonus to saving throws against emotion effects.
If you roll a success on a saving throw against an emotion
effect, you get a critical success instead.

NIMBLE ELF

FEAT 1

ELF

Your muscles are tightly honed. Your Speed increases by 5 feet.

OTHERWORLDLY MAGIC

FEAT 1

4121794

ELF

Your elven magic manifests as a simple arcane spell, even if
you aren’t formally trained in magic. Choose one cantrip from
the arcane spell list (page 307). You can cast this cantrip as an
arcane innate spell at will. A cantrip is heightened to a spell
level equal to half your level rounded up.

UNWAVERING MIEN

FEAT 1

ELF

1ST LEVEL
ANCESTRAL LONGEVITY

FEAT 1

ELF

Prerequisites at least 100 years old
You have accumulated a vast array of lived knowledge over
the years. During your daily preparations, you can reflect
upon your life experiences to gain the trained proficiency
rank in one skill of your choice. This proficiency lasts until
you prepare again. Since this proficiency is temporary, you
can’t use it as a prerequisite for a skill increase or a permanent
character option like a feat.

ELVEN LORE

FEAT 1

ELF

You’ve studied in traditional elven arts, learning about
arcane magic and the world around you. You gain the
trained proficiency rank in Arcana and Nature. If you would

Your mystic control and meditations allow you to resist
external influences upon your consciousness. Whenever you
are affected by a mental effect that lasts at least 2 rounds, you
can reduce the duration by 1 round.
You still require natural sleep, but you treat your saving
throws against effects that would cause you to fall asleep as
one degree of success better. This protects only against sleep
effects, not against other forms of falling unconscious.

5TH LEVEL
AGELESS PATIENCE

FEAT 5

ELF

You work at a pace born from longevity that enhances your
thoroughness. You can voluntarily spend twice as much
time as normal on a Perception check or skill check to gain
a +2 circumstance bonus to that check. You also don’t treat
a natural 1 as worse than usual on these checks; you get a

40

16415389

4121795

4121795

16415390

16415390


Ancestries & backgrounds

critical failure only if your result is 10 lower than the DC. For example, you could get these
benefits if you spent 2 actions to Seek, which normally takes 1 action. You can get these
benefits during exploration by taking twice as long exploring as normal, or in downtime by
spending twice as much downtime.
The GM might determine a situation doesn’t grant you a benefit if a delay would
be directly counterproductive to your success, such as a tense negotiation with an
impatient creature.

ELVEN WEAPON ELEGANCE

FEAT 5

ELF

Prerequisites Elven Weapon Familiarity
You are attuned to the weapons of your elven ancestors and are particularly deadly when
using them. Whenever you critically hit using an elf weapon or one of the weapons listed in
Elven Weapon Familiarity, you apply the weapon’s critical specialization effect.

ELVEN ADVENTURERS
Many elves adventure to find
beauty and discover new
things. Typical backgrounds
for an elf include emissary,
hunter, noble, scholar, or scout.
Elves often become rangers or
rogues, taking advantage of
their dexterity, or alchemists
or wizards, exploring their
intellectual curiosity.

2
Introduction
Ancestries &
Backgrounds
Classes
Skills
Feats
Equipment
Spells

9TH LEVEL
ELF STEP [one-action] 

FEAT 9

ELF

You move in a graceful dance, and even your steps are broad. You Step 5 feet twice.

EXPERT LONGEVITY

FEAT 9

ELF

Prerequisites Ancestral Longevity
You’ve continued to refine the knowledge and skills you’ve gained through
your life. When you choose a skill in which to become trained with
Ancestral Longevity, you can also choose a skill in which you are already
trained and become an expert in that skill. This lasts until your Ancestral
Longevity expires.
When the effects of Ancestral Longevity and Expert Longevity expire, you can
retrain one of your skill increases. The skill increase you gain from this retraining must
either make you trained in the skill you chose with Ancestral Longevity or make you an
expert in the skill you chose with Expert Longevity.

The Age of
Lost OMENS
Playing the
Game
Game
mastering
Crafting
& Treasure

4121795

Appendix

13TH LEVEL
UNIVERSAL LONGEVITY [one-action]

FEAT 13

ELF

Prerequisites Expert Longevity
Frequency once per day
You’ve perfected your ability to keep up with all the skills you’ve learned over your long
life, so you’re almost never truly untrained at a skill. You reflect on your life experiences,
changing the skills you selected with Ancestral Longevity and Expert Longevity.

ELVEN WEAPON EXPERTISE

FEAT 13

ELF

Prerequisites Elven Weapon Familiarity
Your elven affinity blends with your class training, granting you
great skill with elven weapons. Whenever you gain a class
feature that grants you expert or greater proficiency
in certain weapons, you also gain that proficiency in
longbows, composite longbows, longswords, rapiers,
shortbows, composite shortbows, and all elf weapons
in which you are trained.

41

16415390

4121796

4121796


Core Rulebook

Gnome

Long ago, early gnome ancestors emigrated from the First World, realm of the fey. While
it’s unclear why the first gnomes wandered to Golarion, this lineage manifests in modern
gnomes as bizarre reasoning, eccentricity, obsessive tendencies, and what some see as
naivete. These qualities are further reflected in their physical characteristics, such as
spindly limbs, brightly colored hair, and childlike and extremely expressive facial features
that further reflect their otherworldly origins.
Always hungry for new experiences, gnomes constantly
wander both mentally and physically, attempting to stave
off a terrible ailment that threatens all of their people.
This affliction—the Bleaching—strikes gnomes who
fail to dream, innovate, and take in new experiences,
in the gnomes’ absence of crucial magical essence
from the First World. Gnomes latch onto a source of
localized magic where they live, typically primal magic,
```
