# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260409-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 6784–7083 of 103266 (6% through this book)  
**Features generated this cycle so far:** 69 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-09

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
   - Set `books[0].last_line` → 7083
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260409-dungeoncrawler-release-b`
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

## Source material (lines 6784–7083)

```

4121798

FEAT 1

GNOME

You might have a flighty nature, but when a topic captures
your attention, you dive into it headfirst. Pick a Lore skill. You
gain the trained proficiency rank in that skill. At 2nd level,
you gain expert proficiency in the chosen Lore as well as the
Lore granted by your background, if any. At 7th level you gain
master proficiency in these Lore skills, and at 15th level you
gain legendary proficiency in them.

GNOME WEAPON FAMILIARITY

FEAT 1

GNOME

1ST LEVEL
ANIMAL ACCOMPLICE

FEAT 1

GNOME

You build a rapport with an animal, which becomes magically
bonded to you. You gain a familiar using the rules on page
217. The type of animal is up to you, but most gnomes choose
animals with a burrow Speed.

BURROW ELOCUTIONIST

FEAT 1

GNOME

You recognize the chittering of ground creatures as its own
peculiar language. You can ask questions of, receive answers

You favor unusual weapons tied to your people, such as blades
with curved and peculiar shapes. You are trained with the
glaive and kukri.
In addition, you gain access to kukris and all uncommon
gnome weapons. For the purpose of determining your
proficiency, martial gnome weapons are simple weapons and
advanced gnome weapons are martial weapons.

ILLUSION SENSE

FEAT 1

GNOME

Your ancestors spent their days cloaked and cradled in
illusions, and as a result, sensing illusion magic is second
nature to you. You gain a +1 circumstance bonus to both
Perception checks and Will saves against illusions.

44

16415393

4121799

4121799


Ancestries & backgrounds

When you come within 10 feet of an illusion that can be disbelieved, the GM rolls a secret
check for you to disbelieve it, even if you didn’t spend an action to Interact with the illusion.

5TH LEVEL
ANIMAL ELOCUTIONIST

FEAT 5

GNOME

Prerequisites Burrow Elocutionist
You hear animal sounds as conversations instead of unintelligent noise, and can respond in turn.
You can speak to all animals, not just animals with a burrow Speed. You gain a +1 circumstance
bonus to Make an Impression on animals (which usually uses the Diplomacy skill).

ENERGIZED FONT [one-action]

FEAT 5

GNOME

Prerequisites focus pool, at least one innate spell from a gnome heritage or ancestry feat that
shares a tradition with at least one of your focus spells
Frequency once per day
The magic within you provides increased energy you can use to focus. You regain 1 Focus
Point, up to your usual maximum.

GNOME WEAPON INNOVATOR

FEAT 5

GNOME ADVENTURERS
Adventure is not so much a
choice as a necessity for most
gnomes. Adventuring gnomes
often claim mementos, allowing
them to remember and relive
their most exciting stories.
Gnomes often consider the
entertainer, merchant, or nomad
backgrounds. In addition, the
animal whisperer, barkeep,
gambler, and tinker backgrounds
are particularly appropriate.
Gnomes’ connection to magic
makes spellcasting classes
particularly thematic for you,
especially classes that match the
tradition of your primal innate
spells, such as druid or primal
sorcerer, though wellspring
gnomes might choose others.

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

GNOME

Prerequisites Gnome Weapon Familiarity
You produce outstanding results when wielding unusual weapons. Whenever you
critically hit using a glaive, kukri, or gnome weapon, you apply the weapon’s
critical specialization effect.
16415394

16415394

2

9TH LEVEL

Game
mastering
Crafting
& Treasure

4121799

Appendix

FIRST WORLD ADEPT

FEAT 9

GNOME

Prerequisites at least one primal innate spell
Over time your fey magic has grown stronger. You gain faerie fire and invisibility as
2nd-level primal innate spells. You can cast each of these
primal innate spells once per day.

