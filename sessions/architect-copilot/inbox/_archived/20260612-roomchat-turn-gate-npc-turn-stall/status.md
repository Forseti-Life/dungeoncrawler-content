# Status

- status: completed
- created_at: 2026-06-12T14:12:00+00:00
- current_phase: complete

## Notes

### 2026-06-12 — Kickoff
- Incident converted from live browser evidence into architect inbox work item.
- Primary symptom: room-chat 409 turn-gate rejection while current turn is `npc_tavern_keeper`.
- Required direction: RCA to foundational turn-handoff contract with server-side canonical fix.

### 2026-06-12 — Follow-on evidence added
- Added transcript evidence showing round/turn metadata drift and actor-label inconsistency during NPC resolution.
- Expanded command/acceptance criteria to require canonical transcript metadata consistency in addition to non-player turn progression hardening.

### 2026-06-12 — Server-side implementation completed
- Implemented in `dungeoncrawler-content` commit `fd8bf35` (pushed to `main`).
- Progress-line prefixing now uses authoritative server round/turn snapshots carried in context (instead of opportunistic live-state lookups per stage), reducing turn-label drift during streamed updates.
- Encounter-phase room harness now skips out-of-turn NPC chatter so hard encounter loops remain authoritative to coordinator/phase-handler turn flow.
- Added regression coverage for provided progress snapshot handling:
  - `RoomChatControllerProgressTest::testBuildProgressEventDataUsesProvidedEncounterSnapshot`

### 2026-06-12 — Follow-on runtime contract fix completed
- Incident follow-up from live browser logs identified a new server-side 500 stream failure after the 409 turn-gate fix.
- Root cause: room-turn harness payload contract required non-empty `turn_log_key`, but encounter short-path could emit blank key, causing schema validation exception.
- Implemented canonical payload normalization in `RoomChatService::buildRoomTurnHarnessPayload()` to always supply a non-empty key.
- Added focused regression:
  - `RoomChatServiceNpcResolutionTest::testBuildRoomTurnHarnessPayloadGeneratesNonEmptyTurnLogKeyWhenMissing`
- Shipped in `dungeoncrawler-content` commit `6d3f28e` (pushed to `main`).
