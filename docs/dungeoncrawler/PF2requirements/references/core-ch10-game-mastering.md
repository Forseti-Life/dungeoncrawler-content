# PF2E Core Rulebook — Chapter 10: Game Mastering
## Requirements Extraction
*(Pure GM narrative advice sections omitted; only mechanical rules, tables, and systems extracted.)*

---

## SECTION: Encounter Building

### Paragraph — Encounter Threat Levels

> Encounter threat categories range from Trivial to Extreme. XP budget and character adjustment are used to build encounters.

- REQ: Encounter threat tiers: **Trivial** (≤40 XP), **Low** (60 XP), **Moderate** (80 XP), **Severe** (120 XP), **Extreme** (160 XP).
- REQ: XP budget is baseline for 4 PCs. Adjust per additional or missing PC using the Character Adjustment value.

### Paragraph — TABLE 10–1: Encounter Budget

> TABLE 10–1: ENCOUNTER BUDGET
> Trivial: 40 XP / 10 adj | Low: 60 / 15 | Moderate: 80 / 20 | Severe: 120 / 30 | Extreme: 160 / 40

- REQ: Implement encounter budget system using the following table:

| Threat | XP Budget | Per-PC Adjustment |
|--------|-----------|-------------------|
| Trivial | 40 or less | 10 or less |
| Low | 60 | 15 |
| Moderate | 80 | 20 |
| Severe | 120 | 30 |
| Extreme | 160 | 40 |

### Paragraph — TABLE 10–2: Creature XP and Role

> Each creature costs XP based on its level relative to party level (–4 to +4).

- REQ: Creature XP cost based on level vs. party level:

| Creature Level | XP Cost | Suggested Role |
|----------------|---------|----------------|
| Party level – 4 | 10 | Low-threat lackey |
| Party level – 3 | 15 | Low/moderate-threat lackey |
| Party level – 2 | 20 | Any lackey or standard creature |
| Party level – 1 | 30 | Any standard creature |
| Party level | 40 | Standard or low-threat boss |
| Party level + 1 | 60 | Low/moderate-threat boss |
| Party level + 2 | 80 | Moderate/severe-threat boss |
| Party level + 3 | 120 | Severe/extreme-threat boss |
| Party level + 4 | 160 | Extreme-threat solo boss |

- REQ: Creatures more than 4 levels below party are trivial; those more than 4 above are too dangerous to use (XP values not defined).

### Paragraph — Party Level

> Party level is typically the shared level of all PCs. If levels differ, use highest level if only 1–2 characters are behind; use average if everyone differs. A character 2+ levels ahead counts as one additional PC per 2 extra levels.

- REQ: Party level determines encounter budget calculations. Support level-variance handling rules.
- REQ: PCs behind party level earn double XP until caught up.

---

## SECTION: Setting DCs

### Paragraph — Simple DCs

> Simple DCs are used when no level is associated with a task. Based on estimated proficiency rank needed.

- REQ: **Simple DC table**:

| Proficiency Rank | DC |
|------------------|----|
| Untrained | 10 |
| Trained | 15 |
| Expert | 20 |
| Master | 30 |
| Legendary | 40 |

### Paragraph — TABLE 10–5: DCs by Level

> Level-based DCs used when subject has a level. Find level → assign corresponding DC.

- REQ: **Level-Based DC table**:

| Level | DC | Level | DC |
|-------|-----|-------|-----|
| 0 | 14 | 13 | 31 |
| 1 | 15 | 14 | 32 |
| 2 | 16 | 15 | 34 |
| 3 | 18 | 16 | 35 |
| 4 | 19 | 17 | 36 |
| 5 | 20 | 18 | 38 |
| 6 | 22 | 19 | 39 |
| 7 | 23 | 20 | 40 |
| 8 | 24 | 21 | 42 |
| 9 | 26 | 22 | 44 |
| 10 | 27 | 23 | 46 |
| 11 | 28 | 24 | 48 |
| 12 | 30 | 25 | 50 |

- REQ: **Spell Level DCs** (for Identify Spell, Recall Knowledge about spells):

| Spell Level | DC |
|-------------|-----|
| 1st | 15 |
| 2nd | 18 |
| 3rd | 20 |
| 4th | 23 |
| 5th | 26 |
| 6th | 28 |
| 7th | 31 |
| 8th | 34 |
| 9th | 36 |
| 10th | 39 |

