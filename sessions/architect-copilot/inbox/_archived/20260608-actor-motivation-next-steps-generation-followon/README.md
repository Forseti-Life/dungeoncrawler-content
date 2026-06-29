# Architecture Follow-on — Actor motivation and next-step generation

- Agent: architect-copilot
- Created: 2026-06-08
- Topic: actor-motivation-next-steps-generation
- Priority: P1

## Summary
The first hardening slice now injects structured NPC psychology into encounter action recommendations and uses motivation/attitude/personality axes in deterministic fallback action selection + target determination.

This follow-on item tracks the next-step generation work needed to evolve from single-action heuristics into a stronger per-turn behavioral contract.

## Scope
1. Define a canonical **motivation -> tactical intent** contract for non-player actors (encounter + room-scene turns).
2. Extend fallback logic from single-step action picks to **multi-action turn plans** that preserve psychology consistency across actions.
3. Add explicit **decision_reason/decision_basis** telemetry fields for NPC turns so UI/logs can explain why an NPC chose its action.
4. Add focused regression coverage for:
   - motivation-driven de-escalation,
   - self-preservation retreat/reposition behavior,
   - high-cunning target prioritization over nearest-target defaults,
   - deterministic plan continuity across action 1/2/3 in a turn.

## Acceptance criteria
- NPC next-step generation uses an explicit, test-backed motivation contract (not ad hoc branching only).
- Deterministic fallback behavior remains server-authoritative and reproducible from state.
- Encounter/event outputs expose concise machine-readable decision basis metadata for debugging/UX.
- No regression in existing action legality/validation constraints.

## References
- `dungeoncrawler-content/src/Service/EncounterPhaseHandler.php`
- `dungeoncrawler-content/src/Service/AiConversationEncounterAiProvider.php`
- `dungeoncrawler-content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php`
- `dungeoncrawler-content/tests/src/Unit/Service/AiConversationEncounterAiProviderTest.php`
