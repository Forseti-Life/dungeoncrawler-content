- Status: done
- Completed: 2026-04-12T01:53:30Z

# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 7684–7983 of 103266 (7% through this book)  
**Features generated this cycle so far:** 25 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 7684–7983
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-12

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
   - Set `books[0].last_line` → 7983
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260412-dungeoncrawler-release-b`
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

## Source material (lines 7684–7983)

```

4121805

Appendix

Keen Eyes

Sample Names
Anafa, Antal, Bellis, Boram, Etune, Filiu, Jamir, Kaleb, Linna, Marra, Miro, Rillka,
Sistra, Sumak, Yamyra

Halfling Heritages

Living across the land, halflings of different heritages might appear in regions far
from are their ancestors lived. Choose one of the following halfling heritages at
1st level.

Gutsy Halfling
Your family line is known for keeping a level head and staving off fear when the
chips were down, making them wise leaders and sometimes even heroes. When
you roll a success on a saving throw against an emotion effect, you get a critical
success instead.

Your eyes are sharp, allowing
you to make out small details
about concealed or even
invisible creatures that others
might miss. You gain a +2
circumstance bonus when using
the Seek action to find hidden or
undetected creatures within 30
feet of you. When you target an
opponent that is concealed from
you or hidden from you, reduce
the DC of the flat check to 3 for
a concealed target or 9 for a
hidden one.

Hillock Halfling
Accustomed to a calm life in the hills, your people find rest and relaxation especially
replenishing, particularly when indulging in creature comforts. When you regain Hit
Points overnight, add your level to the Hit Points regained. When anyone uses the
Medicine skill to Treat your Wounds, you can eat a snack to add your level to the Hit
Points you regain from their treatment.

51

16415400

4121806

4121806


Core Rulebook

Nomadic Halfling
Your ancestors have traveled from place to place for
generations, never content to settle down. You gain two
additional languages of your choice, chosen from among
the common and uncommon languages available to you,
and every time you take the Multilingual feat, you gain
another new language.

Twilight Halfling
Your ancestors performed many secret acts under the
concealing cover of dusk, whether for good or ill, and
over time they developed the ability to see in twilight
beyond even the usual keen sight of halflings. You gain
low-light vision.

You hail from deep in a jungle or forest, and you’ve
learned how to use your small size to wriggle through
undergrowth, vines, and other obstacles. You ignore
difficult terrain from trees, foliage, and undergrowth.

16415401

16415401

1ST LEVEL

SURE FEET

FEAT 1

Whether keeping your balance or scrambling up a tricky
climb, your hairy, calloused feet easily find purchase. If
you roll a success on an Acrobatics check to Balance or an
Athletics check to Climb, you get a critical success instead.
You’re not flat-footed when you attempt to Balance or Climb.

FEAT 1

You have learned how to use your sling to fell enormous
creatures. When you hit on an attack with a sling against
a Large or larger creature, increase the size of the weapon
damage die by one step (details on increasing weapon
damage die sizes can be found on page 279).

UNFETTERED HALFLING
FEAT 1

HALFLING

You have learned to remain hidden by using larger folk as
a distraction to avoid drawing attention to yourself. You
can use creatures that are at least one size larger than you
(usually Medium or larger) as cover for the Hide and Sneak
actions, though you still can’t use such creatures as cover for
other uses, such as the Take Cover action.

FEAT 1

HALFLING

You’ve dutifully learned how to keep your balance and how
to stick to the shadows where it’s safe, important skills
passed down through generations of halfling tradition. You
gain the trained proficiency rank in Acrobatics and Stealth.
If you would automatically become trained in one of those
skills (from your background or class, for example), you
instead become trained in a skill of your choice. You also
become trained in Halfling Lore.

FORTUNE

You favor traditional halfling weapons, so you’ve learned how to
use them more effectively. You have the trained proficiency with
the sling, halfling sling staff, and shortsword. You gain access to
all uncommon halfling weapons. For the purpose of determining
your proficiency, martial halfling weapons are simple weapons
and advanced halfling weapons are martial weapons.

HALFLING

At 1st level, you gain one ancestry feat, and you gain an
additional ancestry feat every 4 levels thereafter (at 5th,
9th, 13th, and 17th level). As a halfling, you select from
among the following ancestry feats.

DISTRACTING SHADOWS

FEAT 1

HALFLING

TITAN SLINGER

Ancestry Feats

HALFLING LUCK [free-action]

HALFLING WEAPON FAMILIARITY

HALFLING

Wildwood Halfling

HALFLING LORE

avoids you, and to an extent, that might even be true. You
can reroll the triggering check, but you must use the new
result, even if it’s worse than your first roll.

FEAT 1

HALFLING

Frequency once per day
Trigger You fail a skill check or saving throw.
Your happy-go-lucky nature makes it seem like misfortune

4121806

FEAT 1

HALFLING

You were forced into service as a laborer, either pressed into
indentured servitude or shackled by the evils of slavery, but
you’ve since escaped and have trained to ensure you’ll never
be caught again. Whenever you roll a success on a check to
Escape or a saving throw against an effect that would impose
the grabbed or restrained condition on you, you get a critical
success instead. Whenever a creature rolls a failure on a check
to Grapple you, they get a critical failure instead. If a creature
uses the Grab ability on you, it must succeed at an Athletics
check to grab you instead of automatically grabbing you.

WATCHFUL HALFLING

FEAT 1

HALFLING

Your communal lifestyle causes you to pay close attention
to the people around you, allowing you to more easily notice
when they act out of character. You gain a +2 circumstance
bonus to Perception checks when using the Sense Motive
basic action to notice enchanted or possessed characters. If
you aren’t actively using Sense Motive on an enchanted or
possessed character, the GM rolls a secret check, without the
usual circumstance and with a –2 circumstance penalty, for you
to potentially notice the enchantment or possession anyway.
In addition to using it for skill checks, you can use the Aid
basic action to grant a bonus to another creature’s saving
throw or other check to overcome enchantment or possession.

52

16415401

4121807

4121807

16415402

16415402


Ancestries & backgrounds

As usual for Aid, you need to prepare by using an action on your turn to encourage the
creature to fight against the effect.

5TH LEVEL
CULTURAL ADAPTABILITY

FEAT 5

HALFLING

During your adventures, you’ve honed your ability to adapt to the culture of the predominant
ancestry around you. You gain the Adopted Ancestry general feat, and you also gain one
1st-level ancestry feat from the ancestry you chose for the Adopted Ancestry feat.

HALFLING WEAPON TRICKSTER

FEAT 5

HALFLING

Prerequisites Halfling Weapon Familiarity
You are particularly adept at fighting with your people’s favored weapons. Whenever you
critically succeed at an attack roll using a shortsword, a sling, or a halfling weapon, you
apply the weapon’s critical specialization effect.

HALFLING ADVENTURERS
Halflings’ natural wanderlust
and opportunistic nature make
them ideal adventurers. Many
people put up with their vivacious
attitudes in return for the natural
talents they provide and the
popular superstition that traveling
with a halfling is good luck.
Typical backgrounds for
halflings include acrobat,
criminal, emissary, entertainer,
laborer, and street urchin.
Halflings make great clerics
and rogues, but many also
become monks or rangers.

9TH LEVEL
GUIDING LUCK

FEAT 9

HALFLING

Prerequisites Halfling Luck
Your luck guides you to look the right way and aim your blows unerringly. You can use
Halfling Luck twice per day: once in response to its normal trigger, and once when you
fail a Perception check or attack roll instead of the normal trigger.

IRREPRESSIBLE

FEAT 9

HALFLING

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
Crafting
```
