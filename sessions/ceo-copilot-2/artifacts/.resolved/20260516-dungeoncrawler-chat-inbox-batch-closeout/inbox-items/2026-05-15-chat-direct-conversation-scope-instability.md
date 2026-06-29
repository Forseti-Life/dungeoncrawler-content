# Chat direct-conversation scope instability

## Objective
Stabilize the conversation scope after the system narrows to a direct NPC conversation so that follow-up turns continue in the correct NPC thread instead of snapping back to generic room GM output.

## Source
- Live campaign review: `campaign_id=28`
- Same URL as the CEO chat audit for campaign 28.

## Evidence from live transcript
- Good narrowing:
  - `2026-05-15T12:26:10+00:00` / `12:26:12+00:00` — Eldric reply follows direct-conversation narrowing.
  - `2026-05-15T16:06:51+00:00` / `16:06:53+00:00` — Marta reply follows direct-conversation narrowing.
  - `2026-05-15T16:07:32+00:00` / `16:07:35+00:00` — Marta continues correctly.
- Broken follow-up:
  - `2026-05-15T16:07:12+00:00` — `Let me take a look, do I know this?`
  - `2026-05-15T16:07:13+00:00` — system falls back to generic room-present summary instead of staying in the Marta interaction.

## Why this is a problem
- Makes the user re-establish the conversation target repeatedly.
- Produces inconsistent turn logic and weakens the value of channel narrowing.
- Suggests the system is losing active-target context between adjacent turns.

## Required work
- Trace how direct-address resolution, channel selection, and active-target continuity are stored between turns.
- Determine whether the fallback happens in deterministic GM logic, prompt assembly, or target-resolution logic.
- Ensure that direct NPC follow-up questions remain bound to the addressed NPC unless the player clearly broadens scope.
- Add regression coverage for adjacent NPC follow-ups like:
  - `Marta, what's up?`
  - `Let me take a look, do I know this?`
  - `I'm looking at the text Marta presented`

## Verification required
- Confirm direct conversation stays with the same NPC across multiple follow-up turns.
- Confirm room-wide questions still return to room GM scope when the player intentionally broadens scope.
