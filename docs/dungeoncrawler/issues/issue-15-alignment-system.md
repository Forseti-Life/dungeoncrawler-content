# Issue #15: Alignment System

**Status**: Open  
**Type**: Feature Request  
**Priority**: Low  
**Source**: PF2E Core Rulebook Chapter 1 — Step 8 (Choose Alignment and Deity)  
**Created**: 2026-02-27

## Overview

Alignment describes a character's moral and ethical outlook on a two-axis grid. Some classes restrict which alignments are valid. This issue defines the alignment data model, the two-axis grid, class/deity restrictions, and the mutable state rules.

## Requirements

### REQ-15.1 — Alignment Data Model
Alignment is defined on two axes:

| Axis | Values |
|---|---|
| **Ethical** (Law–Chaos) | Lawful, Neutral, Chaotic |
| **Moral** (Good–Evil) | Good, Neutral, Evil |

The nine alignments are the cross-product of these two axes:

| | Lawful | Neutral | Chaotic |
|---|---|---|---|
| **Good** | LG | NG | CG |
| **Neutral** | LN | N (TN) | CN |
| **Evil** | LE | NE | CE |

- True Neutral (both axes Neutral) is typically abbreviated N or TN.
- Alignment must be stored as an enum value on the character entity.
- Alignment is selected during Step 8 (Choose Alignment and Deity) of character creation.

### REQ-15.2 — Alignment as Character Trait
- A character's alignment defines how they interact with alignment-typed magic (holy, unholy, etc.).
- Alignment is recorded in the character's Traits section on the character sheet.
- Alignment trait labels: "Good", "Evil", "Lawful", "Chaotic" (neutrals on each axis have no trait for that axis).

### REQ-15.3 — Class Alignment Restrictions
Some classes have required or restricted alignments:

| Class | Restriction |
|---|---|
| Champion | Alignment must be within one step of the patron deity's alignment on each axis |
| Cleric | Alignment must be within one step of their deity's alignment on each axis |
| Druid | Cannot be Lawful (some Orders may further restrict) |

- Alignment restrictions are enforced during character creation and on alignment change.
- If a player selects a restricted alignment for a restricted class, an error is shown.
- "Within one step" means the alignment may differ on at most one axis by one step (e.g., LG deity: character may be LG, NG, LN).

### REQ-15.4 — Mutable Alignment
- Alignment is not fixed at character creation; it may change during play through roleplay or narrative events.
- The system must allow alignment to be updated post-creation.
- There is no automatic trigger for alignment change; it is GM/narrative driven.

### REQ-15.5 — Atonement Suppression State (Optional)
- If a Champion or Cleric acts against their alignment restrictions, they may lose class powers until an atonement ritual is performed.
- This is a suppression state: a boolean flag on the character indicating powers are suspended.
- The flag can be set/cleared by a GM-level operation.
- Implementation of this state is informational for V1; enforcement of power loss is tracked in a future issue.

### REQ-15.6 — Deity Field
- Alignment and Deity selection occur in the same step (Step 8).
- Deity is a separate field recorded on the character sheet.
- Required for: Champion and Cleric classes.
- Optional for: all other classes (including Druid — though druids typically venerate nature, a specific deity is not required).
- Deity entity fields: name, alignment, domains (array), favored weapon.
- Deity alignment is used to enforce class alignment restrictions (REQ-15.3).

## Acceptance Criteria
- [ ] All 9 alignments available in selection UI.
- [ ] Neutral-Neutral alignment displayed as "N" or "TN".
- [ ] Druid restriction: Lawful alignment selections are disabled/rejected.
- [ ] Champion/Cleric: selecting an alignment more than one step from deity's alignment is rejected.
- [ ] Alignment stored in character.traits and displayed in Traits section of sheet.
- [ ] Alignment can be edited post-creation from the character management screen.
- [ ] Atonement flag exists on character entity; can be toggled by GM role.

## Source Paragraphs
- "Your alignment is a general descriptor of your morality, described with two axes: lawful–chaotic and good–evil." (Ch1, Step 8, §1)
- "Some classes restrict which alignments you can have." (Ch1, Step 8, §2)
- "Druids must not be lawful." (Ch1, Step 8, Druid class note)
- Sample character (Gar): alignment selected as Neutral Good (NG).
