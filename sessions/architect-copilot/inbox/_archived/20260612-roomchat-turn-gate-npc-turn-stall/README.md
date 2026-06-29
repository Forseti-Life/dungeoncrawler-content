# Architecture Incident Response — roomchat turn-gate NPC turn stall

- Agent: architect-copilot
- Created: 2026-06-12
- Topic: roomchat-turn-gate-npc-turn-stall
- Priority: P1

## Summary
A live encounter exposed two coupled defects: (1) player room-chat rejected with server 409 during non-player turns, and (2) transcript metadata drift where round/turn/actor labeling becomes inconsistent during NPC resolution. This needs canonical server-side contract hardening for both turn progression and transcript emission.

## Scope
1. Trace full authority path from room-chat POST through coordinator/encounter handlers to turn-state mutation and actor handoff.
2. Identify exact stall mode for non-player turns (including missing action/no-op/advance paths).
3. Standardize canonical server response semantics for off-turn player talk and deterministic progression visibility.
4. Canonicalize transcript round/turn/actor metadata emission during NPC resolution and player handoff.
5. Remove any residual split pathways or implicit client assumptions that can desync turn ownership.
6. Add focused regressions to lock behavior.

## Acceptance criteria
- NPC/non-player turn progression cannot deadlock encounter flow.
- Off-turn player talk remains server-validated with deterministic contract behavior.
- Player regains legal turn action path without manual recovery/reload.
- Round/turn metadata remains monotonic and internally consistent across all emitted chat lines.
- Actor attribution in transcript envelopes is consistent with authoritative actor context.
- Regression coverage exists for the incident scenario.
