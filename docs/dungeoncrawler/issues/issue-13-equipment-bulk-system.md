# Issue #13: Equipment & Bulk System

**Status**: Open  
**Type**: Feature Request  
**Priority**: Medium  
**Source**: PF2E Core Rulebook Chapter 1 — Step 9 (Buy Equipment); Equipment Rules  
**Created**: 2026-02-27

## Overview

Characters start with a gold piece allotment and purchase equipment subject to encumbrance rules modeled through a Bulk system. This issue covers the starting wealth model, currency denominations, the Bulk encumbrance system, inventory management, and armor's Dexterity cap interaction.

## Requirements

### REQ-13.1 — Starting Wealth
- Starting wealth for 1st-level characters is **15 gold pieces (gp)**.
- This value is defined per class; different classes may specify different starting wealth in full rules, but 15 gp is the standard value used during character creation.
- Starting wealth is subtracted from as the player purchases equipment.
- Remaining wealth is recorded as the character's starting currency.

### REQ-13.2 — Currency Denominations
Four denominations must be supported with defined conversion rates:

| Denomination | Abbreviation | Value in Copper |
|---|---|---|
| Copper piece | cp | 1 |
| Silver piece | sp | 10 cp |
| Gold piece | gp | 100 cp |
| Platinum piece | pp | 1,000 cp |

- All currency amounts must be convertible between denominations.
- The character sheet shows separate fields for cp / sp / gp / pp.
- Starting wealth of 15 gp = 1,500 cp = 150 sp.

### REQ-13.3 — Bulk System
Bulk measures the weight and unwieldiness of carried items:

| Notation | Meaning |
|---|---|
| `L` | Light (negligible; 10 Light items = 1 Bulk) |
| integer | Full Bulk (e.g., 1, 2, 3) |
| `—` | Negligible (does not count) |

- Bulk is tracked per item and summed for total carried Bulk.
- 10 Light items = 1 Bulk for purposes of total.

### REQ-13.4 — Encumbrance Thresholds
Encumbrance is calculated relative to the character's STR score (not modifier):

| Condition | Threshold |
|---|---|
| **Encumbered** | Carried Bulk > STR score + 5 |
| **Cannot carry** | Carried Bulk > STR score + 10 |

- Encumbered: character takes –1 circumstance penalty to all physical rolls; Speed is reduced by 10 feet.
- The system must track encumbrance state and apply the penalty automatically when bulk threshold is exceeded.

### REQ-13.5 — Item Entity Data Model

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Item name |
| `category` | enum | Yes | Weapon, Armor, Gear, Consumable, Container |
| `bulk` | string | Yes | `"L"`, `"—"`, or integer string |
| `price` | integer (cp) | Yes | Price in copper pieces |
| `quantity` | integer | Yes | Number carried |
| `description` | string | No | Item description |
| `hands` | integer | No | 1 or 2 (weapons/shields) |
| `worn` | boolean | No | True if worn/equipped |

### REQ-13.6 — Armor & Dexterity Cap
Armor items have additional fields beyond the base item model:

| Field | Description |
|---|---|
| `ac_bonus` | Armor Class bonus granted |
| `dex_cap` | Maximum DEX modifier that applies to AC when wearing this armor |
| `check_penalty` | Penalty to STR- and DEX-based skill checks |
| `speed_penalty` | Penalty to Speed in feet |
| `strength_requirement` | Minimum STR score to avoid check penalty (medium/heavy armor) |
| `armor_category` | Unarmored / Light / Medium / Heavy |

- The AC calculation in Issue #14 must use the armor's `dex_cap` to cap the DEX modifier contribution.
- Example: Explorer's Clothing has DEX cap +5 (effectively no cap at typical stats); full plate has DEX cap +0.

### REQ-13.7 — Weapon Fields
Weapon items must carry additional fields:

| Field | Description |
|---|---|
| `damage_dice` | e.g., "1d6", "1d8" |
| `damage_type` | Bludgeoning / Piercing / Slashing |
| `weapon_category` | Simple / Martial / Advanced |
| `weapon_group` | e.g., Sword, Bow, Club |
| `traits` | Array of trait strings (e.g., ["Versatile P", "Sweep"]) |
| `range` | For ranged weapons; in feet (null for melee) |
| `reload` | Reload action cost (0, 1, 2…) |

### REQ-13.8 — Equipment Step in Character Creation
- During Step 9, the player starts with 15 gp and selects equipment.
- The UI must track remaining funds, displaying the running balance.
- After purchase, the character's inventory list and currency fields are populated.
- Bulk total is calculated and compared to encumbrance thresholds.
- Character sheet fields populated: Armor (equipped), weapons (list), worn/carried equipment list, currency, Bulk total.

## Acceptance Criteria
- [ ] Starting wealth of 15 gp (1,500 cp) displayed at start of Step 9.
- [ ] Currency conversion: 1 gp → 10 sp → 100 cp.
- [ ] Item purchase decrements running currency balance; balance shown in real time.
- [ ] Bulk sum: 10 L items = 1 Bulk; partial sets of L items round down in Bulk counting.
- [ ] Encumbrance state changes when Bulk exceeds STR+5.
- [ ] Armor DEX cap field correctly limits DEX mod in AC calculation (see Issue #14).
- [ ] Sample character: explorer's clothing (DEX cap +5), staff (Bulk 1, 1d4/1d6 dmg), shortbow (Bulk 1), arrows ×20, backpack, rations, bedroll, etc.

## Source Paragraphs
- "Each character typically starts with 15 gp to purchase their starting equipment." (Ch1, Step 9, §1)
- "The bulk system measures how much a character can carry based on their Strength score (not modifier)." (Ch1, Step 9, §3)
- "Encumbered threshold: Bulk > Strength score + 5. Max carry: Bulk > Strength score + 10." (Ch1, Step 9, §3–4)
