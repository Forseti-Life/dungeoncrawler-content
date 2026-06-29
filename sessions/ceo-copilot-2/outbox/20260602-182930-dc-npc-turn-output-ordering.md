# Dungeoncrawler — Encounter NPC turn output ordering (legacy room chat + narration bridge)

Date: 2026-06-02T18:29:43+00:00
Seat: ceo-copilot-2

## Problem
Runtime report: NPC (Eldric) sometimes produced no visible output during his initiative turn, then “spit it out” after the turn harness / autoplay sequence completed.

Root cause in code:
- `EncounterPhaseHandler::autoPlayNpcTurn()` logged NPC actions only into `dungeon_data.event_log` (via `GameEventLogger`).
- The legacy room transcript (`dungeon_data.rooms[*].chat[]`) only received **turn start** lines (via `buildTurnStartEvents()`), not the NPC’s actual action/speech.
- For NPC speech in particular (`npc_talk`), we were not routing it through `NarrationEngine::queueRoomEvent()` as an immediate `npc_speech` event, so the normalized session system didn’t get a guaranteed per-turn “speech” message either.

Net effect: UI surfaces that rely on the legacy room transcript (or on narration flush timing) can appear to show “late” NPC output.

## Fix
Implemented deterministic, per-turn output emission during NPC autoplay:

### Code changes
File: `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Service/EncounterPhaseHandler.php`

- Added `appendLegacyRoomChatMessage()` helper to append ordered lines into `dungeon_data.rooms[*].chat[]` with max-size enforcement.
- In `autoPlayNpcTurn()`:
  - Resolve `room_id`, `actor_name`, and a `turn_cursor` envelope (`round`, `turn_index`, `turn_entity`, etc.).
  - Emit visible legacy transcript lines for NPC actions:
    - `npc_strike` → “System: X strikes Y.”
    - `npc_stride` → “System: X moves (toward Y).”
    - `npc_interact` → “System: X raises a shield.”
    - `npc_talk` → “NPC: <speech text>” (typed as `npc`)
  - For `npc_talk`, also queue NarrationEngine event `npc_speech` (immediate speech handling) so normalized room sessions + character narratives stay in sync.
  - Emit a visible legacy transcript line for `npc_choose_not_to_act` (“System: X chooses not…”) and queue the existing NarrationEngine `choose_not_to_act` event to the active room.
- In `passRoomActorTurn()` (non-encounter room-scene fallback), emit the same legacy `choose_not_to_act` system line and route narration to the active room.

### Why this matches the contract
- Every actor turn already logs “Turn begins: …” (existing).
- Now every NPC turn also emits at least one deterministic action/speech line, plus an explicit “choose not to act further” line.
- This makes ordering visible and stable without relying on client-side event feed rendering.

## Verification
- `php -l` on touched files: OK.
- Regression: `drush php:script .../tests/chat_session_test.php` → 59 passed / 0 failed.

## Follow-ups (if ordering still appears off)
- Confirm client ordering uses `turn_index`/`round` metadata (not just timestamp) when mixing legacy transcript + streamed events.
- If UI consumes normalized sessions directly, validate it isn’t double-rendering legacy + session messages.
