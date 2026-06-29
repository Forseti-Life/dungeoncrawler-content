# Dungeoncrawler GM Backstop Subsystem — Architecture Design

## Problem statement

The current Dungeoncrawler architecture has three strong ingredients:

1. a deterministic engine (`GameCoordinatorService` + phase handlers),
2. a strong GM prompt identity (`PromptManager::getBaseSystemPrompt()`),
3. and several GM-adjacent orchestration helpers (`RoomChatService`, `GmOrchestrationBrokerService`, `CanonicalActionRegistryService`, `GameplayActionProcessor`, `NarrationEngine`).

But those ingredients are not yet assembled into one explicit **Game Master subsystem**. Today, the GM mostly appears as a fallback embedded inside `RoomChatService`. That makes the architecture good at narration and some action generation, but weaker than it should be at one of the core tabletop GM jobs:

- interpret player intent when the deterministic engine cannot decide,
- choose the correct workflow/tool path,
- preserve grounded continuity,
- and hand authoritative actions back to server-enforced execution.

The target architecture is:

- **deterministic engine first**
- **GM subsystem second**
- **authoritative execution last**

## External GM role baseline

### Pathfinder

Pathfinder’s “first rule” frames the GM as the one who uses the rules to make the game work for the table and tell the stories the group wants to tell. That is not just a narrator role; it is an adjudication and orchestration role.

### D&D / Dungeon Master baseline

At a high level, the Dungeon Master role in D&D is the layer that:

1. describes the world,
2. interprets what players are trying to do,
3. adjudicates uncertain outcomes,
4. invokes the correct rules/workflows,
5. and preserves continuity, consequences, and agency.

That means the GM is not just a prose generator. The GM is the **backstop adjudication subsystem** sitting above deterministic rule execution.

## Current architecture assessment

## What already exists

### Deterministic engine

- `GameCoordinatorService`
- `EncounterPhaseHandler`
- other phase handlers

These own state transitions, turn order, legality, mutations, event logging, and persistence.

### GM identity and prompting

- `PromptManager::getBaseSystemPrompt()`
- `RoomChatService::generateRealityCheckedGmResponse()`
- `GameplayActionProcessor::buildEnhancedSystemPrompt()`

These provide a real GM identity, room inventory grounding, actor context, and action-validation retry loop.

### GM-adjacent orchestration pieces

- `GmOrchestrationBrokerService`
- `CanonicalActionRegistryService`
- `NarrationEngine`

These already look like subsystem pieces, but they do not yet form the single explicit orchestration layer.

## Main architectural gap

The GM is still spread across:

- controller shortcuts,
- deterministic room-intent classification,
- deterministic GM short paths,
- prompt assembly,
- canonical action validation,
- broker execution,
- narration persistence.

That means the **GM exists conceptually**, but **not yet as one clean subsystem boundary**.

## Obvious gaps

### 1. No single ownership boundary for GM decisions

Right now, GM-like decisions are split across:

- controller text shortcuts,
- `RoomChatService` intent classification,
- deterministic GM helper branches,
- prompt assembly,
- action validation,
- broker execution,
- transcript persistence.

That means no single service can honestly answer:

- what the GM is allowed to decide,
- what workflows the GM can route into,
- what deterministic work already happened,
- and what final output contract the GM must return.

### 2. No explicit GM tool/workflow contract

The current implementation has real capabilities, but they are scattered:

- canonical action registry entries,
- broker handlers,
- gameplay validator logic,
- coordinator transitions,
- quest touchpoints,
- navigation handling,
- NPC dialogue handoff,
- narration persistence.

Those capabilities are not surfaced as one authoritative GM-facing tool/workflow list. That creates two risks:

1. the fallback prompt can underspecify what the GM may do,
2. future developers can add workflows without exposing them consistently to the GM layer.

### 3. Prompt language still underspecifies the GM backstop role

The visible fallback prompt still leans heavily toward:

- concise environmental narration,
- occasional action JSON,
- room-scene grounding.

It does **not yet clearly say**:

- deterministic systems already ran first,
- the GM is the ambiguity backstop,
- the GM interprets player intent,
- the GM chooses the correct workflow,
- and the GM returns canonical proposals for authoritative execution.

### 4. No normalized GM output envelope

The GM currently produces a mix of:

- narrative text,
- action arrays,
- dice rolls,
- validation retry paths,
- broker side effects,
- transcript payloads.

But there is no single normalized subsystem contract like:

- interpreted intent
- workflow selection
- canonical proposals
- validation receipts
- final transcript directives

Without that envelope, GM behavior stays coupled to `RoomChatService`.

### 5. Deterministic/GM handoff is not observable enough

We should be able to answer, for any player message:

