# Issue #7: Stat Block Data Model & Rules Element Framework

**Status**: Open  
**Type**: Feature Request  
**Priority**: High  
**Source**: PF2E Core Rulebook Chapter 1 — Reading Rules  
**Created**: 2026-02-27

## Overview

Every rules element in PF2e — feats, actions, spells, items, monsters, class features — is presented as a stat block. This issue defines the canonical stat block data model, all its optional fields, and the runtime enforcement of prerequisites, frequency limits, trigger conditions, requirement checks, and multi-take stacking behavior.

## Requirements

### REQ-7.1 — Universal Stat Block Structure
All rules elements must use a shared base stat block. Fields are optional (omit when not applicable). The model must include:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | The rules element's name |
| `action_type` | enum | No | Single/Reaction/Free/Activity2/Activity3 (see Issue #6) |
| `level` | integer | No | Level prerequisite; must be met to access this element |
| `traits` | string[] | No | Tags applied to this element (e.g., `exploration`, `fire`, `divine`) |
| `prerequisites` | Prerequisite[] | No | Conditions that must be met before use (see REQ-7.2) |
| `frequency` | Frequency | No | Use limit per time period (see REQ-7.3) |
| `trigger` | string | No | Event condition for reactions and triggered free actions |
| `requirements` | string | No | Item possession or circumstance required at use time |
| `effect` | string | Yes | The effect/benefit description body |
| `special` | string | No | Multi-take or other special notes |

### REQ-7.2 — Prerequisites Evaluation
- Prerequisites must be evaluatable at runtime before granting access to a rules element.
- Supported prerequisite types:
  - Minimum ability score value (e.g., STR 14)
  - Feat ownership (has a specific feat)
  - Proficiency rank minimum (e.g., Trained in Athletics)
  - Level minimum (met by `level` field above, but also usable inline)
  - Arbitrary other conditions (stored as free text; flagged for manual GM resolution)
- At character creation, the system must validate prerequisites during feat selection.
- At level-up, prerequisites from the same level-up batch are checked after all choices are finalized (not mid-selection).

### REQ-7.3 — Frequency Tracking
- Frequency field must encode a uses-per-time-period structure, e.g.:
  - `{ uses: 1, per: "round" }`
  - `{ uses: 1, per: "day" }`
  - `{ uses: 1, per: "encounter" }`
- System must track usage count per combatant/character per time period.
- Usage count must reset automatically when the time period boundary is crossed (round reset, day reset, encounter end).

### REQ-7.4 — Trigger Condition Detection
- Trigger field is required on all reactions and optional on triggered free actions.
- The engine must detect trigger events during encounters and notify the trigger owner.
- Trigger owner has the opportunity to respond before the triggering action fully resolves (unless the trigger is "after" rather than "before").

### REQ-7.5 — Requirements Check at Use Time
- Requirements field is checked at the moment the ability is activated.
- If requirements are not met (item not in possession, circumstance not active), the ability cannot be used and the attempt is blocked before consuming any action cost.

### REQ-7.6 — Effect Body
- Effect field may represent: automatic effect, roll-dependent effect (dice formula), or passive/constant modification.
- Roll-dependent effects must reference a dice expression (e.g., `2d6+STR`) parsed by the dice engine.
- Constant/passive effects must be applicable to the character's stat block at all times when the feat is owned.

### REQ-7.7 — Special Field: Multi-Take Stacking
- Special field is present primarily on feats that can be taken more than once.
- When a feat with a Special field is taken a second (or subsequent) time, the Special field defines the incremental benefit.
- The system must track how many times each feat has been taken per character.
- Stacking benefits must be computed cumulatively.

### REQ-7.8 — Spells and Items Extend Base Stat Block
- Spells and magic/alchemical items share the base stat block structure.
- They extend it with additional type-specific fields (defined in later issues for spells and items).

## Acceptance Criteria
- [ ] All rules elements share the base stat block schema; no element requires fields outside it (only extends).
- [ ] Prerequisite checker correctly blocks access when conditions are unmet at character creation and level-up.
- [ ] Frequency tracker resets on round/day/encounter boundary; blocks further use when exhausted.
- [ ] Trigger notifier fires before action resolution and offers owner response window.
- [ ] Requirements block activation when not met; no action cost consumed.
- [ ] Multi-take feat correctly stacks benefits per the Special field on each subsequent take.
- [ ] Tests cover: unmet prerequisite blocking, frequency exhaustion and reset, requirement failure at activation, multi-take stacking (2× and 3×).

## Source Paragraphs
- "Rules elements are always presented in the form of a stat block." (Ch1, Reading Rules, §2)
- "Entries are omitted from a stat block when they don't apply." (Ch1, Reading Rules, §4)
- "Prerequisites: Any minimum ability scores, feats, proficiency ranks..." (Ch1, Stat Block Field: Prerequisites)
- "Frequency: This is the limit on how many times you can use the ability within a given time." (Ch1, Stat Block Field: Frequency)
- "Special: Usually this section appears in feats you can select more than once, explaining what happens when you do." (Ch1, Stat Block Field: Special)
