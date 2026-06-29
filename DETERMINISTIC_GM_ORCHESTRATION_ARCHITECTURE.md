# Deterministic GM Orchestration Architecture

**Module**: dungeoncrawler_content  
**Last updated**: 2026-06-19  
**Status**: Proposed architecture with explicit GM subsystem facade in place; normalized player room-chat routing envelope is now implemented, while broader deterministic broker migration remains in progress

---

## Overview

This document defines the target architecture for moving room-chat orchestration away
from a prompt-described action protocol and toward a deterministic, server-owned
broker model.

Current implementation state:

- architecture documentation is now in-repo,
- a first `GmOrchestrationBrokerService` scaffold exists,
- `GameMasterSubsystemService` now owns the explicit player room-chat subsystem boundary between transport and authoritative action routing,
- the subsystem returns a normalized route/workflow/intent envelope for player room chat while keeping the controller response contract stable,
- `RoomChatService` now delegates authoritative quest/combat canonical action
  execution through that broker,
- deterministic route extraction, argument resolution, and receipt-driven narration
  are still future migration work.

The governing rule is:

> **The GM should narrate from authoritative state and execution receipts, not act as
> the primary source of backend tool calls.**

This design keeps the GM as a narrative and adjudication layer while moving backend
lookups, validation, argument resolution, and state mutations into typed PHP services.

---

## Why This Change Is Needed

The current room-chat flow already has strong server-side execution, but it still
depends too much on the GM model to:

- choose which backend action shape to emit,
- resolve ids from prose context,
- produce JSON that matches backend expectations,
- retry when the model emits invalid or impossible actions.

That is workable for experimentation, but it is the wrong long-term control surface
for mechanics that should be deterministic.

### Current pain points

1. `RoomChatService::generateGmReply()` owns too many concerns at once:
   - intent classification,
   - deterministic short-path replies,
   - prompt assembly,
   - LLM invocation,
   - JSON parsing,
   - validation/retry,
   - authoritative action execution,
   - persistence and response shaping.
2. `GameplayActionProcessor::buildEnhancedSystemPrompt()` carries a large prompt-time
   action contract that asks the model to manufacture backend payloads.
3. `CanonicalActionRegistryService` is currently more of a prompt registry than a
   real typed broker catalog.
4. Deterministic services already exist for many mechanics, but they are often used
   only after the GM has proposed a structure.

---

## Current Runtime Ownership

### Current room-chat path

Today, the canonical room-chat orchestration path is:

```text
Player room message
  -> RoomChatService::generateGmReply()
    -> classifyRoomTurnIntent()
    -> buildDeterministicGmNarrative() [optional shortcut]
    -> build prompt + system prompt
    -> invoke GM model
    -> GameplayActionProcessor::parseResponse()
    -> GameplayActionProcessor::validateCharacterActionResources()
    -> retry on invalid actions
    -> executeCanonicalAuthoritativeActions()
    -> persist / return narrative + actions
```

### Current authoritative services already in place

The current codebase already has strong backend ownership in these areas:

| Concern | Current authoritative service |
|---|---|
| inventory custody changes | `InventoryManagementService` |
| quest progress / turn-ins | `QuestTouchpointService` |
| combat phase transition | `GameCoordinatorService` + `EncounterPhaseHandler` + `CombatEngine` |
| room / entity grounding | `GameplayActionProcessor::buildRoomInventory()` |
| relevant active quest lookup | `QuestTrackerService` |
| navigation support | room-chat navigation handling + map generation services |

This architecture does not replace those services. It adds a deterministic broker
layer that chooses and coordinates them before any GM prompt fallback.

---

## Target Design Principles

1. **Deterministic first**
   - If backend state is sufficient to classify, resolve, validate, and execute a
     turn, no LLM should be required.
2. **LLM for narration and ambiguity**
   - The GM model remains valuable for scene prose, tone, ambiguity resolution, and
     exceptional/creative cases.
3. **Typed tools, not prompt-only tools**
   - Backend capabilities should be represented as typed tool definitions with known
     schemas, validators, executors, and receipt shapes.
4. **Execution before narration**
   - Mutations happen in the backend first; narration is generated from receipts.