- was it fully resolved deterministically?
- if not, why not?
- which GM workflow was selected?
- what proposals were emitted?
- what validator/broker accepted or rejected them?

That telemetry boundary is not yet explicit.

### 6. NPC dialogue and GM adjudication are still too interwoven

The architecture now distinguishes GM narration from NPC speech better than before, but the handoff is still too implicit.

The GM subsystem should explicitly decide among:

- referee narration only
- direct NPC handoff
- merchant workflow
- quest workflow
- combat workflow
- canonical player action proposal

Today those transitions are distributed across several helpers rather than owned by one router.

### 7. Controller still knows too much

The room-chat controller still contains gameplay-intent logic. Even when that logic is deterministic, it weakens the architectural story because transport and gameplay interpretation are mixed.

If the GM is truly a subsystem, controllers should know:

- auth / request shape,
- streaming / transport concerns,
- error envelope formatting,

and nothing about gameplay-intent semantics.

### 8. Migration risk: prompt refactor without service extraction

The easiest wrong move would be to rewrite prompts while leaving architecture untouched.

That would improve wording but not subsystem clarity. We need both:

- better GM instructions,
- and cleaner service boundaries.

## Suggested execution slices

### Slice 1 — ownership and contracts

Goal:

- define the exact subsystem boundary and contracts before moving logic

Deliverables:

- `GameMasterSubsystem` facade contract
- GM request/response envelope
- GM tool/workflow contract schema
- deterministic handoff reason codes

### Slice 2 — controller cleanup

Goal:

- remove gameplay interpretation from transport/controller layer

Deliverables:

- controller delegates room-chat player messages to one orchestration entrypoint only
- current direct text shortcuts move behind deterministic gateway / GM subsystem

### Slice 3 — deterministic gateway extraction

Goal:

- isolate deterministic-first handling as a separate pre-GM layer

Deliverables:

- exact-action detection
- stable room query resolution
- direct mechanical short paths
- explicit handoff payload to GM subsystem when unresolved

### Slice 4 — GM intent interpreter + router

Goal:

- centralize interpretation and workflow selection

Deliverables:

- interpreted intent families
- workflow selection matrix
- explicit NPC/merchant/quest/combat/navigation/adjudication routing

### Slice 5 — GM tool context builder

Goal:

- provide one authoritative GM-facing description of available tools/workflows

Deliverables:

- canonical tool/workflow registry projection for prompts
- separation between:
  - deterministic-owned surfaces
  - GM-routable workflows
  - execution-only downstream systems

### Slice 6 — proposal / validation / execution bridge

Goal:

- normalize how GM proposals become authoritative outcomes

Deliverables:

- proposal envelope
- validation results
- broker receipts
- final transcript/state-sync composition

### Slice 7 — observability and regression coverage

Goal:

- make subsystem decisions debuggable and testable

Deliverables:

- deterministic-vs-GM routing telemetry
- workflow-selection telemetry
- prompt-context regression tests
- canonical action / broker receipt tests

## Target subsystem boundary

## Proposed top-level boundary

Introduce an explicit service boundary:

- `GameMasterSubsystem`

Responsibilities:

1. receive a player message that the deterministic engine could not fully resolve,
2. interpret intent,
3. choose the correct workflow,
4. produce canonical action/workflow proposals plus grounded narration,
5. hand those proposals to deterministic validators/brokers,
6. return validated response payloads for transcript and client sync.

Non-responsibilities:

- direct state mutation without validation,
- bypassing `GameCoordinatorService`,
- bypassing canonical action registry / authoritative brokers.

## Proposed internal components

### 1. GMIntentInterpreter

Purpose:

- takes player message + current state + available workflows
- returns interpreted intent

Output shape:

- `intent_family`
- `confidence`
- `target_refs`
- `needs_workflow`
- `narrative_mode`
- `requires_llm`

Notes:

- deterministic classifiers may still run first for obvious cases,
- but once the message is outside deterministic certainty, this interpreter owns interpretation.

### 2. GMWorkflowRouter

Purpose:

- map interpreted intent to the correct subsystem workflow

Examples:

- referee-only narration
- NPC dialogue handoff
- canonical encounter action
- combat initiation
- quest progression / quest turn-in
- navigation / room transition
- merchant / inventory / item workflow

### 3. GMToolContextBuilder

Purpose:

- assemble the full tool/workflow context the GM backstop is allowed to use

This is the key gap in the current prompt layer. The fallback GM must know:

- what workflows exist,
- what canonical actions are legal,
- what broker routes exist,
- what is deterministic vs what must be interpreted,
- what it may propose,
- and what it may never mutate directly.

### 4. GMActionProposalEngine

Purpose:

- LLM-assisted interpretation and proposal generation for non-deterministic cases

