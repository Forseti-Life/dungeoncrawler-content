# Issue #10: Ancestry System

**Status**: Open  
**Type**: Feature Request  
**Priority**: High  
**Source**: PF2E Core Rulebook Chapter 1 — Steps 3 (Select an Ancestry), Sample Character  
**Created**: 2026-02-27

## Overview

Ancestry is one of the three core pillars of character identity in PF2e (alongside background and class). Each ancestry is a structured data entity providing physical characteristics, ability adjustments, and character options. This issue defines the ancestry data model, heritage sub-selection, ancestry feat selection, voluntary flaw rules, and the alternate boost option.

## Requirements

### REQ-10.1 — Ancestry Entity Data Model
Each ancestry must provide the following fields:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Ancestry name (e.g., "Dwarf", "Elf") |
| `size` | enum | Yes | Physical size: `Small` or `Medium` (core ancestries) |
| `speed` | integer | Yes | Base movement rate in feet per action |
| `hit_points` | integer | Yes | Flat HP granted at 1st level |
| `languages` | string[] | Yes | Languages spoken at 1st level |
| `bonus_languages` | string[] | No | Languages available if INT modifier ≥ +1 |
| `ability_boosts` | Boost[] | Yes | Fixed and/or free boosts (see Issue #9) |
| `ability_flaw` | string | No | Score name receiving −2 (null if none) |
| `heritages` | Heritage[] | Yes | At least 1 heritage sub-option |
| `ancestry_feats` | Feat[] | Yes | List of ancestry feats player may choose from |
| `special_abilities` | Ability[] | No | Innate abilities (darkvision, low-light vision, etc.) |
| `traits` | string[] | Yes | Ancestry traits (e.g., `dwarf`, `humanoid`) |

### REQ-10.2 — Heritage Sub-Selection
- Every ancestry must have at least one heritage.
- A heritage further modifies the ancestry's traits and may add additional abilities.
- At character creation, the player selects exactly one heritage from the ancestry's available list.
- Heritage is recorded as a sub-field of the character's ancestry (e.g., "Dwarf — Rock Dwarf").

### REQ-10.3 — Ancestry Feat Selection
- At 1st level, the player selects exactly one ancestry feat from the ancestry's feat list.
- Additional ancestry feats are gained at levels specified by the character's class advancement table.
- Ancestry feats may have prerequisites (evaluated per Issue #7, REQ-7.2).

### REQ-10.4 — Special Senses
- Ancestries may grant special senses: darkvision, low-light vision, tremorsense, etc.
- Special senses must be a structured field on the character, not free text.
- `darkvision` — see in darkness as though dim light.
- `low_light_vision` — treat dim light as bright light.
- These senses affect perception and stealth checks in low-light conditions.

### REQ-10.5 — Alternate Ancestry Boost Option
(See also Issue #9 REQ-9.11)
- Player may replace the ancestry's listed fixed boosts and any ability flaw entirely with 2 free ability boosts and no flaw.
- This is a boolean flag on the character's ancestry selection, toggled at character creation.
- When toggled on, the ancestry's default `ability_boosts` and `ability_flaw` arrays are ignored; 2 free boosts are provided instead.

### REQ-10.6 — Voluntary Flaws
- Player may elect additional ability flaws beyond the ancestry's default.
- No more than one total flaw (ancestry + voluntary) per individual ability score.
- Voluntary flaws are tracked separately from ancestry flaws in the character record for auditability.
- System surfaces an informational warning ("consult your group") but does not block this choice.

### REQ-10.7 — Minimum Core Ancestries (Data)
The following six core ancestries must be present in the game content database:
1. Dwarf (CON/WIS/free boosts; CHA flaw; 10 HP; includes Rock Dwarf heritage example)
2. Elf (DEX/INT/free boosts; CON flaw; 6 HP)
3. Gnome (CON/CHA/free boosts; STR flaw; 8 HP)
4. Goblin (DEX/CHA/free boosts; WIS flaw; 6 HP)
5. Halfling (DEX/WIS/free boosts; STR flaw; 6 HP)
6. Human (2 free boosts; no flaw; 8 HP)

(Half-elf and Half-orc are human heritages, not separate ancestries.)

## Acceptance Criteria
- [ ] All 6 core ancestries exist in the database with correct size, speed, HP, languages, boosts, and flaws.
- [ ] Each ancestry has at least one heritage defined.
- [ ] Dwarf character creation applies +CON, +WIS, +free boost, −CHA correctly; darkvision is granted.
- [ ] Human alternate ancestry option produces exactly 2 free boosts and no flaw; no fixed values.
- [ ] Voluntary flaw reduces a score by 2; system prevents >1 flaw on same score.
- [ ] Ancestry feat list is browsable at character creation; selected feat recorded on character sheet.
- [ ] Tests cover: correct HP grant, correct boost/flaw application, heritage selection recorded, alternate boost toggle.

## Source Paragraphs
- "Ancestry determines your character's size, Speed, and languages, and contributes to their Hit Points." (Ch1, Step 3, §2)
- "You'll make four decisions when you select your character's ancestry..." (Ch1, Step 3, §3)
- "You always have the option to replace your ancestry's listed ability boosts and ability flaws entirely and instead select two free ability boosts." (Ch1, Alternate Ancestry Boosts sidebar)
- "You can't apply more than one flaw to any single ability score." (Ch1, Optional: Voluntary Flaws sidebar)
- Sample character: Dwarf grants +CON, +WIS, free boost, −CHA flaw, 10 HP, darkvision, Rock Dwarf heritage.
