# Reference Document Scan — PF2E Core Rulebook (Fourth Printing)

**Site:** dungeoncrawler  
**Next release:** 20260408-dungeoncrawler-release-b  
**Book:** PF2E Core Rulebook (Fourth Printing) (rulebook)  
**Progress:** lines 6484–6783 of 103266 (6% through this book)  
**Features generated this cycle so far:** 123 / 30 cap  
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
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Created: 2026-04-08

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
   - Set `books[0].last_line` → 6783
   - Set `books[0].status` → `in_progress` (or `complete` if end of book)
   - Set `last_scan_release` → `20260408-dungeoncrawler-release-b`
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

## Source material (lines 6484–6783)

```
as befits their fey lineage, but this isn’t enough to avoid
the Bleaching unless they supplement this magic with
new experiences. The Bleaching slowly drains the
color—literally—from gnomes, and it plunges those
affected into states of deep depression that eventually
claim their lives. Very few gnomes survive
this scourge, becoming deeply morose
and wise survivors known
as bleachlings.

16415391

16415391

If you want a character with boundless enthusiasm
and an alien, fey outlook on morality and life, you should
play a gnome.

You Might...
• Embrace learning and hop from one area of study
to another without warning.
• Rush into action before fully taking stock of the
entire situation.
• Speak, think, and move quickly, and lose patience
with those who can’t keep up.

Others Probably...
• Appreciate your enthusiasm and the energy with
which you approach new situations.
• Struggle to understand your motivations or adapt
to your rapid changes of direction.
• See you as unpredictable, flighty, unreliable, or
even reckless.

4121796

Physical Description
Most gnomes stand just over 3 feet in height and weigh
little more than a human child. They exhibit a wide
range of natural skin, hair, and eye colors. For gnomes
that haven’t begun the Bleaching, nearly any hair and
eye color other than white is possible, with vibrant
colors most frequent, while skin tones span a slightly
narrower spectrum and tend toward earthy tones and
pinkish hues, though occasionally green, black, or pale
blue. Gnomes’ large eyes and dense facial muscles allow
them to be particularly expressive in their emotions.
Gnomes typically reach physical maturity at the age
of 18, though many gnomes maintain a childlike
curiosity about the world even into adulthood.
A gnome can theoretically live to any age if
she can stave off the Bleaching indefinitely,
but in practice gnomes rarely live longer than
around 400 years.

Society
While most gnomes adopt some of the cultural
practices of the region in which they live, they tend
to pick and choose, adjusting their communities to
fit their own fey logic. This often leads to majority
gnome communities eventually consisting almost entirely

42

16415391

4121797

4121797

16415392

16415392


Ancestries & backgrounds

of gnomes, as other people, bewildered by gnomish political decisions, choose to
move elsewhere. Gnomes have little culture that they would consider entirely their
own. No gnome kingdoms or nations exist on the surface of Golarion, and gnomes
wouldn’t know what to do with such a state if they had one.
By necessity, few gnomes marry for life, instead allowing relationships to run their
course before amicably moving on, the better to stave off the Bleaching with new
experiences. Though gnome families tend to be small, many gnome communities
raise children communally, with fluid family boundaries. As adults depart the
settlement, unrelated adolescents sometimes tag along, creating adopted families to
journey together.

Alignment and Religion
Though gnomes are impulsive tricksters with inscrutable motives and confusing
methods, many at least attempt to make the world a better place. They are prone to
fits of powerful emotion, and they are often good but rarely lawful. Gnomes most
commonly worship deities that value individuality and nature, such as Cayden
Cailean, Desna, Gozreh, and Shelyn.

Hit Points
8

Gnome names can get quite complex and polysyllabic. They have little interest in
familial names, and most children receive their names purely on a parent’s whim.
Gnomes rarely concern themselves with how easy their names are to pronounce,
and they often go by shorter nicknames. Some even collect and chronicle
these nicknames. Among gnomes, the shorter the name, the more feminine it’s
considered to be.

Sample Names
Abroshtor, Bastargre, Besh, Fijit, Halungalom, Krolmnite, Neji, Majet, Pai,
Poshment, Queck, Trig, Zarzuket, Zatqualmie

Small

Speed

Classes

25 feet

Skills
Ability Boosts
Constitution
Charisma
Free

Ability Flaw
Strength

Common
Gnomish
Sylvan
Additional languages equal to
your Intelligence modifier (if it’s
positive). Choose from Draconic,
Dwarven, Elven, Goblin, Jotun,
Orcish, and any other languages
to which you have access (such
as the languages prevalent in
your region).

Traits

Gnome Heritages

A diverse collection of oddballs, gnomes have all sorts of peculiar strains among
their bloodlines. Choose one of the following gnome heritages at 1st level.

Chameleon Gnome
The color of your hair and skin is mutable, possibly due to latent magic. You can
slowly change the vibrancy and the exact color, and the coloration can be different
across your body, allowing you to create patterns or other colorful designs. It takes
a single action for minor localized shifts and up to an hour for dramatic shifts
throughout your body. While you’re asleep, the colors shift on their own in tune
with your dreams, giving you an unusual coloration each morning. When you’re in
an area where your coloration is roughly similar to the environment (for instance,
forest green in a forest), you can use the single action to make minor localized
shifts designed to help you blend into your surroundings. This grants you a +2
circumstance bonus to Stealth checks until your surroundings shift in coloration
or pattern.

Introduction
Ancestries &
Backgrounds

Size

Languages

Names

2

Gnome
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

4121797

Appendix

Low-Light Vision
You can see in dim light as
though it were bright light,
and you ignore the concealed
condition due to dim light.

Fey-touched Gnome
The blood of the fey is so strong within you that you’re truly one of them. You gain
the fey trait, in addition to the gnome and humanoid traits. Choose one cantrip
from the primal spell list (page 314). You can cast this spell as a primal innate spell
at will. A cantrip is heightened to a spell level equal to half your level rounded up.
You can change this cantrip to a different one from the same list once per day by
meditating to realign yourself with the First World; this is a 10-minute activity that
has the concentrate trait.

43

16415392

4121798

4121798


Core Rulebook

Sensate Gnome
You see all colors as brighter, hear all sounds as richer,
and especially smell all scents with incredible detail.
You gain a special sense: imprecise scent with a range of
30 feet. This means you can use your sense of smell to
determine the exact location of a creature (as explained
on page 465). The GM will usually double the range if
you’re downwind from the creature or halve the range
if you’re upwind.
In addition, you gain a +2 circumstance bonus to
Perception checks whenever you’re trying to locate an
undetected creature that is within the range of your scent.

Umbral Gnome
Whether from a connection to dark or shadowy fey,
from the underground deep gnomes also known as
svirfneblin, or another source, you can see in complete
darkness. You gain darkvision.

Wellspring Gnome
Some other source of magic has a greater hold on
you than the primal magic of your fey lineage does.
This connection might come from an occult plane
or an ancient occult song; a deity, celestial, or fiend;
magical effluent left behind by a mage war; or ancient
rune magic.
Choose arcane, divine, or occult. You gain one cantrip
from that magical tradition’s spell list (pages 307–315).
You can cast this spell as an innate spell at will, as a
spell of your chosen tradition. A cantrip is heightened
to a spell level equal to half your level rounded up.
Whenever you gain a primal innate spell from a gnome
ancestry feat, change its tradition from primal to your
chosen tradition.

16415393

16415393

Ancestry Feats

At 1st level, you gain one ancestry feat, and you gain
an additional ancestry feat every 4 levels thereafter (at
5th, 9th, 13th, and 17th level). As a gnome, you select
from among the following ancestry feats.

from, and use the Diplomacy skill with animals that have a
burrow Speed, such as badgers, ground squirrels, moles, and
prairie dogs. The GM determines which animals count for
this ability.

FEY FELLOWSHIP

FEAT 1

GNOME

Your enhanced fey connection affords you a warmer reception
from creatures of the First World as well as tools to foil their
tricks. You gain a +2 circumstance bonus to both Perception
checks and saving throws against fey.
In addition, whenever you meet a fey creature in a social
situation, you can immediately attempt a Diplomacy check to
Make an Impression on that creature rather than needing to
converse for 1 minute. You take a –5 penalty to the check.
If you fail, you can engage in 1 minute of conversation and
attempt a new check at the end of that time rather than
accepting the failure or critical failure result.
Special If you have the Glad-Hand skill feat, you don’t take
the penalty on your immediate Diplomacy check if the target
is a fey.

FIRST WORLD MAGIC

FEAT 1

GNOME

Your connection to the First World grants you a primal innate
spell, much like those of the fey. Choose one cantrip from the
primal spell list (page 314). You can cast this spell as a primal
innate spell at will. A cantrip is heightened to a spell level
equal to half your level rounded up.

GNOME OBSESSION
```