5. **Stable entrypoint**
   - `RoomChatService` remains the public room-chat entrypoint during migration.
6. **NPC isolation**
   - NPC channels remain separate from GM orchestration and do not inherit the GM's
     full tool surface.

---

## Current Phase 1/2 State

Today, the implemented scaffold is narrower than the full target design:

- `RoomChatController` remains a transport-only entrypoint for player room chat.
- `GameMasterSubsystemService` is now the explicit server-side boundary for player room-chat routing and canonical room action submission.
- The subsystem currently classifies deterministic turn-control phrasing vs normal room-talk and emits a normalized routing envelope for downstream consumers.
- `RoomChatService` remains the stable room-chat entrypoint for broader GM narration/orchestration.
- `GmOrchestrationBrokerService` exists and owns authoritative quest/combat
  canonical action execution.
- `CanonicalActionRegistryService` now exposes broker-oriented tool metadata.
- Deterministic routing, argument resolution, and receipt-driven narration are not
  yet extracted into their own services.

---

## Future State Architecture

### High-level runtime flow

```text
Player room message
  -> RoomChatService [stable entrypoint]
    -> DeterministicTurnRouter [future]
      -> route category selected
      -> if deterministic:
           GmOrchestrationBrokerService
             -> ToolArgumentResolver [future]
             -> Validator
             -> Executor
             -> ToolExecutionReceiptBuilder [future]
           -> NarrationReceiptFormatter [future] or GM narration pass
      -> if ambiguous / unsupported:
           GM fallback path
             -> parse fallback result
             -> convert to broker receipt
             -> validate / execute
             -> narrate from receipt
```

### Target service roles

| Service | Responsibility |
|---|---|
| `RoomChatService` | stable public entrypoint, response shaping, migration host |
| `DeterministicTurnRouter` | future classifier that chooses deterministic path vs fallback |
| `GmOrchestrationBrokerService` | current broker scaffold; future owner of resolve -> validate -> execute -> receipt |
| `ToolCatalog` | future typed registry surface above canonical action metadata |
| `ToolArgumentResolver` | future resolver for natural language to authoritative ids |
| `ToolExecutionReceiptBuilder` | future standardized execution/narration handoff builder |
| `NarrationReceiptFormatter` | future deterministic narration or compact narration hints |

These are conceptual targets; naming may change at implementation time.

---

## `generateGmReply()` Subsystem Blueprint (current vs target)

`RoomChatService::generateGmReply()` is currently the highest-concentration orchestration seam.
It should be decomposed into explicit subsystems while preserving the existing response contract.

### Current internal stages (inside one method)

1. **Turn context + intent**
   - recent chat extraction
   - room NPC/profile gathering
   - direct-addressed NPC resolution
   - turn intent classification
2. **Deterministic short path**
   - `buildDeterministicGmResponse()`
   - role-boundary enforcement + safe fallback narrative
3. **Fallback generation path**
   - prompt artifact assembly
   - system prompt assembly (character/room context)
   - cache lookup
   - `generateRealityCheckedGmResponse()`
4. **Output sanitation + suggestion extraction**
   - `[CREATE_SUGGESTION]` parsing/stripping
   - narrative cleanup/sanitization
5. **Canonical execution**
   - broker canonical actions (`executeCanonicalAuthoritativeActions()`)
   - character/room state mutation + state diff
6. **Transitions + conversation sync**
   - navigation handling
   - explicit room conversation state sync
7. **Transcript projection + persistence**
   - visible GM narrative shaping
   - message payload assembly
   - DB persistence + session bridge

### Target subsystem decomposition

| Subsystem | Owns | Inputs | Outputs |
|---|---|---|---|
| `TurnIntentRouter` | route-family decision and deterministic eligibility | room turn context, actor context, message | `route_family`, `intent`, `handoff_reason` |
| `PromptContextAssembler` | fallback prompt/system context only | campaign+room+actor context, route metadata | `prompt_bundle` |
| `GmGenerationPolicy` | fallback generation/caching/reality-check policy | `prompt_bundle`, policy flags | normalized `gm_candidate` |
| `CanonicalExecutionPipeline` | resolve -> validate -> execute -> receipt | `intent`/`actions`, authoritative state | `execution_receipts`, `validation`, state deltas |
| `NavigationTransitionPipeline` | destination transition side effects | execution artifacts, dungeon state | navigation receipt + updated state |
| `TranscriptProjector` | player-visible response projection from receipts | narrative candidate + receipts + policy flags | stable `gm_message`, `state_diff`, metadata |
| `PersistenceCoordinator` | durable write + session bridge + log hooks | transcript payload + mutated state | persisted state + response envelope |

