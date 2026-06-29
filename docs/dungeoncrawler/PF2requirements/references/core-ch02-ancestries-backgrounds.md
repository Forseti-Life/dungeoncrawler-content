# PF2E Core Rulebook — Chapter 2: Ancestries & Backgrounds
## Systematic Requirements Analysis (Paragraph by Paragraph)

---

## SECTION: Chapter Overview

### Paragraph 1
> "Your character's ancestry determines which people they call their own, whether it's diverse and ambitious humans, insular but vivacious elves, traditionalist and family-focused dwarves, or any of the other folk who call Golarion home. A character's ancestry and their experiences prior to their life as an adventurer—represented by a background—might be key parts of their identity, shape how they see the world, and help them find their place in it."

Requirements identified:
- Every character must have exactly one **ancestry** selected from a defined set of ancestry types.
- Ancestry is a character identity field, not a mechanical modifier only — it has narrative/display properties.
- The system must support at minimum: Human, Elf, Dwarf, and other ancestry types as expandable entries.

---

### Paragraph 2
> "A character has one ancestry and one background, both of which you select during character creation. You'll also select a number of languages for your character. Once chosen, your ancestry and background can't be changed."

Requirements identified:
- Character creation must enforce: exactly 1 ancestry + exactly 1 background + 1 or more languages.
- Ancestry and background are immutable after character creation (no re-selection post-creation).
- Languages are a separate selectable field at character creation.

---

### Paragraph 3 — Chapter structure overview
> "Ancestries express the culture your character hails from. Within many ancestries are heritages—subgroups that each have their own characteristics. An ancestry provides ability boosts (and perhaps ability flaws), Hit Points, ancestry feats, and sometimes additional abilities."

Requirements identified:
- Ancestry data model must include: **ability boosts**, optional **ability flaw**, **Hit Points** (flat bonus), **ancestry feats**, and optional **special abilities**.
- Many (not all) ancestries contain sub-types called **heritages** — each with their own distinct characteristics.
- Heritage is a child entity of ancestry; ancestry → heritage is a one-to-many relationship.

---

### Paragraph 4
> "Backgrounds, starting on page 60, describe training or environments your character experienced before becoming an adventurer. Your character's background provides ability boosts, skill training, and a skill feat."

Requirements identified:
- Background data model must include: **ability boosts**, **skill training** (one or more trained skills), and exactly one **skill feat**.

---

### Paragraph 5
> "Languages, starting on page 65, let your character communicate with the wonderful and weird people and creatures of the world."

Requirements identified:
- Languages are a distinct entity type.
- Characters have a language list; ancestry and Intelligence modifier determine initial languages.

---

## SECTION: Ancestry Entry Format

### Paragraph — Hit Points
> "This tells you how many Hit Points your character gains from their ancestry at 1st level. You'll add the Hit Points from your character's class (including their Constitution modifier) to this number."

Requirements identified:
- Ancestry provides a flat HP bonus at 1st level.
- Total HP at 1st level = ancestry HP + class HP + Constitution modifier.
- Ancestry HP field is a required field on every ancestry record.

---

### Paragraph — Size
> "This tells you the physical size of members of the ancestry. Medium corresponds roughly to the height and weight range of a human adult, and Small is roughly half that."

Requirements identified:
- Every ancestry has a **Size** field; valid values include at minimum: Small, Medium.
- Size affects game interactions (spells, effects, reach, etc.) — must be a typed enum, not free text.

---

### Paragraph — Speed
> "This entry lists how far a member of the ancestry can move each time they spend an action (such as Stride) to do so."

Requirements identified:
- Every ancestry has a **Speed** field expressed in feet (integer).
- Speed is the movement distance per action (specifically per Stride action or equivalent).

---

### Paragraph — Ability Boosts
> "This lists the ability scores you apply ability boosts to when creating a character of this ancestry. Most ancestries provide ability boosts to two specified ability scores, plus a free ability boost that you can apply to any other score of your choice."