VIVACIOUS CONDUIT

FEAT 9

GNOME

Your connection to the First World has grown, and
its positive energy flows into you rapidly. If you rest for
10 minutes, you regain Hit Points equal to your Constitution
modifier × half your level. This is cumulative with any healing you
receive from Treat Wounds.

13TH LEVEL
GNOME WEAPON EXPERTISE

FEAT 13

GNOME

Prerequisites Gnome Weapon Familiarity
Your gnome affinity blends with your class training, granting you great skill with gnome
weapons. Whenever you gain a class feature that grants you expert or greater proficiency
in a given weapon or weapons, you also gain that proficiency in the glaive, kukri, and all
gnome weapons in which you are trained.

45

16415394

4121800

4121800


Core Rulebook

Goblin

The convoluted histories other people cling to don’t interest goblins. These small folk live
in the moment, and they prefer tall tales over factual records. The wars of a few decades
ago might as well be from the ancient past. Misunderstood by other people, goblins are
happy how they are. Goblin virtues are about being present, creative, and honest. They
strive to lead fulfilled lives, rather than worrying about how their journeys will end. To
tell stories, not nitpick the facts. To be small, but dream big.
Goblins have a reputation as simple creatures who love
songs, fire, and eating disgusting things and who hate
reading, dogs, and horses—and there are a great many
for whom this description fits perfectly. However, great
changes have come to goblinkind, and more and more
goblins resist conformity to these stereotypes. Even among
goblins that are more worldly, many still exemplify their
old ways in some small manner, just to a more sensible
degree. Some goblins remain deeply fascinated with fire or
fearlessly devour a meal that might turn others’ stomachs.

Others are endless tinkerers and view their companions’
trash as the components of gadgets yet to be made.
Though goblins’ culture has splintered radically, their
reputation has changed little. As such, goblins who travel
to larger cities are frequently subjected to derision, and
many work twice as hard at proving their worth.
If you want a character who is eccentric, enthusiastic,
and fun-loving, you should play a goblin.

You Might...
• Strive to prove that you have a place among other
civilized peoples, perhaps even to yourself.
• Fight tooth and nail—sometimes literally—to
protect yourself and your friends from danger.
• Lighten the heavy emotional burdens others carry
(and amuse yourself) with antics and pranks.

16415395

16415395

4121800

Others Probably...
• Work to ensure you don’t accidentally (or
intentionally) set too many things on fire.
• Assume you can’t—or won’t—read.
• Wonder how you survive given your ancestry’s
typical gastronomic choices, reckless behavior,
and love of fire.

Physical Description
Goblins are stumpy humanoids with large bodies,
scrawny limbs, and massively oversized heads with large
ears and beady red eyes. Their skin ranges from green to
gray to blue, and they often bear scars, boils, and rashes.
Goblins average 3 feet tall. Most are bald, with little
or no body hair. Their jagged teeth fall out and regrow
constantly, and their fast metabolism means they eat
constantly and nap frequently. Mutations are also more
common among goblins than other peoples, and goblins
usually view particularly salient mutations as a sign of
power or fortune.
Goblins reach adolescence by the age of 3 and adulthood
4 or 5 years later. Goblins can live 50 years or more,
but without anyone to protect them from each other or
themselves, few live past 20 years of age.

Society
Goblins tend to flock to strong leaders, forming small
tribes. These tribes rarely number more than a hundred,

46

16415395

4121801

4121801

16415396

16415396


Ancestries & backgrounds

though the larger a tribe is, the more diligent the leader must be to keep order—a
notoriously difficult task. As new threats rise across the Inner Sea region, many tribal
elders have put aside their reckless ways in the hope of forging alliances that offer
their people a greater chance at survival. Play and creativity matter more to goblins
than productivity or study, and their encampments erupt with songs and laughter.
Goblins bond closely with their allies, fiercely protecting those companions who
```