### Stable intermediate contracts

1. **Route envelope** (already present): `gm_subsystem_route_v1`
2. **Resolution outcome**: `resolved | needs_confirmation | not_possible | fallback_to_llm`
3. **Execution receipt** (single shape across deterministic routes):
   - route/tool/status
   - resolved arguments
   - validation result
   - execution mutations
   - optional clarification
   - narration hints
4. **Transcript projection payload**:
   - `gm_response`
   - `state_diff`
   - `canonical_actions`
   - navigation metadata
   - suppression/interjection flags

### Extraction order for `generateGmReply()`

1. Extract `TurnIntentRouter` + deterministic route result object.
2. Extract `PromptContextAssembler` (pure assembly, no execution).
3. Wrap existing fallback call into `GmGenerationPolicy` (no behavior change).
4. Move canonical execution + mutation into `CanonicalExecutionPipeline`.
5. Move navigation + transition writes into `NavigationTransitionPipeline`.
6. Move message shaping/persistence into `TranscriptProjector` + `PersistenceCoordinator`.
7. Keep `generateGmReply()` as a thin facade composing the subsystems until callers can be migrated.

### Proposed subsystem interfaces (contract-first)

```php
interface TurnIntentRouterInterface {
  public function route(RoomTurnContext $context): RouteDecision;
}

interface PromptContextAssemblerInterface {
  public function assemble(RoomTurnContext $context, RouteDecision $decision): PromptBundle;
}

interface GmGenerationPolicyInterface {
  public function generate(PromptBundle $bundle, RoomTurnContext $context): GmCandidate;
}

interface CanonicalExecutionPipelineInterface {
  public function execute(RoomTurnContext $context, GmCandidate $candidate): ExecutionPipelineResult;
}

interface NavigationTransitionPipelineInterface {
  public function apply(RoomTurnContext $context, ExecutionPipelineResult $result): NavigationResult;
}

interface TranscriptProjectorInterface {
  public function project(
    RoomTurnContext $context,
    GmCandidate $candidate,
    ExecutionPipelineResult $execution,
    ?NavigationResult $navigation
  ): TranscriptProjection;
}

interface PersistenceCoordinatorInterface {
  public function persist(
    RoomTurnContext $context,
    TranscriptProjection $projection
  ): PersistedTurnResult;
}
```

### Proposed DTO/shape contracts

#### `RouteDecision`

```php
[
  'route_family' => 'lookup_then_narrate|transactional|quest_progression|combat_transition|navigation|narrative_only|llm_fallback',
  'deterministic' => true|false,
  'handoff_reason' => 'string',
  'resolution_outcome' => 'resolved|needs_confirmation|not_possible|fallback_to_llm',
  'intent' => [...], // normalized canonical intent envelope
]
```

#### `PromptBundle`

```php
[
  'user_prompt' => 'string',
  'system_prompt' => 'string',
  'cache_key' => 'string|null',
  'context_meta' => [...],   // counters, room-entry flag, artifact sizes
  'guardrails_version' => 'string',
]
```

#### `GmCandidate`

```php
[
  'source' => 'deterministic|cache|reality_checked_generation',
  'narrative' => 'string',
  'actions' => [...],        // normalized action list
  'dice_rolls' => [...],
  'validation_errors' => [...],
  'policy_flags' => [
    'suppress_visible_gm_response' => true|false,
    'suppress_npc_interjections' => true|false,
  ],
]
```

#### `ExecutionPipelineResult`

```php
[
  'receipts' => [...],       // standard receipt model
  'canonical_results' => [...],
  'actions_applied' => [...],
  'state_diff' => [...],
  'updated_dungeon_data' => [...],
]
```