### Paragraph — TABLE 10–6: DC Adjustments

> DC adjustments apply when task difficulty differs from baseline. Rarity maps to specific adjustments.

- REQ: **DC Adjustment table**:

| Difficulty | Adjustment | Rarity Equivalent |
|------------|------------|-------------------|
| Incredibly easy | –10 | — |
| Very easy | –5 | — |
| Easy | –2 | — |
| Hard | +2 | Uncommon |
| Very hard | +5 | Rare |
| Incredibly hard | +10 | Unique |

- REQ: Uncommon items/spells = Hard (+2 DC). Rare = Very hard (+5). Unique = Incredibly hard (+10).

### Paragraph — Minimum Proficiency Requirements

> Some tasks require a minimum proficiency rank to succeed (not just to attempt).

- REQ: Tasks may require a minimum proficiency rank; characters below that rank cannot succeed (but can still attempt and critically fail).
- REQ: Guidelines: level ≤ 2 tasks should rarely require expert; ≤ 6 rarely require master; ≤ 14 rarely require legendary.

### Paragraph — DC Guidelines for Specific Actions

> Craft: item's level DC; adjust for rarity. Earn Income: task level = settlement level (village 0–1, town 2–4, city 5–7, metropolis 8–10). Gather Information: simple DC by availability. Identify Magic/Learn Spell: level-based DC + rarity. Recall Knowledge: simple DC for general topics; level-based for creatures/traps.

- REQ: **Craft DC**: use item's level from Table 10–5; apply rarity adjustment from Table 10–6.
- REQ: **Earn Income DC**: task level = settlement level → DC from Table 10–5.
- REQ: **Gather Information DC**: simple DC based on availability; raise for in-depth info.
- REQ: **Identify Magic / Learn Spell DC**: level-based + rarity adjustment.
- REQ: **Recall Knowledge DC**: simple DC for general; level-based for creatures/hazards; rarity adjustment applies.
- REQ: **NPC social DCs**: adjust by attitude — friendly = easy (–2), helpful = very easy (–5), unfriendly = hard (+2), hostile = very hard (+5). Request something fundamentally opposed to = incredibly hard or impossible.

---

## SECTION: Creature Identification

### Paragraph — TABLE 10–7: Creature Identification Skills

> Different creature traits use specific skills for Recall Knowledge.

- REQ: Recall Knowledge skill by creature trait:

| Creature Trait | Skills |
|----------------|--------|
| Aberration | Occultism |
| Animal | Nature |
| Astral | Occultism |
| Beast | Arcana, Nature |
| Celestial | Religion |
| Construct | Arcana, Crafting |
| Dragon | Arcana |
| Elemental | Arcana, Nature |
| Ethereal | Occultism |
| Fey | Nature |
| Fiend | Religion |
| Fungus | Nature |
| Humanoid | Society |
| Monitor | Religion |
| Ooze | Occultism |
| Plant | Nature |
| Spirit | Occultism |
| Undead | Religion |

---

## SECTION: Experience Points and Advancement

### Paragraph — XP System

> 1,000 XP to level up. XP is awarded per character (not divided). All party members receive the same XP.

- REQ: XP threshold: 1,000 XP to gain a level. On leveling up, subtract 1,000 XP and continue.
- REQ: All party members receive the same XP award from any encounter or accomplishment.
- REQ: Trivial encounters normally grant 0 XP (GM may award minor accomplishment XP).

### Paragraph — Advancement Speed Options

> Fast: 800 XP. Standard: 1,000 XP. Slow: 1,200 XP. Story-based: level every 3–4 sessions.

- REQ: Advancement speed variants: Fast (800 XP), Standard (1,000 XP), Slow (1,200 XP).
- REQ: Story-based leveling: ignore XP entirely; level after ~3–4 sessions at major milestones.

### Paragraph — TABLE 10–8: XP Awards

> Accomplishments: Minor 10 XP, Moderate 30 XP, Major 80 XP. Creature XP mirrors Table 10–2.

- REQ: **Accomplishment XP**:

| Accomplishment | XP |
|----------------|----|
| Minor | 10 |
| Moderate | 30 |
| Major | 80 |

- REQ: Moderate and major accomplishments typically also award a Hero Point to an instrumental PC.
- REQ: Creature XP matches Table 10–2 (see Encounter Building). Hazard XP per Table 10–14.

---

## SECTION: Treasure

