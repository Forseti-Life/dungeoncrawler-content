# Chat GM role-boundary failure

## Objective
Investigate and fix the live Dungeoncrawler chat behavior where the Game Master layer speaks as the player character instead of narrating or adjudicating from the referee layer.

## Source
- Live campaign review: `campaign_id=28`
- URL: `https://dungeoncrawler.forseti.life/hexmap?campaign_id=28&character_id=122&dungeon_level_id=f8c6b8f1-2df9-469f-9fd5-67a59f120001&map_id=0b7e3d2f-8f7c-4ae0-8f72-9e99e0800001&room_id=7f2f1051-5f88-45a2-a66a-0f7063900001&start_q=0&start_r=0`

## Evidence from live transcript
- `2026-05-15T12:30:56+00:00` — GM writes Burasco's inner thoughts and proposed action.
- `2026-05-15T16:05:56+00:00` — GM says `I'm Burasco...`
- `2026-05-15T16:07:50+00:00` — GM writes a first-person Burasco response instead of adjudicating `GM, have I?`
- `2026-05-15T16:13:08+00:00` — GM again roleplays the player character after being explicitly corrected.

## Why this is a problem
- Breaks the core GM/NPC/player separation.
- Confuses the user about whether they or the system control the player character.
- Undermines downstream NPC turn handling because the GM layer leaks into in-world dialogue.

## Required work
- Trace the room-chat GM generation path and identify where player-character voice is still being admitted.
- Confirm prompt guardrails, deterministic response paths, and any retry/reality-check path all preserve the same role boundary.
- Add targeted regression coverage for prompts like:
  - `What up?`
  - `GM, have I?`
  - `GM isn't supposed to act as the Player...`
- Verify that the primary GM response remains narrator/referee-only and never speaks as the player character.

## Verification required
- Reproduce the live failure against campaign-style tavern chat prompts.
- Confirm the GM no longer emits first-person player-character speech.
- Confirm NPC/direct conversation still functions after the fix.