#### `TranscriptProjection`

```php
[
  'gm_message' => [...]|null,
  'state_diff' => [...]|null,
  'canonical_actions' => [...],
  'navigation' => [...]|null,
  'flags' => [
    'suppress_visible_gm_response' => true|false,
    'suppress_npc_interjections' => true|false,
  ],
]
```

### Contract conformance rules for subsystem extraction

1. **No silent contract drift:** every subsystem boundary must validate required fields and throw explicit contract exceptions on malformed shapes.
2. **No success-shaped fallbacks:** `resolution_outcome=not_possible` and `needs_confirmation` must surface explicitly, never be masked as executed.
3. **Deterministic first, fallback explicit:** `llm_fallback` is an explicit route outcome, not an implicit branch hidden inside execution code.
4. **Single receipt grammar:** all deterministic executions must return the shared receipt top-level fields.
5. **Stable outward response:** existing controller/frontend payload keys remain unchanged during migration.

### First implementation slice (recommended)

**Slice A: isolate routing + prompt assembly without behavior change**

- Add new services:
  - `src/Service/GmSubsystem/TurnIntentRouter.php`
  - `src/Service/GmSubsystem/PromptContextAssembler.php`
- Keep current execution in `generateGmReply()`, but replace inline routing/prompt assembly with subsystem calls.
- Add unit tests:
  - `tests/src/Unit/Service/GmSubsystem/TurnIntentRouterTest.php`
  - `tests/src/Unit/Service/GmSubsystem/PromptContextAssemblerTest.php`
- Keep a thin compatibility adapter in `RoomChatService` so existing callers and tests remain stable.

**Slice B: wrap fallback generation policy**

- Add:
  - `src/Service/GmSubsystem/GmGenerationPolicy.php`
- Move cache + `generateRealityCheckedGmResponse()` invocation policy behind this service.
- Preserve existing prompt, guardrails, and cache key semantics.

**Slice C: execution pipeline extraction**

- Add:
  - `src/Service/GmSubsystem/CanonicalExecutionPipeline.php`
  - `src/Service/GmSubsystem/NavigationTransitionPipeline.php`
  - `src/Service/GmSubsystem/TranscriptProjector.php`
- Move state mutation, navigation transition, and transcript projection out of `generateGmReply()` in sequence.

---

## Route Categories

Every room-chat turn should first be classified into one of these route families:

| Route | Description | LLM required |
|---|---|---|
| `narrative_only` | pure descriptive reply, atmosphere, non-mechanical response | usually yes |
| `lookup_then_narrate` | answerable from backend state (room roster, quests, exits, merchant context) | no |
| `transactional` | inventory transfer, currency transfer, item consumption | no when resolvable |
| `quest_progression` | quest touchpoint, delivery, turn-in, reward-ready result | no when resolvable |
| `combat_transition` | aggressive action that should start combat | no when resolvable |
| `navigation` | leave room / travel to a new place | no when resolvable |
| `llm_fallback` | ambiguous, unsupported, or highly interpretive turn | yes |

This classification replaces the current model where the GM prompt is often asked to
decide both the narrative and the mechanic.

---

## Tool Catalog Model

The current canonical action registry should evolve into a typed broker catalog.

Each tool definition should include:

- `tool_id`
- `category`
- `input_schema`
- `resolver`
- `validator`
- `executor`
- `receipt_schema`
- `narration_policy`
- `feature_flag`

### Initial tool families

#### Lookup tools

- `lookup_room_roster`
- `lookup_room_inventory`
- `lookup_active_quests`
- `lookup_location_exits`
- `lookup_merchant_context`

#### Resolution helpers

- `resolve_npc_target`
- `resolve_item_instance`
- `resolve_storage_owner`
- `resolve_quest_objective`
- `resolve_destination`
- `resolve_combat_target`

#### Mutation / orchestration tools

- `transfer_inventory`
- `transfer_currency`
- `consume_inventory`
- `apply_quest_touchpoint`
- `quest_turn_in`
- `navigate_to_location`
- `combat_initiation`

The lookup and resolution helpers are important because many of the current failures
come from forcing the GM to infer ids that the backend can resolve directly.