### Paragraph — Party Treasure by Level (TABLE 10–9)

> Table shows total treasure value per level for 4 PCs: permanent items, consumables, and currency.

- REQ: Treasure per level (4-PC baseline):

| Level | Total Value | Permanent Items | Consumables | Party Currency |
|-------|-------------|-----------------|-------------|----------------|
| 1 | 175 gp | 2nd×2, 1st×2 | 2nd×2, 1st×3 | 40 gp |
| 2 | 300 gp | 3rd×2, 2nd×2 | 3rd×2, 2nd×2, 1st×2 | 70 gp |
| 3 | 500 gp | 4th×2, 3rd×2 | 4th×2, 3rd×2, 2nd×2 | 120 gp |
| 4 | 850 gp | 5th×2, 4th×2 | 5th×2, 4th×2, 3rd×2 | 200 gp |
| 5 | 1,350 gp | 6th×2, 5th×2 | 6th×2, 5th×2, 4th×2 | 320 gp |
| 6 | 2,000 gp | 7th×2, 6th×2 | 7th×2, 6th×2, 5th×2 | 500 gp |
| 7 | 2,900 gp | 8th×2, 7th×2 | 8th×2, 7th×2, 6th×2 | 720 gp |
| 8 | 4,000 gp | 9th×2, 8th×2 | 9th×2, 8th×2, 7th×2 | 1,000 gp |
| 9 | 5,700 gp | 10th×2, 9th×2 | 10th×2, 9th×2, 8th×2 | 1,400 gp |
| 10 | 8,000 gp | 11th×2, 10th×2 | 11th×2, 10th×2, 9th×2 | 2,000 gp |
| 11 | 11,500 gp | 12th×2, 11th×2 | 12th×2, 11th×2, 10th×2 | 2,800 gp |
| 12 | 16,500 gp | 13th×2, 12th×2 | 13th×2, 12th×2, 11th×2 | 4,000 gp |
| 13 | 25,000 gp | 14th×2, 13th×2 | 14th×2, 13th×2, 12th×2 | 5,600 gp |
| 14 | 36,500 gp | 15th×2, 14th×2 | 15th×2, 14th×2, 13th×2 | 8,000 gp |
| 15 | 54,500 gp | 16th×2, 15th×2 | 16th×2, 15th×2, 14th×2 | 11,500 gp |
| 16 | 82,500 gp | 17th×2, 16th×2 | 17th×2, 16th×2, 15th×2 | 17,000 gp |
| 17 | 128,000 gp | 18th×2, 17th×2 | 18th×2, 17th×2, 16th×2 | 26,000 gp |
| 18 | 208,000 gp | 19th×2, 18th×2 | 19th×2, 18th×2, 17th×2 | 40,000 gp |
| 19 | 355,000 gp | 20th×2, 19th×2 | 20th×2, 19th×2, 18th×2 | 70,000 gp |
| 20 | 490,000 gp | 20th×2, 20th×2 | 20th×2, 20th×2, 19th×2 | 80,000 gp |

- REQ: Currency column represents coins, gems, art objects, lower-level items (sold at half price counts as half value).
- REQ: For each PC beyond 4, add extra currency per level using the Party Size adjustment rules.

### Paragraph — Buying and Selling

> Items typically sell for half Price. Art objects, gems, and raw materials sell at full Price.

- REQ: Selling items: standard = half Price. Gems, art objects, raw materials = full Price.
- REQ: Characters can typically buy and sell items only during downtime.

### Paragraph — TABLE 10–10: Character Wealth (New/Replacement Characters)

> Starting wealth for new characters joining a campaign mid-run.

- REQ: New/replacement character starting wealth by level:

| Level | Permanent Items | Currency | Lump Sum Option |
|-------|-----------------|----------|-----------------|
| 1 | — | 15 gp | 15 gp |
| 2 | 1st: 1 | 20 gp | 30 gp |
| 3 | 2nd: 1, 1st: 2 | 25 gp | 75 gp |
| 4 | 3rd: 1, 2nd: 2, 1st: 1 | 30 gp | 140 gp |
| 5 | 4th: 1, 3rd: 2, 2nd: 1, 1st: 2 | 50 gp | 270 gp |
| 6 | 5th: 1, 4th: 2, 3rd: 1, 2nd: 2 | 80 gp | 450 gp |
| 7 | 6th: 1, 5th: 2, 4th: 1, 3rd: 2 | 125 gp | 720 gp |
| 8 | 7th: 1, 6th: 2, 5th: 1, 4th: 2 | 180 gp | 1,100 gp |
| 9 | 8th: 1, 7th: 2, 6th: 1, 5th: 2 | 250 gp | 1,600 gp |
| 10 | 9th: 1, 8th: 2, 7th: 1, 6th: 2 | 350 gp | 2,300 gp |
| 11 | 10th: 1, 9th: 2, 8th: 1, 7th: 2 | 500 gp | 3,200 gp |
| 12 | 11th: 1, 10th: 2, 9th: 1, 8th: 2 | 700 gp | 4,500 gp |
| 13 | 12th: 1, 11th: 2, 10th: 1, 9th: 2 | 1,000 gp | 6,400 gp |
| 14 | 13th: 1, 12th: 2, 11th: 1, 10th: 2 | 1,500 gp | 9,300 gp |
| 15 | 14th: 1, 13th: 2, 12th: 1, 11th: 2 | 2,250 gp | 13,500 gp |
| 16 | 15th: 1, 14th: 2, 13th: 1, 12th: 2 | 3,250 gp | 20,000 gp |
| 17 | 16th: 1, 15th: 2, 14th: 1, 13th: 2 | 5,000 gp | 30,000 gp |
| 18 | 17th: 1, 16th: 2, 15th: 1, 14th: 2 | 7,500 gp | 45,000 gp |
| 19 | 18th: 1, 17th: 2, 16th: 1, 15th: 2 | 12,000 gp | 69,000 gp |
| 20 | 19th: 1, 18th: 2, 17th: 1, 16th: 2 | 20,000 gp | 112,000 gp |

---

## SECTION: Resting and Daily Preparations

### Paragraph — TABLE 10–3: Watches and Rest

> Group members split a watch based on party size. Total rest+watch time by group size.

- REQ: Watch duration by party size:

| Party Size | Duration Per Watch | Total Time |
|------------|-------------------|------------|
| 2 | 8 hours | 16 hours |
| 3 | 4 hours | 12 hours |
| 4 | 2h 40min | 10h 40min |
| 5 | 2 hours | 10 hours |
| 6 | 1h 36min | 9h 36min |

### Paragraph — Starvation and Thirst

> Without water: fatigued immediately; after Con mod + 1 days, take 1d4 damage/hour (unhealed until drinking). Without food: fatigued; after Con mod + 1 days, take 1 damage/day (unhealed until eating).

- REQ: Track starvation/thirst as environmental hazards.
- REQ: Without water: immediate fatigue; after (Con mod + 1) days, 1d4 damage/hour, cannot be healed until thirst quenched.
- REQ: Without food: immediate fatigue; after (Con mod + 1) days, 1 damage/day, cannot be healed until fed.

---

## SECTION: Environment

### Paragraph — TABLE 10–11: Environmental Damage

> Environmental/natural disaster damage uses named categories rather than fixed numbers.

- REQ: Environmental damage categories:

| Category | Damage |
|----------|--------|
| Minor | 1d6–2d6 |
| Moderate | 4d6–6d6 |
| Major | 8d6–12d6 |
| Massive | 18d6–24d6 |

### Paragraph — TABLE 10–12: Environmental Features

> Reference table listing environment features and associated proficiency DC band required to navigate.

- REQ: Implement terrain/environment features with proficiency check bands. Key entries include:

| Feature | Proficiency DC Band |
|---------|---------------------|
| Avalanche | Expert–legendary |
| Bog | Untrained–trained |
| Canopy | Trained–master |
| Chasm | Trained–master |
| Cliff | Trained–master |
| Collapse | Expert–legendary |
| Crowd | Untrained–expert |
| Current (water) | Trained–master |
| Ice | Trained–master |
| Lava | Expert–legendary |
| Narrow surface | Untrained–master |
| Rubble | Untrained–trained |
| Sand | Untrained–trained |
| Slope | Untrained–expert |
| Snow | Untrained–trained |
| Undergrowth | Untrained–trained |
| Uneven ground | Trained–expert |
| Wall | Untrained–legendary |
| Wind | Trained–legendary |

### Paragraph — Temperature Effects (TABLE 10–13)

> Temperature causes fatigue and damage based on category.

- REQ: Temperature effects:

| Category | Temperature | Fatigue Onset | Damage |
|----------|-------------|---------------|--------|
| Incredible cold | –80°F or colder | 2 hours | Moderate cold/minute |
| Extreme cold | –79°F to –20°F | 4 hours | Minor cold/10 min |
| Severe cold | –21°F to 12°F | 4 hours | Minor cold/hour |
| Mild cold | 13°F to 32°F | 4 hours | None |
| Normal | 33°F to 94°F | — | None |
| Mild heat | 95°F to 104°F* | 4 hours | None |
| Severe heat | 105°F to 114°F | 4 hours | Minor fire/hour |
| Extreme heat | 115°F to 139°F | 4 hours | Minor fire/10 min |
| Incredible heat | 140°F or warmer | 2 hours | Moderate fire/minute |

*Adjust down 15°F in high humidity.

### Paragraph — Environmental Terrain Types

> Various terrain types apply difficult terrain, greater difficult terrain, uneven ground, or hazardous terrain rules.

- REQ: Bogs: shallow = difficult terrain; deep = greater difficult terrain; extremely magical bogs = hazardous terrain.
- REQ: Ice: uneven ground AND difficult terrain.
- REQ: Snow: difficult terrain (shallow/packed) or greater difficult terrain (loose/deep). Deep snow may be uneven ground.
- REQ: Sand: packed = normal; loose/shallow = difficult terrain; loose/deep = uneven ground.
- REQ: Rubble: difficult terrain; dense rubble = uneven ground.
- REQ: Undergrowth: light = difficult terrain (allow Take Cover); heavy = greater difficult terrain (automatic cover). Thorns = also hazardous.
- REQ: Slopes: gentle = normal; steep = requires Climb (Athletics check). Flat-footed while climbing inclines.
- REQ: Narrow surface: requires Balance (Acrobatics); flat-footed; fall risk on hit/save-fail (Reflex save at Balance DC).
- REQ: Uneven ground: requires Balance (Acrobatics); flat-footed; fall risk on hit/save-fail.

### Paragraph — Natural Disasters

> Avalanche: major/massive bludgeoning + buried; Reflex save for half and avoid burial. Buried: restrained + minor damage/minute. Collapse: same. Earthquake: fissures, liquefaction, tremors.

- REQ: Avalanche damage: major or massive bludgeoning. Reflex save: success = half damage; crit success = avoid burial.
- REQ: Burial: restrained; minor bludgeoning damage/minute; possible cold damage; risk of suffocation (Fortitude).
- REQ: Rescue digging: Athletics check; 5×5 ft per 4 minutes (2 min crit success); halved with tools.
- REQ: Collapses: major or massive bludgeoning + burial; don't spread unless structural integrity failed.

### Paragraph — Wind Effects

> Wind imposes circumstance penalty to auditory Perception and ranged attack rolls. High wind: difficult/greater terrain for fliers; may require Athletics check on ground.

- REQ: Wind: circumstance penalty to auditory Perception checks (strength-based).
- REQ: Wind: circumstance penalty to physical ranged attacks; powerful winds make ranged attacks impossible.
- REQ: Flying in wind: difficult terrain (or greater) when moving against; Maneuver in Flight check required; blown away on crit fail.
- REQ: Ground movement in strong wind: Athletics check to move; crit fail = knocked back and prone. Small: –1 circumstance; Tiny: –2.

### Paragraph — Aquatic Environment

> Visibility limits: 240 ft max in clear water; 10 ft in murky. Currents = difficult or greater difficult terrain (against current). End of turn: pushed by current distance.

- REQ: Underwater visibility: up to 240 ft (clear water); as low as 10 ft (murky water).
- REQ: Swimming against a current: difficult terrain or greater difficult terrain (based on current speed).
- REQ: Current displacement: end of each turn, creature is moved in current's direction by current's speed.

---

## SECTION: Hazards

### Paragraph — Detecting Hazards

> Without minimum proficiency: automatic secret Perception check for each PC vs. hazard's Stealth DC. With minimum proficiency: only those who have that rank and are actively Searching get the check.

- REQ: Hazards have a Stealth DC for detection.
- REQ: Hazards without minimum proficiency: all PCs auto-roll secret Perception vs. Stealth DC when entering area.
- REQ: Hazards with minimum proficiency: only actively Searching characters with qualifying rank attempt the check.
- REQ: Detect magic can reveal magical hazards without minimum proficiency rank (but not those with minimum rank); doesn't allow disabling.

