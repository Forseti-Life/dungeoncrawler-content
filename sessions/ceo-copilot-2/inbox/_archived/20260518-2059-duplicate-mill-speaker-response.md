Issue: Duplicate NPC response turn at Garnet Mill Approach

Priority: Medium
Campaign: 70
Character: 267

Summary:
At **Garnet Mill Approach**, both **Old Greta Millward** and **Bramwell the Miller's Lad** responded with the same line on what looks like the same exchange. That suggests responder arbitration is allowing multiple speakers to satisfy a single pending turn.

Evidence:
- `dc_room_turn_logs` in room `cae1e37c-e69b-4437-b66f-ca0e94fd2b13 = Garnet Mill Approach`
- Consecutive rows:
  - `id 1300`: `Old Greta Millward` → `"If you are after work, say what kind. I might know a lead, or I might know who does."`
  - `id 1302`: `Bramwell the Miller's Lad` → `"If you are after work, say what kind. I might know a lead, or I might know who does."`
- Timing is effectively back-to-back in the same room turn window.

Why this matters:
- Turn ownership is ambiguous.
- The player can receive duplicated or conflicting NPC replies.
- This likely contributes to the broader room-chat state instability seen elsewhere.

Root cause:
- `dc_room_turn_logs` for turn key `room_turn_6a0b615cc01526.36271792` show the system explicitly scheduled and completed two speakers in sequence:
  - `next_speaker` Greta
  - `speaker_completed` Greta
  - `next_speaker` Bramwell
  - `speaker_completed` Bramwell
- This is not response cloning after generation; it is the turn planner intentionally ordering both NPCs into the same room turn.
- In `RoomChatService::buildNpcTurnPlan()`, if the player message is:
  - not explicitly addressed to the GM
  - not directly addressed to a single NPC
  - not continuing an already-active direct conversation
  then the service falls back to `buildRoomNpcInitiativeOrder($room_npcs, $dungeon_data, $room_id)`.
- At Garnet Mill Approach, that fallback produced a multi-NPC ordered list, so both Greta and Bramwell were allowed to answer one player exchange.

Why the system behaved this way:
- The room-turn harness currently models general room dialogue as a mini initiative round rather than a single-speaker arbitration problem.
- That design is safe for observation-style crowd reactions, but it breaks conversational UX when the expected behavior is one clear responder per exchange.
- With both NPCs sharing similar context, the dialogue generator produced nearly identical lines, making the defect highly visible.

Suggested corrective direction:
1. Change room-turn arbitration so an unaddressed conversational turn resolves to at most one responder unless the action explicitly requests group response.
2. Keep multi-speaker fanout only for deliberate crowd/bark behavior, not default room dialogue.
3. Preserve `next_speaker` logging, but make the planner record why one NPC won and why the others were suppressed.
