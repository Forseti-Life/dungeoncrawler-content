# Issue #6: Action Economy System

**Status**: Open  
**Type**: Feature Request  
**Priority**: Critical  
**Source**: PF2E Core Rulebook Chapter 1 — Understanding Actions  
**Created**: 2026-02-27

## Overview

The action economy is the core mechanical engine of PF2e turn-based gameplay. Every combatant (PC and NPC) operates under a strict action budget per turn. This issue defines the full action type system, turn structure, reaction and free action rules, activity composition, and the three play modes (encounter, exploration, downtime).

## Requirements

### REQ-6.1 — Action Types
The system must model the following distinct action types as an enumeration:
- `SINGLE_ACTION` — costs 1 of the 3-action budget
- `REACTION` — uses the reaction slot; can fire off-turn
- `FREE_ACTION` — costs nothing from budget or reaction slot
- `ACTIVITY_2` — costs 2 actions from budget
- `ACTIVITY_3` — costs 3 actions from budget
- `FREE_ACTIVITY` — activity costing a free action
- `REACTION_ACTIVITY` — activity costing a reaction

Each rules element (feat, spell, ability) must carry one of these type values.

### REQ-6.2 — Per-Turn Action Budget
- Each combatant has exactly 3 single-action points per turn.
- Actions may be spent in any order during the turn.
- Activities consume their full action cost at once; partial spending is not permitted.
- If a combatant cannot pay the full action cost of an activity, the activity may not be initiated.

### REQ-6.3 — Reaction Slot
- Each combatant has exactly 1 reaction per round (not per turn).
- The reaction slot resets at the start of each round.
- Reactions may fire during any combatant's turn (not only the owner's).
- A reaction may only be used when its defined trigger condition is satisfied.

### REQ-6.4 — Free Actions
- Free actions do not consume the 3-action budget or the reaction slot.
- Free actions with a trigger: behave like reactions (can fire off-turn); only 1 free action per trigger instance allowed even if multiple qualify simultaneously.
- Free actions without a trigger: used on the owner's turn, no cost.
- System must enforce: when two or more free actions share the same trigger, only one may be used per trigger event.

### REQ-6.5 — Trigger System
- Every reaction and every triggered free action must define a trigger condition.
- The engine must detect trigger events during the round and offer the trigger owner the opportunity to respond.
- Trigger condition is commonly defined as a specific creature taking a specific action, but must support arbitrary event types.

### REQ-6.6 — Activity Multi-Action Enforcement
- Activities require all specified actions to be spent simultaneously.
- The engine must not allow partial activity resolution.
- Spellcasting is an Activity type; most spells cost 1–3 actions as defined in the spell's stat block.

### REQ-6.7 — Play Modes
The system must model three distinct play modes with separate rule contexts:
- **Encounter** — turn-based; action budget, initiative order, and round tracking are active.
- **Exploration** — free-form; no turn-based action economy; exploration-trait activities are available.
- **Downtime** — day-scale; only downtime-trait activities are available; measured in days not rounds.

### REQ-6.8 — Trait: exploration and downtime
- Activities must carry a `traits` array field.
- Activities with the `exploration` trait are only available in exploration mode.
- Activities with the `downtime` trait are only available in downtime mode.
- Multi-turn activities (duration > 1 round) are still classified as activities and must carry the appropriate mode trait.

## Acceptance Criteria
- [ ] All 7 action types are representable as a field on rules elements.
- [ ] Engine enforces 3-action budget per turn; refuses budget-exceeding actions.
- [ ] Engine tracks 1 reaction per round per combatant; resets each round.
- [ ] Only 1 free action fires per trigger event even when multiple qualify.
- [ ] Activity initiation is blocked if combatant cannot pay the full cost.
- [ ] Three play modes are distinct states; mode-restricted activities are unavailable outside their mode.
- [ ] Tests cover: 3-action turn exhaustion, reaction re-use prevention, free action per-trigger limit, activity partial-spend prevention.

## Source Paragraphs
- "Characters and their adversaries affect the world of Pathfinder by using actions and producing effects." (Ch1, Understanding Actions, §1)
- "You can use three single actions on your turn in an encounter, in any order you see fit." (Ch1, Single Actions)
- "You get only one reaction per encounter round, and you can use it only when its specific trigger is fulfilled." (Ch1, Reactions)
- "You can use only one free action per trigger, so if you have multiple free actions with the same trigger, you have to decide which to use." (Ch1, Free Actions)
- "You have to spend all the actions an activity requires for its effects to happen." (Ch1, Activities §1)
- "If an activity is meant to be done during exploration, it has the exploration trait." (Ch1, Activities §3)
