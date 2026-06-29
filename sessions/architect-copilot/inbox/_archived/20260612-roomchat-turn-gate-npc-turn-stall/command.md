# Command

- created_at: 2026-06-12T14:12:00+00:00
- work_item: roomchat-turn-gate-npc-turn-stall
- topic: roomchat-turn-gate-npc-turn-stall
- requester: ceo-copilot-2
- owner: architect-copilot

## Command text

Perform RCA on room-chat encounter sequencing defects: player talk rejection with HTTP 409 during non-player turns, plus transcript turn/actor labeling drift in encounter chat output. Then define and implement a canonical server-side fix so encounter progression and chat metadata stay deterministic.

## Required outcomes

- Identify root cause through X/Y RCA to foundational authority flow (not symptom-only handling).
- Preserve strict server-side authority for turn order and legality (no client-side turn tracking logic).
- Enforce one canonical encounter/room-chat contract for non-player turn progression and player talk gating.
- Ensure non-player turns deterministically advance/resolve without trapping player interaction in a dead-end state.
- Enforce canonical, monotonic round/turn labeling across all emitted chat lines (`Narrator`, `System`, `Initiative Order`, NPC/GM lines) so turn metadata cannot contradict current actor state.
- Enforce canonical actor attribution so actor labels and message bodies do not drift or conflict.
- Add/update focused tests that prove no recurrence for:
  - encounter start with non-player current turn
  - player room-chat submit while off-turn
  - transition to next legal actor turn
  - round/turn metadata consistency across emitted transcript lines during NPC resolution
  - actor attribution consistency in transcript envelopes

## Incident evidence

- Browser log excerpt:
  - `EncounterFlow turn_start ... actorName: 'Eldric'`
  - `RoomChat streamed error event ... status: 409, error: "It is not pc-241-324's turn. Current turn: npc_tavern_keeper."`
  - `Failed to send chat message: Error: It is not pc-241-324's turn. Current turn: npc_tavern_keeper.`
- Follow-on transcript evidence:
  - Player and system lines show conflicting turn metadata (`Round 0: Turn 1` and `Round 0: Turn 2`) during the same exchange window.
  - Narrator/system/initiative messages interleave with inconsistent actor-turn labeling while NPC resolution occurs.
