# Dungeoncrawler: Room chat coordinator envelope fix

## Problem
Room chat POST (`/api/campaign/{campaign}/room/{room}/chat`) routes room-channel player messages through `GameCoordinatorService->processAction(type=talk)` so the encounter framework is authoritative.

However, the controller returned **only** `coordinator_result['result']` (the encounter talk action result), not the canonical `room-chat-response-v1` envelope that the client chat code expects (`gm_response`, `npc_interjections`, `quest_updates`, `turn_logs`, `navigation`, etc.). This mismatch can leave the client in a stale/incorrect local state and is consistent with reports like “stuck on NPC turn” / missing round-turn progression visibility.

## Fix
Standardized the room chat POST response contract for coordinator-routed talk:

1. `EncounterPhaseHandler::processTalk()` now returns the canonical room chat envelope as `room_chat_response` (the direct `RoomChatService->postMessage()` response).
2. In the encounter talk action handler, after decrementing `actions_remaining`, we stamp the updated `game_state` back into `dungeon_data` and into `room_chat_response['dungeon_data']` so the response is immediately consistent.
3. `RoomChatController::postChatMessage()` now returns `result['room_chat_response']` (and errors if missing), so the endpoint always returns the same canonical envelope shape.

## Files changed
- `web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php`
- `web/modules/custom/dungeoncrawler_content/src/Controller/RoomChatController.php`

## Verification
- `drush cr`
- `drush php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php` (59 passed, 0 failed)

## Next verification (manual)
- Open a live encounter room and send room chat:
  - Off-turn talk should be rejected
  - On-turn talk should consume 1 action
  - Chat window should render GM response and any turn_logs without needing a full refresh