### Paragraph — Triggering a Hazard

> Standard travel triggers hazard automatically. Manual triggers (e.g., open a door) require a PC to explicitly take that action.

- REQ: Passive triggers (floor plate, sensor): hazard activates automatically if not detected.
- REQ: Active triggers (opening door): only activate if PC explicitly takes that action.

### Paragraph — Hazard Reactions and Routines

> Simple hazard: one reaction = entire effect. Complex hazard: reaction triggers initiative; continues over multiple rounds with a programmed routine.

- REQ: **Simple hazard**: has one reaction; effect resolves in one step.
- REQ: **Complex hazard**: reaction starts encounter/initiative; performs a **routine** each round.
- REQ: Complex hazard routine: preprogrammed set of actions per round. Number and type of actions specified in stat block.
- REQ: If PCs are already in encounter mode when complex hazard triggers, hazard enters at its own initiative (using Stealth modifier).

### Paragraph — Resetting a Hazard

> Some hazards reset automatically (after a set time) or require manual reset steps.

- REQ: Hazards may have a **Reset** condition. Auto-reset occurs after specified time; manual reset requires listed steps.

### Paragraph — Disabling a Hazard

> Most mechanical: Thievery (Disable a Device). Environmental: Nature or Survival. Haunts: Occultism or Religion. 2-action activity. DC and minimum proficiency in stat block. Crit fail: triggers the hazard.

- REQ: Disable a hazard: 2-action activity, skill check vs. hazard's disable DC (listed in stat block).
- REQ: Critical failure on any disable attempt: triggers the hazard.
- REQ: May require multiple successes for complex hazards; critical success = two successes on one component.
- REQ: Minimum proficiency may apply to disabling (same as detecting).
- REQ: Character must first have detected (or been told about) the hazard to attempt disabling.

### Paragraph — Damaging a Hazard

> Mechanical hazards: damage like objects (Hardness reduces damage). Broken Threshold (BT): below BT = broken (disabled, repairable). At 0 HP = destroyed. Hitting usually triggers hazard.

- REQ: Hazards have AC, saving throw modifiers, Hardness, HP, and Broken Threshold (BT) in stat block.
- REQ: Hazard at or below BT: broken (non-functional, repairable). At 0 HP: destroyed.
- REQ: Hitting a hazard typically triggers it unless the attack destroys it outright.
- REQ: Hazards are immune to effects that can't target objects unless specified otherwise.

### Paragraph — Counteracting Magical Hazards

> Can use dispel magic and counteract rules. Counteracting uses spell level and counteract DC in hazard's stat block.

- REQ: Magical hazards have a spell level and counteract DC. Counteract per Ch.9 rules (p.458).
- REQ: Crit fail on counteract: triggers the hazard (same as disable).

### Paragraph — Hazard XP (TABLE 10–14)

> Complex hazard = same XP as creature of same level. Simple hazard = 1/5 of that. Hazards below party level –4 = 0 XP.

- REQ: Hazard XP awarded on overcoming (disable, avoid, endure); awarded only once per hazard per party.
- REQ: Hazard XP table:

| Hazard Level | Simple Hazard XP | Complex Hazard XP |
|-------------|-----------------|-------------------|
| Party level – 4 | 2 | 10 |
| Party level – 3 | 3 | 15 |
| Party level – 2 | 4 | 20 |
| Party level – 1 | 6 | 30 |
| Party level | 8 | 40 |
| Party level + 1 | 12 | 60 |
| Party level + 2 | 16 | 80 |
| Party level + 3 | 24 | 120 |
| Party level + 4 | 32 | 160 |

---

## SECTION: NPC Social Mechanics

### Paragraph — NPC Attitude DCs

> Attitudes affect DCs for social skill checks. Friendly = easy, helpful = very easy, indifferent = normal, unfriendly = hard, hostile = very hard. Requests fundamentally opposed = incredibly hard or impossible.

- REQ: NPC attitude modifies social skill check DCs using standard DC adjustment table:
  - Helpful: very easy (–5)
  - Friendly: easy (–2)
  - Indifferent: no adjustment
  - Unfriendly: hard (+2)
  - Hostile: very hard (+5)
  - Fundamental opposition: incredibly hard (+10) or impossible

---
*Source: PF2E Core Rulebook Ch.10, lines 73975–80760 | Extracted for DungeonCrawler system requirements*
