# Chat storyline grounding gap

## Objective
Audit and fix the tavern/NPC chat grounding so published in-game storylines and authored leads are surfaced through the correct NPCs instead of generic filler hooks.

## Source
- Live campaign review: `campaign_id=28`
- User expectation from live review: Eldric should surface published storyline leads already present in the game.

## Evidence from live transcript
- `2026-05-15T12:26:12+00:00` — Eldric gives generic hooks about bandits, mountain passes, and caravan guards.
- `2026-05-15T12:31:31+00:00` — Eldric says he is `a barkeep, not a quest-giver` and again gives generic work leads.

## Why this is a problem
- Breaks authored content discoverability.
- Makes tavern starter NPCs feel disconnected from the actual published game state.
- Reduces trust that the world knowledge is canonical rather than improvised.

## Required work
- Audit what storyline/quest context is actually available to tavern NPC prompt assembly.
- Verify whether authored leads, quest hooks, or published storyline metadata are being passed to the GM and NPC response layers.
- Determine whether Eldric, Marta, or another canonical NPC should be the expected lead surface for current content.
- Add targeted grounding so published storyline hooks appear before generic filler adventure suggestions.

## Verification required
- Confirm tavern lead NPCs surface actual published story hooks in this campaign state.
- Confirm the system prefers canonical in-game leads over generic improvised adventure bait.