Outputs:

- grounded narration
- canonical action proposals
- workflow routing proposals
- uncertainty markers / assumptions

### 5. GMValidationAndBrokerBridge

Purpose:

- pass GM proposals through:
  - `CanonicalActionRegistryService`
  - `GameplayActionProcessor`
  - `GmOrchestrationBrokerService`
  - `GameCoordinatorService`

This keeps the GM authoritative on interpretation but not on execution legality.

### 6. GMTranscriptComposer

Purpose:

- unify final user-visible result:
  - referee narration
  - action receipts
  - workflow outcomes
  - state sync payload

## Request flow

## Deterministic-first flow

1. `RoomChatController` receives player room message.
2. Transport layer only normalizes/authenticates request.
3. Deterministic engine checks for:
   - exact canonical action rail requests
   - unambiguous direct mechanical intents
   - stable short-path room queries
4. If fully resolved, return deterministic result.
5. If not fully resolved, hand off to `GameMasterSubsystem`.

## GM backstop flow

1. `GMIntentInterpreter` interprets the player's goal.
2. `GMWorkflowRouter` selects the appropriate workflow family.
3. `GMToolContextBuilder` builds the full allowed GM tool/workflow context.
4. `GMActionProposalEngine` produces narration + canonical proposals.
5. `GMValidationAndBrokerBridge` validates and executes through authoritative services.
6. `GMTranscriptComposer` returns transcript-safe output.

## Prompt design requirements

The fallback GM prompt should explicitly say:

1. deterministic systems already resolved everything they could resolve with certainty,
2. you are the GM backstop for ambiguity and player-intent interpretation,
3. your role is to:
   - interpret the player’s goal,
   - choose the correct workflow,
   - propose canonical actions or referee narration,
   - and route through authoritative server workflows,
4. you have access to the full listed GM workflow surface,
5. you do not mutate state directly; authoritative execution happens downstream.

## Tool/workflow surface the GM should receive

At minimum:

- current encounter turn data
- legal canonical actions
- room inventory / room entities
- NPC dialogue handoff path
- combat initiation route
- navigation route
- quest progression route
- merchant / transaction route
- room interaction / search / hazard paths
- explicit “referee narration only” option when no stateful workflow applies

## Code seams to migrate

### Move out of controller

- `RoomChatController::buildDirectRoomTurnControlIntent()`

Reason:

- the controller should be transport only.

### Move behind GM subsystem boundary

- `RoomChatService::classifyRoomTurnIntent()`
- `RoomChatService::buildDeterministicGmResponse()`
- `RoomChatService::generateRealityCheckedGmResponse()`

Reason:

- these are currently the de facto GM interpretation/orchestration core.

### Promote existing helpers into subsystem collaborators

- `GmOrchestrationBrokerService`
- `CanonicalActionRegistryService`
- `GameplayActionProcessor`
- `NarrationEngine`

Reason:

- these already represent meaningful parts of the desired subsystem.

## Suggested service graph

- `RoomChatController`
  - `DeterministicChatGateway`
  - `GameMasterSubsystem`
    - `GMIntentInterpreter`
    - `GMWorkflowRouter`
    - `GMToolContextBuilder`
    - `GMActionProposalEngine`
    - `GMValidationAndBrokerBridge`
    - `GMTranscriptComposer`
  - `GameCoordinatorService`

## Migration plan

### Phase 1 — clarify ownership

- make `RoomChatController` transport-only
- document deterministic-first / GM-backstop-second contract
- formalize the GM prompt/tool-context contract

### Phase 2 — extract subsystem shell

- introduce `GameMasterSubsystem` facade
- move current fallback entry from `RoomChatService` into that facade
- keep existing internal helpers behind the facade initially

### Phase 3 — move interpretation

- migrate intent classification and deterministic GM short paths into subsystem-owned interpreter/router components

### Phase 4 — unify tool context

- build one GM tool/workflow context builder instead of scattered prompt text and partial action descriptions

### Phase 5 — unify transcript and execution path

- centralize:
  - action proposal
  - broker execution
  - final GM message composition

## Acceptance criteria

- GM exists as an explicit subsystem boundary.
- Controller is transport-only.
- Deterministic-first / GM-backstop-second handoff is explicit.
- GM prompt explicitly defines backstop interpretation + workflow routing duties.
- GM receives a full explicit tool/workflow context.
- Authoritative legality/execution remains server-enforced.

## Recommendation

This should be treated as a **P1 architecture refactor**, not a small prompt tweak.

The prompt text needs improvement, but the bigger issue is structural:

- right now the GM is a powerful fallback path,
- but not yet the explicit subsystem that owns ambiguous intent interpretation and workflow routing.

That is the boundary to build next.