---

## Deterministic Resolution Rules

The broker must resolve player language into authoritative targets before execution.

### Resolution responsibilities

#### NPCs

- Prefer exact room entity matches.
- Allow alias/fuzzy matching only when it resolves to a single clear result.
- If multiple NPCs are plausible, return `needs_confirmation`.

#### Items

- Prefer exact `item_instance_id` from current inventory state.
- Support natural language references such as "the spellbook" only when the source
  owner has a single unambiguous match.
- If the player refers to a quest item category that maps to several instances,
  return `needs_confirmation`.

#### Quests

- Use active quest state, objective ids, item refs, npc refs, and quest metadata to
  resolve the intended objective.
- If the interaction can clearly map to a quest touchpoint, execute deterministically.
- If more than one objective could apply, return `needs_confirmation`.

#### Navigation and combat

- Navigation requires a resolvable destination and valid travel preconditions.
- Combat initiation requires a resolvable hostile target or enemy set in the current room.

### Standard resolution outcomes

Every resolution attempt should end in one of:

- `resolved`
- `needs_confirmation`
- `not_possible`
- `fallback_to_llm`

---

## Receipt Model

Every broker-handled turn should return a receipt with the same top-level shape,
regardless of which tool executed it.

### Conceptual receipt shape

```php
$receipt = [
  'route' => 'transactional',
  'tool' => 'transfer_inventory',
  'status' => 'executed', // resolved|needs_confirmation|rejected|executed|fallback
  'resolved_arguments' => [...],
  'validation' => [
    'valid' => TRUE,
    'errors' => [],
  ],
  'execution' => [
    'changed_entities' => [...],
    'changed_items' => [...],
    'changed_objectives' => [...],
  ],
  'clarification' => NULL,
  'narration_hints' => [...],
];
```

### Why receipts matter

Receipts become the contract between deterministic execution and narration. That lets
the GM narrate what actually happened instead of inventing action payloads first.

---

## LLM Usage in the Target Model

### LLM not required

These should resolve without the GM model once migrated:

- "Who is in this room?"
- "What quests do I still have with Eldric?"
- "I hand Eldric the wine bottles."
- "I drink the healing potion."
- "I leave the tavern and head to the market."
- "I attack Gribbles."

### LLM still appropriate

The GM model remains appropriate for:

- atmospheric narration,
- tone-rich consequence narration,
- ambiguous player intent,
- unsupported or novel player actions,
- adjudication when the rules or the intent are not yet mapped to broker logic.

### Key architectural rule

The fallback LLM path should produce either:

1. narrative only, or
2. a broker-compatible intermediate action that is still validated and executed by
   deterministic backend code.

---

## GM vs NPC Capability Boundary

This architecture does **not** widen NPC authority.

### GM

The GM remains responsible for:

- scene framing,
- rules adjudication,
- continuity,
- narration of outcomes,
- ambiguous-case handling.

### NPCs

NPCs remain responsible for:

- their own dialogue,
- their own reactions,
- their own perspective and knowledge limits.

### Explicit boundary

NPC private-channel logic should stay outside the broker rollout. If NPC tools are
ever introduced later, they must be:

- read-only or self-scoped,
- explicitly separated from GM orchestration tools,
- unable to mutate broad world state without a GM/server-owned path.

---

## Migration Plan

The migration should be incremental and behavior-safe.

### Implementation plan (execution phases + gates)

| Phase | Goal | Primary code seams | Verification gate | Exit criteria |
|---|---|---|---|---|
| 0 | Freeze baseline behavior and contracts | `RoomChatService::generateGmReply()`, GM route envelope outputs | Existing unit + contract tests green | Baseline snapshot captured; no net behavior change |
| 1 | Extract routing boundary | `GameMasterSubsystemService`, new `TurnIntentRouter` | Route envelope contract tests | Route decision is subsystem-owned and contract-stable |
| 2 | Extract prompt assembly | new `PromptContextAssembler` + adapter in `RoomChatService` | Prompt assembly unit tests + unchanged integration outputs | Prompt/system bundle generated outside `generateGmReply()` |
| 3 | Extract fallback generation policy | new `GmGenerationPolicy` wrapping cache + reality-check | Fallback policy tests + no response shape drift | Fallback path isolated behind one service |
| 4 | Extract canonical execution pipeline | new `CanonicalExecutionPipeline` | Receipt-shape tests + canonical action regression tests | Resolve/validate/execute/receipt no longer inline in `generateGmReply()` |
| 5 | Extract navigation + transcript/persistence | `NavigationTransitionPipeline`, `TranscriptProjector`, `PersistenceCoordinator` | Navigation + transcript contract tests | `generateGmReply()` reduced to orchestration facade |
| 6 | Prompt contract reduction + cleanup | Prompt templates + fallback-specific mechanics instructions | Deterministic bypass coverage + fallback safety tests | Common deterministic routes no longer depend on prompt-authored mechanics |

