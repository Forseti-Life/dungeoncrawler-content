# Chat GM meta-question misrouting

## Objective
Fix the handling of explicit GM-directed or adjudication-style player prompts so they route to referee adjudication instead of in-world dialogue or player-character roleplay.

## Source
- Live campaign review: `campaign_id=28`

## Evidence from live transcript
- `2026-05-15T16:07:47+00:00` — player says `GM, have I?`
- `2026-05-15T16:07:50+00:00` — response is a player-character/Burasco roleplay monologue instead of a knowledge/adjudication answer.

## Why this is a problem
- Explicitly GM-directed prompts are a core tabletop interaction mode.
- The system appears to miss or ignore a strong meta/control signal from the user.
- This failure compounds the GM role-boundary bug.

## Required work
- Add or strengthen classifier logic for GM-directed prompts, knowledge checks, recollection questions, and out-of-dialogue adjudication asks.
- Ensure such prompts route to the GM/referee layer even when an NPC conversation is active.
- Make the GM answer what the character knows, notices, recalls, or can infer without switching into PC dialogue.
- Add regression coverage for prompts including:
  - `GM, have I?`
  - `Do I know this?`
  - `Would Burasco recognize that phrase?`

## Verification required
- Confirm explicit GM-directed prompts produce adjudication/referee narration only.
- Confirm no first-person player-character response is emitted for those prompts.
