# Issue #11: Background System

**Status**: Open  
**Type**: Feature Request  
**Priority**: High  
**Source**: PF2E Core Rulebook Chapter 1 — Step 4 (Pick a Background)  
**Created**: 2026-02-27

## Overview

A character's background represents their life before adventuring — upbringing, trade, or formative experience. Each background is a structured entity providing ability boosts, skill training, and a skill feat. This issue defines the background data model including the Lore skill sub-type.

## Requirements

### REQ-11.1 — Background Entity Data Model
Each background must provide the following fields:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Background name (e.g., "Nomad", "Acolyte") |
| `description` | string | Yes | Narrative description of the background |
| `ability_boost_1` | string[2] | Yes | Choice-of-two: player picks one of these two named scores |
| `ability_boost_2` | literal `"free"` | Yes | Free boost: player picks any score |
| `skill_training` | string | Yes | Specific skill granted as Trained |
| `lore_skill` | string | Yes | Topic name for a Lore skill (e.g., "Cave", "Nomadic") granted as Trained |
| `skill_feat` | Feat | Yes | Specific skill feat granted at 1st level |

### REQ-11.2 — Ability Boost Application
- Background provides exactly 2 ability boosts.
- Boost #1 is a choice-of-two: player selects one of the two named scores.
- Boost #2 is a free boost: player selects any score.
- Both boosts are part of the same batch (Step 4) and must each target a different score per Issue #9 REQ-9.7.

### REQ-11.3 — Skill Training Grant
- Background grants Trained proficiency in one specific skill.
- This is applied directly to the character's skill proficiency map.
- If the class (Step 5/7) would also grant Trained in the same skill, the player substitutes a different skill for the class's grant (not for the background's grant — the background skill is fixed).

### REQ-11.4 — Lore Skill
- Lore is a sub-type of skill that covers a specific knowledge domain.
- Each background defines a Lore topic (e.g., "Cave Lore", "Nomadic Lore").
- Lore skills use INT as their governing ability modifier.
- A character may have multiple distinct Lore skills.
- Lore skill name format: `[Topic] Lore` (e.g., "Cave Lore").
- The topic must be a configurable string field on the background entity, not hardcoded.

### REQ-11.5 — Skill Feat Grant
- Each background grants one specific skill feat at 1st level.
- This feat is automatically applied; no player selection is required for this grant.
- The feat may have a prerequisite of Trained in the associated skill (which is also granted by the background, so it is always satisfiable).

### REQ-11.6 — Character Sheet Population
After Step 4, the following are recorded:
- Background name on character sheet header.
- Ability score adjustments from both background boosts.
- Skill feat in the Skill Feats section.
- Trained mark on the background's specific skill.
- Trained mark on the Lore skill; Lore skill name written in the skills section.

## Acceptance Criteria
- [ ] Background "Nomad" grants: WIS or CON (choice-of-two) + free boost; Survival trained; Cave Lore trained; Assurance (Survival) feat — matches sample character.
- [ ] Choice-of-two boost correctly offers only two options; free boost offers any.
- [ ] Both background boosts are in the same batch; targeting the same score with both is rejected.
- [ ] Class skill overlap: if background grants Athletics and class also grants Athletics, character records class grant as a different skill.
- [ ] Lore skill appears in the skills list with INT as governing modifier.
- [ ] Tests cover: boost application, skill training, lore skill creation, skill feat auto-grant, overlap substitution.

## Source Paragraphs
- "They typically provide two ability boosts (one that can be applied to either of two specific ability scores, and one that is free), training in a specific skill, training in a Lore skill, and a specific skill feat." (Ch1, Step 4, §1)
- Sample character (Step 4): Nomad background → WIS boost, CON free boost, Survival trained, Cave Lore, Assurance (Survival).