Requirements identified:
- Ancestry provides a list of **fixed ability boosts** (typically 2 named scores) plus exactly 1 **free ability boost** (player's choice of any score not already boosted).
- Ability boost model: each boost targets one of the 6 core ability scores (STR, DEX, CON, INT, WIS, CHA).
- "Free" boost must be applied to a score not already receiving a fixed boost from the same source.

---

### Paragraph — Ability Flaw
> "This lists the ability score to which you apply an ability flaw when creating a character of this ancestry. Most ancestries, with the exception of humans, include an ability flaw."

Requirements identified:
- Ancestry optionally has an **ability flaw** field (one ability score that takes a -2 penalty at creation).
- Humans are the exception — they have no ability flaw.
- Ability flaw is nullable; the data model must allow a null/empty flaw entry.

---

### Paragraph — Languages
> "This tells you the languages that members of the ancestry speak at 1st level. If your Intelligence modifier is +1 or higher, you can select more languages from a list given here."

Requirements identified:
- Every ancestry includes a list of **starting languages** automatically granted at 1st level.
- Characters with Intelligence modifier ≥ +1 may select **bonus languages** from an ancestry-defined additional language list.
- Number of bonus languages = Intelligence modifier (if positive).
- Ancestry must store: `starting_languages[]`, `bonus_language_options[]`.

---

### Paragraph — Traits
> "These descriptors have no mechanical benefit, but they're important for determining how certain spells, effects, and other aspects of the game interact with your character."

Requirements identified:
- Every ancestry has one or more **traits** (tags/descriptors).
- Traits have no direct stat effect but are referenced by spells and game effects for targeting/interaction.
- Traits must be a queryable field (e.g., spells that say "affects Humanoids" look up the trait list).

---

### Paragraph — Special Abilities
> "Any other entries in the sidebar represent abilities, senses, and other qualities all members of the ancestry manifest. These are omitted for ancestries with no special rules."

Requirements identified:
- Ancestry optionally has **special abilities** — passive abilities or senses granted to all members.
- Special abilities are an optional list field on the ancestry record; may be empty.

---

### Paragraph — Heritages
> "You select a heritage at 1st level to reflect abilities passed down to you from your ancestors or common among those of your ancestry in the environment where you were born or grew up. You have only one heritage and can't change it later. A heritage is not the same as a culture or ethnicity, though some cultures or ethnicities might have more or fewer members from a particular heritage."

Requirements identified:
- Character must select exactly **1 heritage** at 1st level from the list of heritages for their ancestry.
- Heritage selection is immutable after 1st level.
- Heritage is a mechanical subtype of ancestry, not a cultural/narrative label — must be modeled as distinct from cultural descriptors.

---

### Paragraph — Ancestry Feats
> "You gain your first ancestry feat at 1st level, and you gain another at 5th level, 9th level, 13th level, and 17th level, as indicated in the class advancement table."

Requirements identified:
- Ancestry feat gain schedule: levels **1, 5, 9, 13, 17** (5 total across a character's career).
- At 1st level, only 1st-level ancestry feats are available.
- At later levels, any ancestry feat of character level or lower may be selected.
- Ancestry feats may have **prerequisites** — conditions the character must meet to select the feat.

---

## SECTION: Dwarf Ancestry

### Paragraph — Overview (flavor; requirements embedded)
> "Dwarves have a well-earned reputation as a stoic and stern people, ensconced within citadels and cities carved from solid rock."

Requirements identified:
- Dwarf is a valid ancestry type in the system.
- Dwarves are categorized as **Humanoid** (trait).

---

### Paragraph — Physical Description
> "Dwarves are short and stocky, standing about a foot shorter than most humans. They have wide, compact bodies and burly frames... Dwarves typically reach physical adulthood around the age of 25... A typical dwarf can live to around 350 years old."

Requirements identified:
- Ancestry records may include optional lore fields: typical lifespan, age of adulthood, physical description — for display/flavor purposes (not mechanical).

---

### Dwarf Stat Block
> Hit Points: 10 | Size: Medium | Speed: 20 feet | Ability Boosts: Constitution, Wisdom, Free | Ability Flaw: Charisma | Languages: Common, Dwarven + bonus from Intelligence

Requirements identified:
- **Dwarf** ancestry data:
  - `hp`: 10
  - `size`: Medium
  - `speed`: 20 ft
  - `ability_boosts`: [Constitution, Wisdom, (free)]
  - `ability_flaw`: Charisma
  - `languages`: [Common, Dwarven]
  - `bonus_language_options`: [Gnomish, Goblin, Jotun, Orcish, Terran, Undercommon]
  - `traits`: [Dwarf, Humanoid]
  - `special_abilities`: [Clan Dagger (item grant)]

---

### Special Ability — Clan Dagger
> "You get one clan dagger (page 280) for free, as it was given to you at birth."

Requirements identified:
- Some ancestries grant a **starting item** at character creation (not purchased, granted).
- The item grant must be applied automatically during character creation.
- Item: Clan Dagger (a specific item in the item database).

---

## SECTION: Dwarf Heritages

### Heritage — Ancient-Blooded Dwarf
> "Dwarven heroes of old could shrug off their enemies' magic, and some of that resistance manifests in you. You gain the Call on Ancient Blood reaction."
> **CALL ON ANCIENT BLOOD [reaction]** — Trigger: You attempt a saving throw against a magical effect, but you haven't rolled yet. Effect: +1 circumstance bonus to all saves until end of turn (including triggering save).

Requirements identified:
- Heritage: **Ancient-Blooded Dwarf**
  - Grants the **Call on Ancient Blood** reaction ability.
  - Reaction trigger: "attempting a saving throw against a magical effect, before rolling."
  - Effect: +1 circumstance bonus to all saves until end of turn.
  - Circumstance bonuses must be tracked as a typed bonus (circumstance bonuses don't stack with other circumstance bonuses).

---

### Heritage — Death Warden Dwarf
> "If you roll a success on a saving throw against a necromancy effect, you get a critical success instead."

Requirements identified:
- Heritage: **Death Warden Dwarf**
  - Passive ability: success on saving throw vs. necromancy effects is upgraded to critical success.
  - The system must support **degree-of-success upgrades** as an effect type (success → critical success).
  - Effect is conditional on damage type/school tag: `school: necromancy`.

---

### Heritage — Forge Dwarf
> "This grants you fire resistance equal to half your level (minimum 1), and you treat environmental heat effects as if they were one step less extreme."

Requirements identified:
- Heritage: **Forge Dwarf**
  - Grants **fire resistance** = floor(level / 2), minimum 1 (scales with level).
  - Environmental heat effects are downgraded by 1 step on a severity scale (incredible → extreme → severe → etc.).
  - The system must model a **damage resistance** field per damage type on a character.
  - The system must model **environmental severity levels** as an ordered scale for heat (and presumably cold, etc.).

---

### Heritage — Rock Dwarf
> "You gain a +2 circumstance bonus to your Fortitude or Reflex DC against attempts to Shove or Trip you... if any effect would force you to move 10 feet or more, you are moved only half the distance."

Requirements identified:
- Heritage: **Rock Dwarf**
  - +2 circumstance bonus to Fortitude DC and Reflex DC specifically against Shove and Trip actions.
  - +2 circumstance bonus to saving throws vs. spells/effects that would knock prone.
  - Forced movement of ≥10 ft is halved.
  - The system must distinguish **DC** (difficulty to affect this character) from **save bonus** — bonuses can apply to either.
  - Forced movement must be a trackable effect type with a distance field.

---

### Heritage — Strong-Blooded Dwarf
> "You gain poison resistance equal to half your level (minimum 1), and each of your successful saving throws against a poison affliction reduces its stage by 2, or by 1 for a virulent poison. Each critical success against an ongoing poison reduces its stage by 3, or by 2 for a virulent poison."

Requirements identified:
- Heritage: **Strong-Blooded Dwarf**
  - Grants **poison resistance** = floor(level / 2), minimum 1 (scales with level).
  - Afflictions (poisons) have a **stage** value that progresses/regresses.
  - Saving throw results (success, critical success) affect stage regression:
    - Success: stage −2 (standard), stage −1 (virulent)
    - Critical success: stage −3 (standard), stage −2 (virulent)
  - Afflictions must have a `virulent` boolean flag that modifies stage-regression math.

---

## SECTION: Dwarf Ancestry Feats

### Feat — Dwarven Lore (Level 1)
> "You gain the trained proficiency rank in Crafting and Religion. If you would automatically become trained in one of those skills (from your background or class), you instead become trained in a skill of your choice. You also become trained in Dwarven Lore."

Requirements identified:
- Feat grants: trained in **Crafting**, trained in **Religion**, trained in **Dwarven Lore** (a lore skill).
- **Conflict resolution rule**: if another source (background/class) would also grant training in the same skill, the duplicate training converts to a free skill of the player's choice.
- The system must detect duplicate skill training grants and apply the replacement rule.

---

### Feat — Dwarven Weapon Familiarity (Level 1)
> "You are trained with the battle axe, pick, and warhammer. You also gain access to all uncommon dwarf weapons. For the purpose of determining your proficiency, martial dwarf weapons are simple weapons and advanced dwarf weapons are martial weapons."

Requirements identified:
- Feat grants trained proficiency in: battle axe, pick, warhammer.
- Unlocks access to **uncommon dwarf weapons** (access gate: ancestry feat).
- Weapon proficiency downgrade rule for ancestry weapons: martial dwarf weapons count as simple; advanced dwarf weapons count as martial (for proficiency level calculation only).

---

### Feat — Rock Runner (Level 1)
> "You can ignore difficult terrain caused by rubble and uneven ground made of stone and earth. When you use Acrobatics to Balance on narrow surfaces or uneven ground made of stone or earth, you aren't flat-footed, and a success becomes a critical success."

Requirements identified:
- Difficult terrain tags: rubble, uneven ground — these must be typed terrain properties.
- Balance action (Acrobatics) has surface type tags: narrow surfaces, uneven ground.
- Feat modifies Acrobatics Balance: removes flat-footed condition, upgrades success → critical success on stone/earth surfaces.

---

### Feat — Stonecunning (Level 1)
> "You gain a +2 circumstance bonus to Perception checks to notice unusual stonework... If you aren't using the Seek action or searching, the GM automatically rolls a secret check for you to notice unusual stonework anyway. This check doesn't gain the circumstance bonus, and it takes a –2 circumstance penalty."

Requirements identified:
- +2 circumstance bonus to Perception to detect unusual stonework and stone-hidden mechanical traps.
- **Passive secret check**: when not actively Seeking, system (GM) auto-rolls Perception with −2 penalty for stonework detection.
- System must support **passive/automatic secret rolls** triggered by environmental proximity, distinct from active Seek checks.

---

### Feat — Unburdened Iron (Level 1)
> "Ignore the reduction to your Speed from any armor you wear. Any time you're taking a penalty to your Speed from some other reason, deduct 5 feet from the penalty."

Requirements identified:
- Armor normally applies a Speed penalty — this feat nullifies that specific penalty.
- For other Speed penalties: subtract 5 ft from one penalty of the player's choice.
- Speed penalty reduction is applied to exactly one source when multiple exist (player-chosen).

---

### Feat — Vengeful Hatred (Level 1)
> "Choose one of the following dwarven ancestral foes when you gain Vengeful Hatred: drow, duergar, giant, or orc. You gain a +1 circumstance bonus to damage with weapons and unarmed attacks against creatures with that trait. If your attack would deal more than one weapon die of damage, the bonus equals the number of weapon dice."

Requirements identified:
- Feat has a **one-time choice** at selection: ancestral foe (drow, duergar, giant, or orc) — stored on character.
- +1 circumstance bonus to damage vs. creatures with that trait; scales to match weapon die count if > 1 die.
- **Conditional triggered bonus**: if target critically hits the character and deals damage, the Vengeful Hatred bonus applies to that creature for 1 minute (ignoring trait requirement).
- System must support creature trait tags for targeting bonuses.

---

### Feat — Boulder Roll (Level 5, Two-Actions)
> "Take a Step into the square of a foe that is your size or smaller, and the foe must move into the empty space directly behind it... The foe can attempt a Fortitude saving throw against your Athletics DC to block your Step. Unless it critically succeeds, it takes bludgeoning damage equal to your level plus your Strength modifier."

Requirements identified:
- Prerequisites: Rock Runner.
- Two-action activity.
- Forced movement mechanic: foe is pushed to the space directly behind it.
- Foe's save: Fortitude vs. caster's Athletics DC.
- Damage on failed save: bludgeoning, amount = character level + Strength modifier.
- Critical success on save = no damage, no movement; any other result = movement + damage.
- Requires target size ≤ acting character's size.

---

### Feat — Dwarven Weapon Cunning (Level 5)
> "Whenever you critically hit using a battle axe, pick, warhammer, or a dwarf weapon, you apply the weapon's critical specialization effect."

Requirements identified:
- Prerequisites: Dwarven Weapon Familiarity.
- On critical hit with listed weapons: apply **critical specialization effect** for that weapon type.
- Each weapon type must have a defined `critical_specialization` effect in the weapon database.

---

### Feat — Mountain's Stoutness (Level 9)
> "Increase your maximum Hit Points by your level. When you have the dying condition, the DC of your recovery checks is equal to 9 + your dying value (instead of 10 + your dying value). If you also have the Toughness feat, HP from both feats are cumulative, and dying recovery DC = 6 + dying value."

Requirements identified:
- Max HP increases by character level (dynamic, must recalculate on level up).
- Recovery check DC normally = 10 + dying value; this feat changes it to 9 + dying value.
- Toughness feat interaction: both HP bonuses stack; recovery DC drops to 6 + dying value if both feats held.
- System must track and combine feat-based HP bonuses.

---

### Feat — Stonewalker (Level 9)
> "You gain meld into stone as a 3rd-level divine innate spell that you can cast once per day. If you have Stonecunning, you can attempt to find unusual stonework and traps that require legendary proficiency in Perception."

Requirements identified:
- Grants **innate spell**: meld into stone, divine, 3rd level, 1/day.
- Innate spells are granted by ancestry/feat — not prepared or slots from class.
- Innate spells have a `uses_per_day` field and a `spell_tradition` (divine here).
- With Stonecunning: unlocks attempting Perception checks that normally require legendary proficiency.

---

### Feat — Dwarven Weapon Expertise (Level 13)
> "Whenever you gain a class feature that grants you expert or greater proficiency in certain weapons, you also gain that proficiency for battle axes, picks, warhammers, and all dwarven weapons in which you are trained."

Requirements identified:
- Prerequisites: Dwarven Weapon Familiarity.
- When a class grants weapon proficiency upgrades (expert+), the upgrade automatically applies to dwarf weapons as well.
- System must support **proficiency inheritance** — feat-linked weapons receive the same tier as the class-granted group.

---


## SECTION: Elf Ancestry

### Elf Stat Block
> Hit Points: 6 | Size: Medium | Speed: 30 feet | Ability Boosts: Dexterity, Intelligence, Free | Ability Flaw: Constitution | Languages: Common, Elven + bonus from Intelligence | Special: Low-Light Vision

Requirements identified:
- **Elf** ancestry data:
  - `hp`: 6
  - `size`: Medium
  - `speed`: 30 ft
  - `ability_boosts`: [Dexterity, Intelligence, (free)]
  - `ability_flaw`: Constitution
  - `languages`: [Common, Elven]
  - `bonus_language_options`: [Celestial, Draconic, Gnoll, Gnomish, Goblin, Orcish, Sylvan]
  - `traits`: [Elf, Humanoid]
  - `special_abilities`: [Low-Light Vision]

---

### Special Ability — Low-Light Vision
> "You can see in dim light as though it were bright light, so you ignore the concealed condition due to dim light."

Requirements identified:
- **Low-Light Vision**: dim light is treated as bright light for this character.
- Characters with Low-Light Vision do not gain the concealed condition from dim light.
- Vision type is a character field; must distinguish: Normal, Low-Light Vision, Darkvision.

---

## SECTION: Elf Heritages

### Heritage — Arctic Elf
> "You gain cold resistance equal to half your level (minimum 1). You treat environmental cold effects as if they were one step less extreme."

Requirements identified:
- Cold resistance = floor(level / 2), minimum 1 (scales with level).
- Environmental cold severity scale mirrors heat scale — system must model both.

---

### Heritage — Cavern Elf
> "You were born or spent many years in underground tunnels where light is scarce. You gain darkvision."

Requirements identified:
- Grants **Darkvision**: can see in total darkness as though it were dim light (black and white).
- Darkvision is a character vision property distinct from Low-Light Vision.

---

### Heritage — Seer Elf
> "You can cast the detect magic cantrip as an arcane innate spell at will. A cantrip is heightened to a spell level equal to half your level rounded up. You gain a +1 circumstance bonus to checks to Identify Magic and to Decipher Writing of a magical nature."

Requirements identified:
- Innate cantrip: detect magic, arcane tradition, at-will (no uses/day limit).
- Cantrip heightening rule: cantrip spell level = ceil(character level / 2).
- +1 circumstance bonus to Identify Magic and Decipher Writing (magical) skill checks.
- Identify Magic and Decipher Writing can use: Arcana, Nature, Occultism, or Religion.

---

### Heritage — Whisper Elf
> "You can use the Seek action to sense undetected creatures in a 60-foot cone instead of a 30-foot cone. You also gain a +2 circumstance bonus to locate undetected creatures that you could hear within 30 feet with a Seek action."

Requirements identified:
- Default Seek range for undetected creatures: 30-foot cone.
- Whisper Elf extends Seek range to 60-foot cone.
- +2 circumstance bonus to Seek checks targeting undetected creatures within 30 ft that emit sound.
- Undetected status is a creature visibility state the system must track.

---

### Heritage — Woodland Elf
> "When Climbing trees, vines, and other foliage, you move at half your Speed on a success and at full Speed on a critical success... You can always use the Take Cover action when in forest terrain, even without a nearby obstacle."

Requirements identified:
- Standard Climb: success = 5 ft (typically), critical success = 10 ft. Woodland Elf: success = half Speed, critical success = full Speed.
- Does not override Climb Speed (if character has one, normal rules apply).
- Take Cover normally requires an adjacent obstacle; Woodland Elf removes that requirement in forest terrain.
- Terrain type must be a tagged property: forest, for Take Cover interaction.

---

## SECTION: Elf Ancestry Feats

### Feat — Ancestral Longevity (Level 1)
> "Prerequisites: at least 100 years old. During your daily preparations, you can become trained in one skill of your choice. This proficiency lasts until you prepare again and cannot be used as a prerequisite for permanent options."

Requirements identified:
- Prerequisite: character age ≥ 100 years — character must have an age field.
- Grants a temporary trained proficiency in one skill, refreshed daily.
- Temporary proficiency: cannot satisfy feat prerequisites or permanent skill increases.
- System must distinguish **permanent** vs. **temporary** proficiency sources.

---

### Feat — Elven Lore (Level 1)
> "You gain trained in Arcana and Nature. Also become trained in Elven Lore. Duplicate training from background/class converts to a free skill choice."

Requirements identified:
- Same duplicate-training resolution rule as Dwarven Lore (see above).
- Grants Elven Lore (a lore skill).

---

### Feat — Elven Weapon Familiarity (Level 1)
> "Trained with longbows, composite longbows, longswords, rapiers, shortbows, composite shortbows. Unlocks uncommon elf weapons. Martial elf weapons count as simple; advanced elf weapons count as martial for proficiency."

Requirements identified:
- Same weapon familiarity/downgrade model as Dwarven Weapon Familiarity.
- Trained weapons: longbow, composite longbow, longsword, rapier, shortbow, composite shortbow.

---

### Feat — Forlorn (Level 1)
> "+1 circumstance bonus to saving throws against emotion effects. Success against emotion effect becomes critical success."

Requirements identified:
- Emotion is a spell/effect **descriptor tag** the system must support.
- +1 circumstance bonus to saves vs. emotion-tagged effects.
- Degree-of-success upgrade: success → critical success on emotion saves.

---

### Feat — Nimble Elf (Level 1)
> "Your Speed increases by 5 feet."

Requirements identified:
- Flat Speed bonus, not conditional — applies to base land Speed.

---

### Feat — Otherworldly Magic (Level 1)
> "Choose one cantrip from the arcane spell list. You can cast this cantrip as an arcane innate spell at will. Heightened to half level rounded up."

Requirements identified:
- Player selects one cantrip from arcane list at feat acquisition — stored on character.
- Innate cantrip: arcane, at-will, auto-heightened per standard cantrip rule.

---

### Feat — Unwavering Mien (Level 1)
> "Whenever you are affected by a mental effect lasting ≥2 rounds, reduce duration by 1 round. Treat saves against sleep effects as one degree of success better."

Requirements identified:
- Mental is a descriptor tag on effects.
- Duration reduction: mental effects with duration ≥ 2 rounds are reduced by 1 round on application.
- Degree-of-success upgrade on sleep effect saves (sleep is a sub-tag of mental or its own tag).

---

### Feat — Ageless Patience (Level 5)
> "Spend twice as much time on a Perception or skill check to gain +2 circumstance bonus. Natural 1 is not treated as worse than usual; critical failure only occurs if result is ≥10 below DC."

Requirements identified:
- Double-time check: a deliberate choice to take twice as long in exchange for +2 circumstance bonus.
- Modified natural-1 rule: normally a natural 1 triggers degree-of-success downgrade; this feat suppresses that for these checks.
- Critical failure threshold: result must be DC−10 or worse (standard rule); this feat makes this the only critical failure condition.

---

### Feat — Elven Weapon Elegance (Level 5)
> "Prerequisites: Elven Weapon Familiarity. Whenever you critically hit with an elf weapon or listed weapons, apply the weapon's critical specialization effect."

Requirements identified:
- Same critical specialization model as Dwarven Weapon Cunning.

---

### Feat — Elf Step (Level 9, One-Action)
> "You Step 5 feet twice."

Requirements identified:
- A single one-action feat produces two Step movements (5 ft each).
- Step normally cannot trigger reactions; both steps here carry that same restriction.

---

### Feat — Expert Longevity (Level 9)
> "Prerequisites: Ancestral Longevity. When choosing a temporary trained skill, also choose a skill you are already trained in and become temporarily expert in it. When effects expire, may retrain one skill increase."

Requirements identified:
- Extends Ancestral Longevity: adds a temporary expert-rank skill (must already be trained).
- Retraining opportunity on expiry: one permanent skill increase may be reassigned to match the temporary skills used.

---

### Feat — Universal Longevity (Level 13, One-Action)
> "Prerequisites: Expert Longevity. Frequency: once per day. Change the skills selected with Ancestral Longevity and Expert Longevity."

Requirements identified:
- Once per day, the character may swap both their temporary Longevity skill selections in one action.
- Frequency field: 1/day.

---

### Feat — Elven Weapon Expertise (Level 13)
> "Prerequisites: Elven Weapon Familiarity. When class grants expert+ proficiency in weapons, also gain that proficiency in longbows, composite longbows, longswords, rapiers, shortbows, composite shortbows, and all elf weapons trained."

Requirements identified:
- Same proficiency inheritance model as Dwarven Weapon Expertise.

---

## SECTION: Gnome Ancestry

### Gnome Stat Block
> Hit Points: 8 | Size: Small | Speed: 25 feet | Ability Boosts: Constitution, Charisma, Free | Ability Flaw: Strength | Languages: Common, Gnomish, Sylvan + bonus from Intelligence | Special: Low-Light Vision

Requirements identified:
- **Gnome** ancestry data:
  - `hp`: 8
  - `size`: Small
  - `speed`: 25 ft
  - `ability_boosts`: [Constitution, Charisma, (free)]
  - `ability_flaw`: Strength
  - `languages`: [Common, Gnomish, Sylvan]
  - `bonus_language_options`: [Draconic, Dwarven, Elven, Goblin, Jotun, Orcish]
  - `traits`: [Gnome, Humanoid]
  - `special_abilities`: [Low-Light Vision]
- Small size has gameplay implications (equipment, reach, space — handled in later chapters).

---

## SECTION: Gnome Heritages

### Heritage — Chameleon Gnome
> "You can slowly change the vibrancy and exact color of your hair and skin... When in an area where your coloration is roughly similar to the environment, you can use a single action to blend in, granting a +2 circumstance bonus to Stealth checks until surroundings shift."

Requirements identified:
- Active one-action ability: minor color shift.
- Passive slow shift: up to 1 hour for full-body color change.
- Conditional Stealth bonus: +2 circumstance to Stealth when coloration matches environment.
- Bonus expires when environment changes significantly — system needs a trigger/expiry condition.

---

### Heritage — Fey-touched Gnome
> "You gain the fey trait, in addition to gnome and humanoid traits. Choose one cantrip from the primal spell list. You can cast this spell as a primal innate spell at will, heightened to half level. You can change this cantrip once per day via a 10-minute concentrate activity."

Requirements identified:
- Character gains additional trait: **Fey** (alongside Gnome, Humanoid).
- A character may have multiple traits simultaneously.
- Innate cantrip: primal, at-will, choice from primal list, stored on character.
- Cantrip swap: once/day, 10-minute activity with the **concentrate** trait.
- Activities have a trait field (here: concentrate).

---

### Heritage — Sensate Gnome
> "You gain imprecise scent with a range of 30 feet... +2 circumstance bonus to Perception checks to locate undetected creatures within scent range. GM may double range downwind or halve it upwind."

Requirements identified:
- Grants a special sense: **imprecise scent**, range 30 ft.
- Sense types: precise (pinpoints exact location), imprecise (determines approximate location).
- Wind direction modifies scent range: downwind = ×2, upwind = ÷2.
- +2 circumstance bonus to Perception to locate undetected creatures within scent range.

---

### Heritage — Umbral Gnome
> "You can see in complete darkness. You gain darkvision."

Requirements identified:
- Grants Darkvision (same as Cavern Elf).

---

### Heritage — Wellspring Gnome
> "Choose arcane, divine, or occult. You gain one cantrip from that tradition's spell list, castable as an innate spell at will. Whenever you gain a primal innate spell from a gnome ancestry feat, its tradition changes to your chosen tradition."

Requirements identified:
- Heritage stores a chosen spell tradition (arcane, divine, or occult) — one-time selection.
- Tradition override rule: any primal innate spells from gnome ancestry feats are reclassified to the chosen tradition.
- System must support per-character tradition overrides on innate spells.

---

## SECTION: Gnome Ancestry Feats

### Feat — Animal Accomplice (Level 1)
> "You gain a familiar using the rules on page 217. The type of animal is up to you."

Requirements identified:
- Familiar is a companion entity associated with the character; defined on page 217 (Chapter 4 rules).
- Character can own a familiar — familiar is its own entity with stats, abilities, type.

---

### Feat — Burrow Elocutionist (Level 1)
> "You can ask questions of, receive answers from, and use the Diplomacy skill with animals that have a burrow Speed."

Requirements identified:
- Ability to communicate with animals of a specific movement type (burrow Speed).
- Creature movement types include: walk, fly, burrow, swim, climb — must be typed fields.
- Diplomacy skill can be used on non-intelligent creatures with this feat.

---

### Feat — Fey Fellowship (Level 1)
> "+2 circumstance bonus to Perception checks and saving throws against fey. When meeting a fey in social situations, may immediately attempt Diplomacy to Make an Impression at −5 penalty (no 1-minute requirement). On failure, may re-attempt after 1 minute."

Requirements identified:
- Creature type tag: **Fey** — used for bonus targeting.
- Make an Impression normally requires 1 minute; this feat allows an immediate roll at −5.
- Re-attempt on failure: must wait the standard 1 minute then roll again without the failure result persisting.
- Special interaction with Glad-Hand feat: negates the −5 penalty vs. fey.

---

### Feat — First World Magic (Level 1)
> "Choose one cantrip from the primal spell list. Cast as primal innate spell at will, heightened to half level."

Requirements identified:
- Same innate cantrip model as Otherworldly Magic (Elf) but primal tradition.

---

### Feat — Gnome Obsession (Level 1)
> "Pick a Lore skill. Gain trained now, expert at 2nd level, master at 7th level, legendary at 15th level. Same progression applies to the Lore from your background."

Requirements identified:
- Feat creates a **level-gated automatic proficiency progression** for a specific lore skill.
- Progression: trained (1st) → expert (2nd) → master (7th) → legendary (15th).
- Applies identically to background-granted Lore skills as well.
- System must auto-apply proficiency upgrades at the specified levels.

---

### Feat — Gnome Weapon Familiarity (Level 1)
> "Trained with glaive and kukri. Access to uncommon gnome weapons. Martial gnome weapons = simple, advanced gnome weapons = martial for proficiency."

Requirements identified:
- Same weapon familiarity/downgrade model.
- Trained weapons: glaive, kukri.

---

### Feat — Illusion Sense (Level 1)
> "+1 circumstance bonus to Perception and Will saves against illusions. When within 10 feet of a disbelievable illusion, GM rolls secret disbelieve check automatically."

Requirements identified:
- Illusion is a spell school/descriptor tag.
- +1 circumstance bonus to Perception and Will saves vs. illusion-tagged effects.
- Automatic passive secret check: triggered when within 10 ft of any disbelievable illusion.
- Disbelieve is an interaction check — system must flag illusion spells as disbelievable.

---

### Feat — Animal Elocutionist (Level 5)
> "Prerequisites: Burrow Elocutionist. Can speak to all animals, not just burrowers. +1 circumstance bonus to Make an Impression on animals."

Requirements identified:
- Extends Burrow Elocutionist to all animals (no movement-type restriction).

---

### Feat — Energized Font (Level 5, One-Action)
> "Prerequisites: focus pool + at least one innate spell from gnome heritage/feat sharing tradition with a focus spell. Frequency: once per day. Regain 1 Focus Point."

Requirements identified:
- Character has a **focus pool** (track of Focus Points, max 3, restored per Focus spell rules).
- Feat refills 1 Focus Point, up to pool maximum, once per day.
- Prerequisite check: innate spell tradition must match at least one focus spell's tradition.

---

### Feat — Gnome Weapon Innovator (Level 5)
> "Prerequisites: Gnome Weapon Familiarity. Critically hitting with glaive, kukri, or gnome weapon applies critical specialization effect."

Requirements identified:
- Same critical specialization model as previous weapon familiarity feats.

---

### Feat — First World Adept (Level 9)
> "Prerequisites: at least one primal innate spell. Gain faerie fire and invisibility as 2nd-level primal innate spells, each 1/day."

Requirements identified:
- Grants two named innate spells: faerie fire, invisibility — both 2nd level, primal, 1/day each.
- Multiple innate spells can coexist on the same character, each with independent uses/day.

---

### Feat — Vivacious Conduit (Level 9)
> "If you rest for 10 minutes, regain HP equal to Constitution modifier × half your level. Cumulative with Treat Wounds healing."

Requirements identified:
- Short rest (10 minutes) triggers HP recovery = CON modifier × floor(level / 2).
- Stacks additively with Treat Wounds healing (no exclusion).

---

### Feat — Gnome Weapon Expertise (Level 13)
> "Prerequisites: Gnome Weapon Familiarity. Class-granted expert+ proficiency also applies to glaive, kukri, and all gnome weapons trained."

Requirements identified:
- Same proficiency inheritance model as previous weapon expertise feats.

---

## SECTION: Goblin Ancestry

### Goblin Stat Block
> Hit Points: 6 | Size: Small | Speed: 25 feet | Ability Boosts: Dexterity, Charisma, Free | Ability Flaw: Wisdom | Languages: Common, Goblin + bonus from Intelligence | Special: Darkvision

Requirements identified:
- **Goblin** ancestry data:
  - `hp`: 6
  - `size`: Small
  - `speed`: 25 ft
  - `ability_boosts`: [Dexterity, Charisma, (free)]
  - `ability_flaw`: Wisdom
  - `languages`: [Common, Goblin]
  - `bonus_language_options`: [Draconic, Dwarven, Gnoll, Gnomish, Halfling, Orcish]
  - `traits`: [Goblin, Humanoid]
  - `special_abilities`: [Darkvision]

---

## SECTION: Goblin Heritages

### Heritage — Charhide Goblin
> "You gain fire resistance equal to half your level (minimum 1). Your flat check to remove persistent fire damage is DC 10 instead of DC 15 (reduced to DC 5 if aided)."

Requirements identified:
- Fire resistance = floor(level / 2), minimum 1.
- **Persistent damage** has a flat check to remove it each round; standard DC is 15.
- Charhide: flat check DC drops to 10 (or 5 with appropriate aid).
- System must model persistent damage as a recurring damage-over-time with a per-round flat check to clear.
- Aid mechanic: another creature's appropriate action can further modify the check DC.

---

### Heritage — Irongut Goblin
> "+2 circumstance bonus to saves against afflictions, against gaining the sickened condition, and to remove sickened. Success on Fortitude save (affected by this bonus) becomes critical success. These benefits apply only when affliction/condition resulted from something ingested."

Requirements identified:
- Condition-scoped bonus: only when the source was **ingested** — afflictions/conditions must track their source type (ingested, inhaled, contact, injury, etc.).
- Success → critical success upgrade on Fortitude saves when bonus applies.
- Passive survival ability: can subsist on spoiled food; can eat/drink while sickened.
- System must flag whether a character can eat/drink while sickened (normally prohibited).

---

### Heritage — Razortooth Goblin
> "You gain a jaws unarmed attack that deals 1d6 piercing damage. Brawling group, finesse and unarmed traits."

Requirements identified:
- Unarmed attack as a heritage-granted weapon: jaws.
- Unarmed attack stats: damage = 1d6 piercing, group = brawling, traits = [finesse, unarmed].
- Characters can have multiple natural/unarmed attack options.

---

### Heritage — Snow Goblin
> "You gain cold resistance equal to half your level (minimum 1). Treat environmental cold effects as one step less extreme."

Requirements identified:
- Same resistance/severity-step model as Arctic Elf and Forge Dwarf but for cold.

---

### Heritage — Unbreakable Goblin
> "You gain 10 Hit Points from your ancestry instead of 6. When you fall, reduce falling damage as though you had fallen half the distance."

Requirements identified:
- Heritage overrides the ancestry HP value (10 instead of 6).
- Falling damage is calculated from distance fallen; this heritage halves effective distance for damage calculation.
- System must allow heritage to override ancestry base HP.

---

## SECTION: Goblin Ancestry Feats

### Feat — Burn It! (Level 1)
> "Spells and alchemical items dealing fire damage gain a status bonus to damage equal to half the spell's level or one-quarter the item's level (minimum 1). +1 status bonus to persistent fire damage dealt."

Requirements identified:
- Status bonus to fire damage: separate bonus type from circumstance/item/untyped (status bonuses don't stack with other status bonuses).
- Scaling: spell fire damage bonus = floor(spell_level / 2); alchemical item fire bonus = floor(item_level / 4), minimum 1.
- Persistent fire damage also gets a flat +1 status bonus.
- Bonus applies only to fire damage type.

---

### Feat — City Scavenger (Level 1)
> "+1 circumstance bonus to Subsist checks. Can use Society or Survival to Subsist in settlements. While Subsisting in a city, can Earn Income with Society or Survival simultaneously without extra downtime days. Special: Irongut goblin increases bonuses to +2."

Requirements identified:
- Subsist normally uses Survival; this feat adds Society as an alternative skill.
- Activity combination: Subsist + Earn Income can occur in the same downtime day in cities.
- Conditional bonus upgrade based on another heritage: system must check heritage when applying feat bonuses.

---

### Feat — Goblin Lore (Level 1)
> "Trained in Nature and Stealth. Also trained in Goblin Lore. Duplicate training converts to a free skill choice."

Requirements identified:
- Same duplicate-training resolution rule.

---

### Feat — Goblin Scuttle (Level 1, Reaction)
> "Trigger: An ally ends a move action adjacent to you. You Step."

Requirements identified:
- Reaction trigger: ally ends a move action adjacent to acting character.
- Effect: character takes a Step (5 ft movement, doesn't trigger reactions).

---

### Feat — Goblin Song (Level 1, One-Action)
> "Attempt Performance vs. Will DC of up to 1 target within 30 ft (scales with proficiency: 2 targets at expert, 4 at master, 8 at legendary). Critical success: −1 status to Perception/Will for 1 minute. Success: −1 status for 1 round. Critical failure: target immune for 1 hour."

Requirements identified:
- Performance check used offensively against a creature's Will DC.
- Target count scales with Performance proficiency rank.
- Effect is a status penalty to Perception checks and Will saves.
- Temporary immunity mechanic: on critical failure, target is immune to this specific ability for 1 hour.

---

### Feat — Goblin Weapon Familiarity (Level 1)
> "Trained with dogslicer and horsechopper. Access to uncommon goblin weapons. Same downgrade rule."

Requirements identified:
- Trained weapons: dogslicer, horsechopper.

---

### Feat — Junk Tinker (Level 1)
> "Can Craft level 0 items (including weapons, not armor) from junk at one-quarter cost, producing shoddy items. Character does not take the shoddy item penalty for items they made. Can add junk during any Craft to gain a cost reduction equivalent to one additional day of work."

Requirements identified:
- Level 0 items craftable from junk at 25% price.
- **Shoddy** item quality: normally imposes a penalty; this feat waives that penalty for self-made items.
- Item quality field: normal, shoddy, masterwork/high-quality.
- Junk reduction: equivalent to 1 extra day of crafting work → reduces final price.

---

### Feat — Rough Rider (Level 1)
> "Gain the Ride feat even without prerequisites. +1 circumstance to Nature checks to Command an Animal on a goblin dog or wolf mount. Can always select a wolf as an animal companion."

Requirements identified:
- Feat can grant another feat (Ride) regardless of prerequisites — feat grants can bypass normal prerequisites.
- Mounts are typed: goblin dog, wolf are specific mount types.
- Animal companion selection can be gated by feat (wolf normally unavailable to some classes unless this feat is held).

---

### Feat — Very Sneaky (Level 1)
> "Move 5 feet farther on a Sneak action (up to Speed). While continuing to Sneak successfully, don't become observed if not covered/concealed at end of Sneak action, as long as you have cover/concealment at end of turn."

Requirements identified:
- Sneak action normally allows movement up to half Speed (see Stealth rules); this adds 5 ft.
- Observation state during Sneak: normally become observed if ending without cover/concealment; this feat delays that to end of full turn.

---

### Feat — Goblin Weapon Frenzy (Level 5)
> "Prerequisites: Goblin Weapon Familiarity. Critical hits with goblin weapons apply critical specialization effect."

Requirements identified:
- Same critical specialization model.

---

### Feat — Cave Climber (Level 9)
> "You gain a climb Speed of 10 feet."

Requirements identified:
- Grants a new **movement type**: climb Speed = 10 ft.
- Characters may have multiple movement speeds (land, climb, swim, fly, burrow) — each is a separate field.

---

### Feat — Skittering Scuttle (Level 9)
> "Prerequisites: Goblin Scuttle. When using Goblin Scuttle, may Stride up to half your Speed instead of Stepping."

Requirements identified:
- Upgrades the Goblin Scuttle reaction: Stride (up to half Speed) replaces Step.

---

### Feat — Goblin Weapon Expertise (Level 13)
> "Prerequisites: Goblin Weapon Familiarity. Class-granted expert+ proficiency also applies to dogslicer, horsechopper, and all goblin weapons trained."

Requirements identified:
- Same proficiency inheritance model.

---

### Feat — Very, Very Sneaky (Level 13)
> "Prerequisites: Very Sneaky. Move up to full Speed on Sneak. No longer need cover or concealment to Hide or Sneak."

Requirements identified:
- Upgrades Very Sneaky: Sneak movement = full Speed.
- Removes cover/concealment requirement for Hide and Sneak actions entirely.

---

## SECTION: Halfling Ancestry

### Halfling Stat Block
> Hit Points: 6 | Size: Small | Speed: 25 feet | Ability Boosts: Dexterity, Wisdom, Free | Ability Flaw: Strength | Languages: Common, Halfling + bonus from Intelligence | Special: Keen Eyes

Requirements identified:
- **Halfling** ancestry data:
  - `hp`: 6
  - `size`: Small
  - `speed`: 25 ft
  - `ability_boosts`: [Dexterity, Wisdom, (free)]
  - `ability_flaw`: Strength
  - `languages`: [Common, Halfling]
  - `bonus_language_options`: [Dwarven, Elven, Gnomish, Goblin]
  - `traits`: [Halfling, Humanoid]
  - `special_abilities`: [Keen Eyes]

---

### Special Ability — Keen Eyes
> "+2 circumstance bonus when using Seek to find hidden or undetected creatures within 30 feet. When targeting a concealed opponent, flat check DC = 3 (normally 5). When targeting a hidden opponent, flat check DC = 9 (normally 11)."

Requirements identified:
- Flat checks exist for attacking concealed targets (standard DC 5) and hidden targets (standard DC 11).
- Keen Eyes reduces these to DC 3 (concealed) and DC 9 (hidden).
- Seek action bonus: +2 circumstance within 30 ft for hidden/undetected creatures.
- System must track flat check DCs for targeting obscured creatures as modifiable fields.

---

## SECTION: Halfling Heritages

### Heritage — Gutsy Halfling
> "When you roll a success on a saving throw against an emotion effect, you get a critical success instead."

Requirements identified:
- Success → critical success upgrade on emotion saves (same pattern as Forlorn elf feat).

---

### Heritage — Hillock Halfling
> "When you regain HP overnight, add your level to the HP regained. When treated with Medicine (Treat Wounds), eating a snack lets you add your level to HP regained."

Requirements identified:
- Overnight natural healing formula gains +level HP bonus.
- Treat Wounds healing gains +level HP when character consumes a snack simultaneously.
- Snack is an implicit item/food interaction; system needs a "snack" or "ration" item type.

---

### Heritage — Nomadic Halfling
> "You gain two additional languages of your choice (common or uncommon). Every time you take the Multilingual feat, you gain one extra language."

Requirements identified:
- Grants 2 bonus languages at creation beyond the standard Intelligence-based bonus.
- Multilingual feat interaction: each Multilingual selection grants 1 extra language on top of Multilingual's normal grant.

---

### Heritage — Twilight Halfling
> "You gain low-light vision."

Requirements identified:
- Grants Low-Light Vision.

---

### Heritage — Wildwood Halfling
> "You ignore difficult terrain from trees, foliage, and undergrowth."

Requirements identified:
- Difficult terrain has subtypes/tags: trees, foliage, undergrowth.
- This heritage makes specific terrain tags non-difficult for this character.

---

## SECTION: Halfling Ancestry Feats

### Feat — Distracting Shadows (Level 1)
> "You can use creatures at least one size larger than you as cover for Hide and Sneak (but not Take Cover). Typically Medium or larger creatures."

Requirements identified:
- Cover for Hide/Sneak can come from creatures, not just objects/terrain, with this feat.
- Cover source types: obstacle/terrain vs. creature — must be distinguishable.
- Size comparison: cover-granting creature must be ≥1 size category larger than the character.

---

### Feat — Halfling Lore (Level 1)
> "Trained in Acrobatics and Stealth, plus Halfling Lore. Duplicate training rule applies."

Requirements identified:
- Same duplicate-training resolution rule.

---

### Feat — Halfling Luck (Level 1, Free-Action)
> "Frequency: once per day. Trigger: you fail a skill check or saving throw. Reroll the triggering check; must use the new result even if worse."

Requirements identified:
- Free-action reaction triggered on failed skill check or saving throw.
- **Reroll mechanic**: roll again and use the new result (not a "keep better" — uses new result regardless).
- Reroll must track which check it's being applied to.

---

### Feat — Halfling Weapon Familiarity (Level 1)
> "Trained with sling, halfling sling staff, and shortsword. Access to uncommon halfling weapons. Same downgrade rule."

Requirements identified:
- Trained weapons: sling, halfling sling staff, shortsword.

---

### Feat — Sure Feet (Level 1)
> "Success on Acrobatics (Balance) or Athletics (Climb) becomes critical success. Not flat-footed when attempting Balance or Climb."

Requirements identified:
- Degree-of-success upgrade on Balance and Climb checks.
- Removes flat-footed condition during Balance and Climb attempts.

---

### Feat — Titan Slinger (Level 1)
> "When hitting on an attack with a sling against a Large or larger creature, increase the weapon damage die by one step."

Requirements identified:
- Conditional damage die upgrade: triggered by target size (Large or larger) AND weapon type (sling).
- Die step increase: d4→d6→d8→d10→d12 (see weapon rules, p.279).

---

### Feat — Unfettered Halfling (Level 1)
> "Success on Escape check or save against grabbed/restrained becomes critical success. Failure on an enemy's Grapple check becomes critical failure. If a creature uses Grab, it must make an Athletics check (no auto-grab)."

Requirements identified:
- Success → critical success on Escape checks and saves vs. grabbed/restrained conditions.
- Enemy Grapple check: failure → critical failure when targeting this character.
- Grab ability (automatic on hit) becomes a manual Athletics check when targeting this character.
- Significant interaction with grapple/grab mechanics — system must support per-character modifiers to these outcomes.

---

### Feat — Watchful Halfling (Level 1)
> "+2 circumstance bonus to Sense Motive Perception checks to detect enchanted/possessed characters. Passive secret check (−2, no circumstance bonus) if not actively Sensing Motive. Can use Aid action to help creature resist enchantment/possession."

Requirements identified:
- Sense Motive: a Perception-based action to detect enchantment or possession.
- Enchanted and possessed are character states the system must track.
- Passive secret check pattern (same as Stonecunning, Illusion Sense).
- Aid action normally applies to skill checks and attacks; this feat extends it to saving throws against enchantment/possession.

---

### Feat — Cultural Adaptability (Level 5)
> "Gain the Adopted Ancestry general feat, plus one 1st-level ancestry feat from the chosen ancestry."

Requirements identified:
- **Adopted Ancestry**: a general feat that grants access to another ancestry's feats.
- This feat bundles Adopted Ancestry + one 1st-level feat from that ancestry.
- System must support a character having feats from multiple ancestries.

---

### Feat — Halfling Weapon Trickster (Level 5)
> "Prerequisites: Halfling Weapon Familiarity. Critical hit with shortsword, sling, or halfling weapon applies critical specialization effect."

Requirements identified:
- Same critical specialization model.

---

### Feat — Guiding Luck (Level 9)
> "Prerequisites: Halfling Luck. Can use Halfling Luck twice per day: once on normal trigger (failed skill/save), once on a failed Perception check or attack roll."

Requirements identified:
- Extends Halfling Luck frequency to 2/day.
- Second use has a different trigger: failed Perception check or attack roll.
- System must track per-ability daily uses separately.

---

### Feat — Irrepressible (Level 9)
> "When you roll a success on a save against an emotion effect, get a critical success. If heritage is Gutsy Halfling, critical failure on emotion save becomes failure instead."

Requirements identified:
- General emotion save upgrade (duplicates Gutsy Halfling success upgrade for non-Gutsy characters).
- Gutsy Halfling bonus interaction: critical failure → failure on emotion saves (additive benefit).
- System must check heritage when computing feat benefit.

---

### Feat — Ceaseless Shadows (Level 13)
> "Prerequisites: Distracting Shadows. No longer need cover or concealment to Hide or Sneak. Cover from creatures upgrades: lesser cover → cover, cover → greater cover."

Requirements identified:
- Removes cover/concealment requirement for Hide and Sneak entirely.
- Cover tier from creatures is upgraded by one step when this feat is held.
- Cover tiers: lesser cover, cover, greater cover — ordered scale.

---

### Feat — Halfling Weapon Expertise (Level 13)
> "Prerequisites: Halfling Weapon Familiarity. Class-granted expert+ proficiency also applies to sling, halfling sling staff, shortsword, and all halfling weapons trained."

Requirements identified:
- Same proficiency inheritance model.

---

## SECTION: Human Ancestry

### Human Stat Block
> Hit Points: 8 | Size: Medium | Speed: 25 feet | Ability Boosts: Two free ability boosts | Ability Flaw: None | Languages: Common + 1 + Intelligence modifier bonus | Traits: Human, Humanoid

Requirements identified:
- **Human** ancestry data:
  - `hp`: 8
  - `size`: Medium
  - `speed`: 25 ft
  - `ability_boosts`: [(free), (free)] — both are free choices (no fixed boosts)
  - `ability_flaw`: none (humans have no ability flaw)
  - `languages`: [Common]
  - `bonus_language_count`: 1 + Intelligence modifier (if positive) — humans get more bonus languages than other ancestries
  - `traits`: [Human, Humanoid]
  - `special_abilities`: none

---

### Paragraph — Two Free Ability Boosts
> "Humans receive two free ability boosts, unlike most ancestries that receive two fixed boosts plus one free."

Requirements identified:
- Human ability boost model differs from all others: both boosts are free (player's choice of any score, each to a different score).
- System must support the case where an ancestry has 0 fixed boosts and N free boosts.

---

### Paragraph — Language Count Exception
> "Additional languages equal to 1 + your Intelligence modifier (if positive). Choose from the list of common languages and any other languages to which you have access."

Requirements identified:
- Human bonus language formula: 1 + Intelligence modifier (vs. other ancestries: just Intelligence modifier).
- Humans can choose from all common languages (not a restricted sub-list like other ancestries).

---

## SECTION: Human Heritages

### Heritage — Half-Elf
> "You gain the elf trait, the half-elf trait, and low-light vision. You can select elf, half-elf, and human feats whenever you gain an ancestry feat."

Requirements identified:
- Heritage can grant additional traits: Elf, Half-Elf added to Human, Humanoid.
- Heritage expands ancestry feat pool: can select from Elf, Half-Elf, and Human feats.
- Character's accessible ancestry feat pool is determined by heritage, not just ancestry.

---

### Heritage — Half-Orc
> "You gain the orc trait, the half-orc trait, and low-light vision. You can select orc, half-orc, and human feats whenever you gain an ancestry feat."

Requirements identified:
- Same multi-ancestry feat pool model as Half-Elf.
- Traits added: Orc, Half-Orc.

---

### Heritage — Skilled Heritage
> "You become trained in one skill of your choice. At 5th level, you become an expert in the chosen skill."

Requirements identified:
- Heritage grants trained proficiency in a chosen skill at 1st level.
- Automatic expert upgrade at 5th level — system must apply this on level-up.

---

### Heritage — Versatile Heritage
> "Select a general feat of your choice for which you meet the prerequisites."

Requirements identified:
- Heritage grants a free general feat at character creation.
- General feats are a distinct feat category (not ancestry, class, or skill feats).

---

## SECTION: Human Ancestry Feats

### Feat — Adapted Cantrip (Level 1)
> "Prerequisites: spellcasting class feature. Choose one cantrip from a tradition other than your own. Add it to your spell repertoire/spellbook/prepared spells. Cast as your class's tradition."

Requirements identified:
- Feat has a class-feature prerequisite: requires a spellcasting class feature.
- Allows adding one cantrip from a different tradition; it is cast using the character's own tradition.
- Cross-tradition cantrip swap on retrain is supported.

---

### Feat — Cooperative Nature (Level 1)
> "+4 circumstance bonus on checks to Aid."

Requirements identified:
- Aid check bonus: +4 circumstance.

---

### Feat — General Training (Level 1)
> "You gain a 1st-level general feat. Must meet prerequisites (can defer selection to later in character creation). Can be selected multiple times for a different feat each time."

Requirements identified:
- Feat can be taken multiple times — system must support multi-select feats (not unique).
- Grants a general feat with deferred prerequisite checking during character creation.

---

### Feat — Haughty Obstinacy (Level 1)
> "Success on save vs. mental control effect becomes critical success. Enemy failure on Coerce check becomes critical failure (blocks re-attempt for 1 week)."

Requirements identified:
- "Mental control" is a tagged effect subtype.
- Coerce (Intimidation) failure → critical failure when targeting this character.
- Coerce critical failure imposes a 1-week immunity to further Coerce attempts by same creature.

---

### Feat — Natural Ambition (Level 1)
> "You gain a 1st-level class feat for your class. Must meet prerequisites (can defer selection)."

Requirements identified:
- Grants one 1st-level class feat (not ancestry/general) from the character's own class.
- Class feats are a distinct feat category.

---

### Feat — Natural Skill (Level 1)
> "You gain trained proficiency in two skills of your choice."

Requirements identified:
- Grants 2 free skill training choices at character creation.

---

### Feat — Unconventional Weaponry (Level 1)
> "Choose an uncommon simple or martial weapon with an ancestry trait (e.g., dwarf, goblin, orc) or common in another culture. Gain access; weapon counts as simple for proficiency. If trained in all martial weapons, may choose an uncommon advanced weapon with such a trait (counts as martial for proficiency)."

Requirements identified:
- Access gate for uncommon weapons can be opened by this feat.
- Weapon proficiency reclassification for this single weapon: downgraded to simple (or martial if advanced).
- Requires matching an ancestry trait tag or cultural designation.

---

### Feat — Adaptive Adept (Level 5)
> "Prerequisites: Adapted Cantrip, can cast 3rd-level spells. Choose a cantrip or 1st-level spell from same tradition as Adapted Cantrip. Added to spell list; cast as class tradition. 1st-level spell: no access to heightened versions."

Requirements identified:
- Can extend a cross-tradition spell to 1st-level (not just cantrip).
- 1st-level spells added this way cannot be heightened beyond 1st level via this path.
- System must track the tradition source of each cross-tradition spell.

---

### Feat — Clever Improviser (Level 5)
> "Gain Untrained Improvisation general feat. Can attempt skill actions requiring training even while untrained."

Requirements identified:
- Removes the trained-proficiency gate on skill actions for this character.
- Untrained Improvisation is a named general feat bundled by this feat.

---

### Feat — Cooperative Soul (Level 9)
> "Prerequisites: Cooperative Nature, expert in the skill. Any result on Aid check other than critical success is treated as success."

Requirements identified:
- Upgrades Aid check outcomes: any non-critical-success result becomes success when character is expert+ in the skill.

---

### Feat — Incredible Improvisation (Level 9, Free-Action)
> "Prerequisites: Clever Improviser. Frequency: once per day. Trigger: attempt a check with an untrained skill. Gain +4 circumstance bonus."

Requirements identified:
- Free-action triggered on attempting an untrained skill check.
- +4 circumstance bonus, 1/day.

---

### Feat — Multitalented (Level 9)
> "Gain a 2nd-level multiclass dedication feat even if you haven't fulfilled the normal archetype feat requirement. Half-elf: no ability score prerequisite for this feat."

Requirements identified:
- Multiclass dedication feats have a prerequisite: normally must take more feats in current archetype first.
- This feat bypasses that gate.
- Half-elf modifier: waives ability score prerequisites specifically for this feat.

---

### Feat — Unconventional Expertise (Level 13)
> "Prerequisites: Unconventional Weaponry, trained in chosen weapon. Class-granted expert+ proficiency also applies to the unconventional weapon chosen."

Requirements identified:
- Same proficiency inheritance model; applies to the specific weapon chosen in Unconventional Weaponry.

---

## SECTION: Half-Elf Ancestry Feats

### Feat — Elf Atavism (Level 1)
> "Gain the benefits of one elf heritage. Cannot retrain into or out of this feat. Only at 1st level. Cannot select a heritage that improves a feature you lack (e.g., can't take cavern elf's darkvision if you don't have low-light vision)."

Requirements identified:
- Prerequisite: heritage compatibility check — feat grants may be conditional on the character already having prerequisite features.
- Special restriction: immutable (no retrain).

---

### Feat — Inspire Imitation (Level 5)
> "When you critically succeed on a skill check, you automatically qualify to use the Aid reaction for the same skill without spending a preparation action."

Requirements identified:
- Aid reaction normally requires a preparation action on a prior turn.
- This feat waives the preparation requirement on critical skill check success.

---

### Feat — Supernatural Charm (Level 5)
> "Can cast 1st-level charm as an arcane innate spell once per day."

Requirements identified:
- Innate spell: charm, arcane, 1st level, 1/day.

---

## SECTION: Half-Orc Ancestry Feats

### Feat — Monstrous Peacemaker (Level 1)
> "+1 circumstance bonus to Diplomacy vs. non-humanoid intelligent creatures and humanoids marginalized in human society (giants, goblins, kobolds, orcs, etc.). Same bonus to Perception for Sense Motive against such creatures."

Requirements identified:
- Bonus targeting by creature type AND social status (marginalized humanoids).
- GM-discretion flag on target eligibility — system stores the list, GM overrides possible.

---

### Feat — Orc Ferocity (Level 1, Reaction)
> "Frequency: once per day. Trigger: would be reduced to 0 HP but not immediately killed. Remain at 1 HP; wounded condition increases by 1."

Requirements identified:
- **0 HP trigger**: fires before falling unconscious.
- Effect: character stays at 1 HP instead of dropping.
- **Wounded condition** increases by 1 (wounded is a numeric tracked condition).
- System must support this "cheat death" trigger as a distinct reactive rule.

---

### Feat — Orc Sight (Level 1)
> "Prerequisites: low-light vision. Gain darkvision."

Requirements identified:
- Prerequisite: character must have low-light vision (from Half-Orc heritage).
- Upgrades vision: low-light vision → darkvision.

---

### Feat — Orc Superstition (Level 1, Reaction)
> "Trigger: attempt saving throw vs. spell or magical effect, before rolling. Gain +1 circumstance bonus to that saving throw (concentrate trait)."

Requirements identified:
- Same pre-roll save bonus pattern as Ancient-Blooded Dwarf.
- This feat has the concentrate trait — using it counts as a concentrate action (can be disrupted).

---

### Feat — Orc Weapon Familiarity (Level 1)
> "Trained with falchion and greataxe. Access to uncommon orc weapons. Same downgrade rule."

Requirements identified:
- Trained weapons: falchion, greataxe.

---

### Feat — Orc Weapon Carnage (Level 5)
> "Prerequisites: Orc Weapon Familiarity. Critical hits with falchion, greataxe, or orc weapon apply critical specialization effect."

Requirements identified:
- Same critical specialization model.

---

### Feat — Victorious Vigor (Level 5, Reaction)
> "Trigger: bring a foe to 0 HP. Gain temporary HP equal to Constitution modifier until end of next turn."

Requirements identified:
- **Temporary HP**: a separate HP pool that absorbs damage before regular HP, expires on trigger.
- Temporary HP amount = CON modifier.
- Trigger: enemy drops to 0 HP (caused by this character).
- Duration: until end of next turn.

---

### Feat — Pervasive Superstition (Level 9)
> "Prerequisites: Orc Superstition. +1 circumstance bonus to saves against spells and magical effects at all times."

Requirements identified:
- Passive always-on bonus (no trigger needed) to all saves vs. spells and magical effects.
- Stacks with Orc Superstition (which is reactive and circumstance).

---

### Feat — Incredible Ferocity (Level 13)
> "Prerequisites: Orc Ferocity. Orc Ferocity can now be used once per hour instead of once per day."

Requirements identified:
- Upgrades Orc Ferocity frequency: 1/day → 1/hour.
- System must support both daily and hourly reset windows for ability tracking.

---

### Feat — Orc Weapon Expertise (Level 13)
> "Prerequisites: Orc Weapon Familiarity. Class-granted expert+ proficiency also applies to falchion, greataxe, and all orc weapons trained."

Requirements identified:
- Same proficiency inheritance model.

---

## SECTION: Backgrounds

### Paragraph — Background Overview
> "Each background grants two ability boosts, a skill feat, and the trained proficiency rank in two skills, one of which is a Lore skill. If your background and class both grant training in the same skill, become trained in another skill of your choice. This decision is permanent."

Requirements identified:
- Background data model must include:
  - `ability_boosts`: two boosts (one may be fixed to a pair of choices, one free)
  - `skill_feat`: exactly one skill feat granted
  - `trained_skills`: exactly two skills, one of which must be a Lore skill
- Background is immutable after 1st level selection.
- Duplicate training resolution: same rule as ancestry/class duplicates.
- Lore skills may involve a player choice (e.g., terrain type) subject to GM approval.

---

### Background Catalog (All backgrounds — stat summary)

Each entry below captures the mechanical grants. Flavor text omitted.

| Background | Ability Boost Options | Skills Trained | Skill Feat |
|---|---|---|---|
| Acolyte | Intelligence or Wisdom + free | Religion, Scribing Lore | Student of the Canon |
| Acrobat | Strength or Dexterity + free | Acrobatics, Circus Lore | Steady Balance |
| Animal Whisperer | Wisdom or Charisma + free | Nature, Terrain Lore (choice) | Train Animal |
| Artisan | Strength or Intelligence + free | Crafting, Guild Lore | Specialty Crafting |
| Artist | Dexterity or Charisma + free | Crafting, Art Lore | Specialty Crafting |
| Barkeep | Constitution or Charisma + free | Diplomacy, Alcohol Lore | Hobnobber |
| Barrister | Intelligence or Charisma + free | Diplomacy, Legal Lore | Group Impression |
| Bounty Hunter | Strength or Wisdom + free | Survival, Legal Lore | Experienced Tracker |
| Charlatan | Intelligence or Charisma + free | Deception, Underworld Lore | Charming Liar |
| Criminal | Dexterity or Intelligence + free | Stealth, Underworld Lore | Experienced Smuggler |
| Detective | Intelligence or Wisdom + free | Society, Underworld Lore | Streetwise |
| Emissary | Intelligence or Charisma + free | Society, City Lore (choice) | Multilingual |
| Entertainer | Dexterity or Charisma + free | Performance, Theater Lore | Fascinating Performance |
| Farmhand | Constitution or Wisdom + free | Athletics, Farming Lore | Assurance (Athletics) |
| Field Medic | Constitution or Wisdom + free | Medicine, Warfare Lore | Battle Medicine |
| Fortune Teller | Intelligence or Charisma + free | Occultism, Fortune-Telling Lore | Oddity Identification |
| Gambler | Dexterity or Charisma + free | Deception, Games Lore | Lie to Me |
| Gladiator | Strength or Charisma + free | Performance, Gladiatorial Lore | Impressive Performance |
| Guard | Strength or Charisma + free | Intimidation, Legal Lore or Warfare Lore | Quick Coercion |
| Herbalist | Constitution or Wisdom + free | Nature, Herbalism Lore | Natural Medicine |
| Hermit | Constitution or Intelligence + free | Nature or Occultism, Terrain Lore (choice) | Dubious Knowledge |
| Hunter | Dexterity or Wisdom + free | Survival, Tanning Lore | Survey Wildlife |
| Laborer | Strength or Constitution + free | Athletics, Labor Lore | Hefty Hauler |
| Martial Disciple | Strength or Dexterity + free | Acrobatics or Athletics (choice), Warfare Lore | Cat Fall (Acrobatics) or Quick Jump (Athletics) |
| Merchant | Intelligence or Charisma + free | Diplomacy, Mercantile Lore | Bargain Hunter |
| Miner | Strength or Wisdom + free | Survival, Mining Lore | Terrain Expertise (underground) |
| Noble | Intelligence or Charisma + free | Society, Genealogy Lore or Heraldry Lore | Courtly Graces |
| Nomad | Constitution or Wisdom + free | Survival, Terrain Lore (choice) | Assurance (Survival) |
| Prisoner | Strength or Constitution + free | Stealth, Underworld Lore | Experienced Smuggler |
| Sailor | Strength or Dexterity + free | Athletics, Sailing Lore | Underwater Marauder |
| Scholar | Intelligence or Wisdom + free | Arcana/Nature/Occultism/Religion (choice), Academia Lore | Assurance (chosen skill) |
| Scout | Dexterity or Wisdom + free | Survival, Terrain Lore (choice) | Forager |
| Street Urchin | Dexterity or Constitution + free | Thievery, City Lore (choice) | Pickpocket |
| Tinker | Dexterity or Intelligence + free | Crafting, Engineering Lore | Specialty Crafting |
| Warrior | Strength or Constitution + free | Intimidation, Warfare Lore | Intimidating Glare |

Requirements identified:
- Background ability boost model: one boost fixed to a pair of ability scores (player picks one of the two), plus one free boost.
- Every background includes exactly one Lore skill.
- Some Lore skills are generic (Scribing Lore, Circus Lore); some require a player-defined choice (Terrain Lore, City Lore) with GM approval.
- Some backgrounds offer a skill choice (e.g., Hermit: Nature or Occultism) — a selection the player makes at character creation.
- Some backgrounds offer a skill feat that itself requires a choice (e.g., Assurance with a specific skill).

---

## SECTION: Languages

### Paragraph — Language Mechanics
> "Your ancestry entry states which languages you know at 1st level. You can both speak and read these languages. A positive Intelligence modifier grants additional languages equal to your modifier. If your Intelligence changes later, adjust your number of languages accordingly."

Requirements identified:
- Languages grant both **speak** and **read** ability by default (no distinction required for standard languages).
- Language count adjusts dynamically if Intelligence modifier changes.
- System must recalculate available bonus language slots when Intelligence is modified.

---

### Paragraph — Language Tiers
> "Common languages are regularly encountered in most places. Uncommon languages are most frequently spoken by native speakers or scholars. Druidic is a secret language available only to druids."

Requirements identified:
- Languages have a tier/access field: common, uncommon, secret.
- Secret languages (Druidic) are class-locked — only available to druids; druids are prohibited from teaching it.
- Uncommon languages require a specific access source (ancestry, feat, region, etc.).

---

### Table 2-1: Common Languages

| Language | Primary Speakers |
|---|---|
| Common (Taldane) | Humans, dwarves, elves, halflings, and other common ancestries |
| Draconic | Dragons, reptilian humanoids |
| Dwarven | Dwarves |
| Elven | Elves, half-elves |
| Gnomish | Gnomes |
| Goblin | Goblins, hobgoblins, bugbears |
| Halfling | Halflings |
| Jotun | Giants, ogres, trolls, ettins, cyclopes |
| Orcish | Orcs, half-orcs |
| Sylvan | Fey, centaurs, plant and fungus creatures |
| Undercommon | Drow, duergars, xulgaths |

Requirements identified:
- Language database must include all 11 common languages with speaker associations.
- Speaker associations are informational tags (not mechanically enforced at character creation, but used for lore and NPC interactions).

---

### Table 2-2: Uncommon Languages

| Language | Primary Speakers |
|---|---|
| Abyssal | Demons |
| Aklo | Deros, evil fey, otherworldly monsters |
| Aquan | Aquatic creatures, water elemental creatures |
| Auran | Air elemental creatures, flying creatures |
| Celestial | Angels |
| Gnoll | Gnolls |
| Ignan | Fire elemental creatures |
| Infernal | Devils |
| Necril | Ghouls, intelligent undead |
| Shadowtongue | Nidalese, Shadow Plane creatures |
| Terran | Earth elemental creatures |

Requirements identified:
- 11 uncommon languages must be in the language database.
- Access to uncommon languages requires a specific source (ancestry bonus list, feat, region, etc.).

---

### Table 2-3: Secret Language

| Language | Speakers |
|---|---|
| Druidic | Druids only |

Requirements identified:
- Druidic is class-locked (requires druid class feature) and cannot be taught/shared.
- System must enforce the class prerequisite for Druidic.

---

### Paragraph — Sign Language and Lip Reading
> "You can learn signed languages associated with the languages you know, or how to read lips, by taking the Sign Language or Read Lips skill feats."

Requirements identified:
- Sign Language and Read Lips are distinct communication modalities — separate from spoken/written.
- Accessible via skill feats.
- Characters who are deaf/mute may receive these feats for free at GM discretion.

---

*End of Chapter 2: Ancestries & Backgrounds*
