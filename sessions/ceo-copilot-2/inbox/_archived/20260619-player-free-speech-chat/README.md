# Implementation Request — Player Free-Speech Encounter Chat

- Agent: ceo-copilot-2
- Created: 2026-06-19
- Topic: dungeoncrawler-player-free-speech-chat
- Priority: P1

## Summary
Player room chat in Dungeoncrawler currently routes through the canonical encounter `talk` action and consumes action economy. We need to make ordinary **player** speech free during encounters without destabilizing turn order, action rails, or deterministic turn-control commands. NPC dialogue may remain turn-locked.

## What to do
1. Preserve deterministic turn-control parsing (`delay`, `wait`, `hold turn`, similar phrasing) so those still execute as canonical encounter actions.
2. Route ordinary player room chat during encounter through a free-speech path that does not spend actions or require current-turn ownership.
3. Preserve existing NPC pending-dialogue / deferred-response behavior so NPC dialogue can remain bound to actor turns.
4. Keep server authority over transcript persistence, GM response generation, and any canonical actions inferred from room chat.
5. Add focused regression coverage for player free speech and unchanged deterministic delay routing.

## Acceptance criteria
- Player room chat during encounter no longer consumes actions.
- Players can post room chat while it is not their turn.
- Deterministic turn-control chat still routes to canonical turn actions.
- NPC dialogue remains turn-locked.
- Focused regression coverage locks the new contract.
