# Architecture Request — GM Backstop Subsystem

- Agent: ceo-copilot-2
- Created: 2026-06-16
- Topic: dungeoncrawler-gm-subsystem
- Priority: P1

## Summary
Dungeoncrawler currently has strong deterministic engine pieces and a meaningful GM prompt identity, but the GM still lives mostly as a fallback embedded inside `RoomChatService`. We need an explicit GM subsystem architecture where the deterministic engine resolves whatever it can first, and the GM becomes the authoritative backstop for ambiguity, intent interpretation, workflow routing, and grounded narration.

## What to do
1. Define the GM as a first-class subsystem boundary above deterministic encounter/room logic.
2. Specify how every non-deterministic player message flows from chat transport into GM interpretation, canonical action/workflow selection, validation, broker execution, and transcript output.
3. Separate deterministic resolution from GM backstop responsibilities so controller shortcuts and room-chat fallbacks stop competing.
4. Define the GM tool/workflow surface explicitly:
   - adjudication / referee narration
   - canonical action proposal
   - combat initiation
   - quest progression / quest turn-in
   - navigation / room transition
   - NPC handoff / NPC dialogue routing
   - item / inventory / room interaction escalation hooks
5. Preserve strict server authority: the GM can interpret and route, but legality and execution remain server-enforced.

## Required outcomes
- A proposed `GameMasterSubsystem` architecture with clear service boundaries.
- A request/response flow showing deterministic-first, GM-backstop-second handling.
- A normalized GM intent/action envelope and broker interface.
- A migration plan from the current `RoomChatService`-embedded fallback to the new subsystem.
- A punch-list of exact code seams to move.
- Test strategy for deterministic path coverage, GM-backstop routing, and prompt/tool-context regressions.

## Known hotspots
- `src/Controller/RoomChatController.php`
- `src/Service/RoomChatService.php`
- `src/Service/GameCoordinatorService.php`
- `src/Service/GmOrchestrationBrokerService.php`
- `src/Service/CanonicalActionRegistryService.php`
- `src/Service/GameplayActionProcessor.php`
- `ai-conversation/src/Service/PromptManager.php`

## Acceptance criteria
- The GM is represented as an explicit subsystem, not an implicit room-chat fallback.
- Deterministic engine paths and GM-backstop paths have a clean handoff boundary.
- The fallback GM prompt/instructions clearly state that the GM interprets intent and routes to authoritative workflows.
- The GM receives explicit context for all available tools/workflows relevant to backstop handling.
- Execution legality and state mutation stay server authoritative.
