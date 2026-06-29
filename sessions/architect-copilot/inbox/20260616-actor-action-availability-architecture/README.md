# Architecture Request — Actor Action Availability + Prompt Contract

- Agent: architect-copilot
- Created: 2026-06-16
- Topic: actor-action-availability
- Priority: P1

## Summary
The current actor-action prompt contract in Dungeoncrawler is good enough for a narrow encounter subset, but it is not yet a full authoritative action-availability subsystem. We need an architecture that can resolve all actor actions consistently across encounter AI, freeform actor prompts, UI/action rails, and server execution — including spells, feats, consumables, item activations, hazards, and LLM-interpreted option trees.

## What to do
1. Define the canonical server-side subsystem that resolves **all currently legal actions** for a given actor in a given context.
2. Remove duplicated/narrow helper logic so freeform prompts, encounter prompts, preview tooling, and UI all consume the same authoritative action envelope.
3. Treat the shared resolver itself as a GM/NPC/server tool surface:
   - GM can request action availability for any actor by identifier
   - NPC decisioning receives the same envelope automatically at turn start
   - UI/action rails and validation consume the same canonical output
3. Design action-family resolvers for high-branching action types:
   - spells
   - feats
   - consumables
   - item activations
   - skill actions / hazard actions / special movement
4. Split the contract into:
   - top-level action families
   - actor-scoped action availability
   - resolved option payloads for concrete choices
   - LLM interpretation hooks where option selection needs semantic reasoning
5. Keep server authority strict: prompts may recommend/interpret, but legality and execution stay server-enforced.

## Required outcomes
- A proposed canonical `ActorActionAvailability` subsystem boundary and data contract.
- A migration plan from the current curated subset to full engine coverage.
- A punch-list of exact call sites to move onto the new subsystem.
- Explicit handling strategy for spells/feats/consumables and other option-heavy actions.
- Test strategy for deterministic legality + prompt-shape regressions.

## Known hotspots
- `EncounterPhaseHandler::getAvailableActions()`
- `EncounterPhaseHandler::getClientActionContract()`
- `EncounterPhaseHandler::buildActorTurnActionAvailabilityEnvelope()`
- `EncounterAiIntegrationService::buildAllowedActionsForCurrentActor()`
- `AiConversationEncounterAiProvider::buildRecommendationPrompt()`
- `RoomChatService` freeform actor prompt action/tool description block

## Acceptance criteria
- There is one clear authoritative server resolver for actor action availability.
- GM can query the same actor-scoped availability contract that NPC/turn logic already uses.
- Prompt generators consume the same canonical action envelope instead of ad hoc descriptions.
- The architecture accounts for both simple fixed-cost actions and option-heavy families.
- The legality/execution path remains server authoritative even when LLM interpretation is used.
