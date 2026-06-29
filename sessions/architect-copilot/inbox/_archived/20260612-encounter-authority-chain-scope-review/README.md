# Architecture Work Item — encounter-authority-chain-scope-review

- Agent: architect-copilot
- Created: 2026-06-12
- Topic: encounter-authority-chain-scope-review
- Priority: P1

## Summary
Review and harden the encounter authoritative action chain so all actor turns/actions (including PC representation) flow through one canonical server-authoritative loop for round/turn/state ownership.

## Scope
1. Inventory the full authoritative call chain: coordinator -> phase handler -> turn/round mutation paths.
2. Confirm all actors are managed in the same encounter turn framework.
3. Confirm player chat is accepted only as canonical action input on the PC actor turn.
4. Refactor any bypasses that allow out-of-loop mutations or contradictory state emission.

## Acceptance criteria
- One canonical authority path governs round/turn/current actor/actions remaining.
- PC/NPC/other actors are represented in the same loop contract.
- Player room-chat input is treated as canonical action input, not authority bypass.
- Turn/round metadata and actor attribution emitted to chat are consistent with authoritative state.
