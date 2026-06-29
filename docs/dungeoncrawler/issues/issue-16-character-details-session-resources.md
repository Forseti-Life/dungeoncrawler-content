# Issue #16: Character Details & Session Resources

**Status**: Open  
**Type**: Feature Request  
**Priority**: Low  
**Source**: PF2E Core Rulebook Chapter 1 — Step 10 (Details); Hero Point rules  
**Created**: 2026-02-27

## Overview

Character details are the personal narrative and descriptive elements that complete the character beyond mechanical stats. Session resources are game-state values that reset between sessions. This issue covers narrative fields, the Hero Point mechanic, and per-session resource tracking.

## Requirements

### REQ-16.1 — Narrative Character Detail Fields
The following fields are collected during Step 10 (Character Details) and stored as free-text on the character:

| Field | Required | Notes |
|---|---|---|
| `name` | Yes | Character's in-world name |
| `age` | No | Free text (a number or description like "young adult") |
| `gender` / `pronouns` | No | Free text; player may enter any value |
| `height` | No | Free text |
| `weight` | No | Free text |
| `appearance` | No | Free text physical description |
| `personality_traits` | No | Free text |
| `ideals` | No | Free text |
| `bonds` | No | Free text |
| `flaws` | No | Free text |
| `backstory` | No | Long-form free text |
| `notes` | No | General player notes |

- All fields are free-text; no validation beyond character limits.
- None of these fields have mechanical effect in V1.
- The UI should provide these fields on a "Details" tab of the character sheet.

### REQ-16.2 — Languages
- Characters start with: Common + additional languages based on INT modifier + any ancestry/class languages.
- Number of starting languages = 1 (Common) + INT modifier + languages from ancestry (typically 1 additional) + class-granted languages.
- Human ancestry grants an additional language (see Issue #10).
- INT modifier contributes bonus languages: positive INT = extra languages; negative INT does not reduce below the minimum.
- Languages are stored as an array of strings on the character.
- The Languages section on the character sheet lists all languages.

### REQ-16.3 — Hero Points
Hero Points are a session resource:

| Rule | Detail |
|---|---|
| **Starting amount** | Every character starts each session with 1 Hero Point |
| **Maximum** | 3 Hero Points at any time |
| **Reset** | Reset to 1 at the start of each session |
| **Spending (minor)** | Spend 1 Hero Point to reroll any d20 roll; must take the second result |
| **Spending (major)** | Spend all remaining Hero Points when reduced to 0 HP (instead of dying); become Dying 0 and stable instead of gaining Dying 1 |

- Hero Points are tracked as an integer field on the active session state, not the character entity.
- A GM or system trigger can award Hero Points during a session (maximum 3 total).
- Spending a Hero Point to reroll is the player's choice; the second result is mandatory.
- Spending Hero Points to avoid death uses all current Hero Points regardless of count.

### REQ-16.4 — Session State Entity
A `SessionState` entity must exist per active game session:

| Field | Type | Description |
|---|---|---|
| `character_id` | FK | Character associated with this session state |
| `session_id` | FK | Game session |
| `hero_points` | integer (0–3) | Current Hero Points |
| `current_hp` | integer | Current Hit Points (may differ from max mid-combat) |
| `focus_points` | integer | Current Focus Points (resets on 10-minute rest or short rest per class) |
| `conditions` | string[] | Active condition names (e.g., ["frightened 1", "prone"]) |
| `dying` | integer (0–4) | Dying value (0 = stable/not dying, 4 = dead) |
| `wounded` | integer | Wounded value (persists until healed) |

- `current_hp`, `dying`, `wounded`, `conditions`, and `focus_points` are session-mutable and do not persist to the character sheet base values.
- HP maximum is always derived from the character sheet; current HP may fluctuate.

### REQ-16.5 — Deity Display
- Deity (if selected in Step 8) is displayed on the character sheet in the Details section.
- Deity field shows: deity name and favored weapon.
- For classes that require a deity (Champion, Cleric), this field is required; character creation cannot complete without it.

## Acceptance Criteria
- [ ] All narrative fields are editable free-text; no validation errors on any safe text input.
- [ ] Language list populated correctly: Common + INT modifier + ancestry languages + class languages.
- [ ] Hero Points reset to 1 at start of each session.
- [ ] Hero Point reroll: second result is mandatory (no "take higher").
- [ ] Hero Points cannot exceed 3; award at maximum is silently capped.
- [ ] SessionState entity tracks current HP separately from max HP.
- [ ] Dying field increments/decrements correctly; Dying 4 = dead state.
- [ ] Champion/Cleric without deity cannot complete character creation.

## Source Paragraphs
- "Step 10: Character Details — Fill in the final details of your character: name, age, gender, height, weight, appearance, and personality." (Ch1, Step 10, §1)
- "At the start of each session, every player character gets 1 Hero Point." (Ch1, Step 10, Hero Points box)
- "You can spend a Hero Point at any time... to reroll a d20 (you must use the second result)... or when dying to stabilize." (Ch1, Step 10, Hero Points box)
