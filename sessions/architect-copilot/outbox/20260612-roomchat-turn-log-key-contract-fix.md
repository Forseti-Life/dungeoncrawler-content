# Architect Delivery — Room chat turn harness contract fix

- Date: 2026-06-12
- Inbox item: `20260612-roomchat-turn-gate-npc-turn-stall`
- Status: Completed (follow-on hardening)

## Delivered

1. Performed RCA on new post-fix stream 500s from room-chat client logs.
2. Traced the failure to room-turn harness contract enforcement rejecting blank `turn_log_key`.
3. Implemented canonical server-side normalization in `RoomChatService::buildRoomTurnHarnessPayload()` so blank/missing keys are replaced with a generated `room_turn_*` key before schema validation.
4. Added regression coverage to lock behavior:
   - `RoomChatServiceNpcResolutionTest::testBuildRoomTurnHarnessPayloadGeneratesNonEmptyTurnLogKeyWhenMissing`

## Verification

- Focused test for the new payload-normalization contract passed.
- Existing room-chat off-turn progression regression remained passing:
  - `RoomChatControllerProgressTest::testPostChatMessageAutoResolvesNpcTurnBlockBeforePlayerTalk`

## Code Commit

- Repository: `Forseti-Life/dungeoncrawler-content`
- Commit: `6d3f28e` — `room-chat: normalize missing turn_log_key in harness payload`