### Phase deliverables (required)

1. **Code deliverables:** new subsystem service(s), wiring in `dungeoncrawler_content.services.yml`, and thin compatibility adapter in `RoomChatService`.
2. **Contract deliverables:** explicit DTO/array shape assertions for route decisions, receipts, and transcript projection.
3. **Regression deliverables:** existing route/contract tests continue to pass; added subsystem tests cover new boundaries.
4. **Documentation deliverables:** architecture doc updated per phase with landed service ownership and remaining seams.

### Phase progression policy

- No phase advances until its verification gate is green.
- No outward API/response shape changes are allowed before explicit compatibility signoff.
- Contract violations must fail explicitly (no silent fallback-to-success behavior).

### Phase 0 — Document and stabilize

- Record the exact current room-chat flow.
- Record current authoritative services and action classes.
- Identify which routes are already deterministic in practice.

### Phase 1 — Broker facade, no behavior change

- Introduce broker and router interfaces behind `RoomChatService`.
- Keep current prompt-based JSON action path active.
- Move registry metadata toward typed tool definitions.

### Phase 2 — Deterministic read-only routes

First migrations:

- room roster queries,
- quest lookup queries,
- merchant context queries,
- exit / destination lookup queries.

Success bar:

- These queries bypass the GM model when backend state is sufficient.

### Phase 3 — Deterministic transactions and quest progression

First migrations:

- inventory transfer,
- currency transfer,
- item consumption,
- quest delivery / turn-in,
- direct NPC transaction handling.

Success bar:

- The GM no longer authors primary transaction JSON for these flows.

### Phase 4 — Deterministic navigation and combat transitions

First migrations:

- `navigate_to_location`
- `combat_initiation`

Success bar:

- Transition actions are resolved and validated in the broker before narration.

### Phase 5 — Prompt contract reduction

- Remove large prompt-time JSON instructions from the default path once deterministic
  routing covers the common routes.
- Keep prompt-described mechanics only for fallback or unsupported cases.

---

## Backward-Compatibility Rules

During migration:

- keep `RoomChatService` as the stable top-level service,
- preserve current response shapes returned to the frontend,
- feature-flag new routing paths,
- retain current parsing/retry behavior until deterministic replacements are proven,
- avoid rewriting NPC dialogue paths as part of the GM broker rollout.

---

## Testing Strategy

Implementation should add focused coverage for:

- turn routing decisions,
- resolver ambiguity handling,
- deterministic lookup replies,
- transaction receipts,
- quest progression receipts,
- navigation and combat transition receipts,
- LLM bypass on deterministic paths,
- fallback behavior when deterministic routing cannot safely resolve intent.

Existing orchestration and combat lifecycle tests should remain valid during the
migration because the public room-chat surface should stay stable.

---

## Non-Goals

This architecture is **not** proposing:

- a full rewrite of `RoomChatService` in one pass,
- broad NPC tool access,
- removal of the GM model from narration,
- replacement of authoritative mechanics services already working today.

---

## Related Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — module-wide architecture overview
- [CHAT_AND_NARRATION_ARCHITECTURE.md](CHAT_AND_NARRATION_ARCHITECTURE.md) — chat sessions and narration pipeline
- [GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md](GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md) — authoritative gameplay process flow
- [GM_INSTRUCTIONS.md](GM_INSTRUCTIONS.md) — GM authority, grounding, quest touchpoint doctrine
