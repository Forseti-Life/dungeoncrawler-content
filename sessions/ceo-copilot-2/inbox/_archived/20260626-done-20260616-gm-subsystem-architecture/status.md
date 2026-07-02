# Status

- status: done
- created_at: 2026-06-16T12:26:30+00:00
- current_phase: completed and archived

## Notes

### 2026-06-26 - completion closeout
- Confirmed all planned extraction phases for the GM subsystem architecture item are complete.
- Closed this work item and moved it to archived inbox state.

Created from Board direction after reviewing the current Dungeoncrawler room-chat/GM fallback path. The current codebase has GM-oriented prompt identity and supporting services, but the GM is still embedded inside `RoomChatService` rather than centralized as an explicit backstop subsystem above deterministic gameplay resolution.

### 2026-06-19 - slice 2 complete
- Expanded the explicit `GameMasterSubsystemService` boundary beyond direct room-chat transport so it now returns a normalized route / workflow / intent envelope for player room messages.
- Preserved the existing room-chat response payload while exposing deterministic-first routing metadata (`workflow`, `route`, `deterministic`, resolved room, actor, and canonical intent shape) that later slices can extend into broader GM interpreter and workflow routing.
- Landed focused unit coverage for both subsystem envelope generation and controller passthrough behavior.

### 2026-06-26 - ownership transfer
- Work item moved from `sessions/architect-copilot/inbox/` to `sessions/ceo-copilot-2/inbox/` per Board direction.
- CEO now owns completion and architecture review for this item.

### 2026-06-26 - architecture review start (phase 1 baseline)
- Confirmed transport boundary now exists in `RoomChatController` with player room chat routed through `GameMasterSubsystemService::handlePlayerRoomChat()`.
- Confirmed normalized GM subsystem envelope contract is live (`gm_subsystem_route_v1`) with deterministic vs GM-backstop route metadata in `GameMasterSubsystemService`.
- Confirmed deterministic authoritative execution remains concentrated in `GameCoordinatorService` + `GmOrchestrationBrokerService`, while `RoomChatService::generateGmReply()` still carries mixed concerns (intent classification, prompt assembly, fallback generation, canonical execution orchestration, persistence, transcript shaping).
- Review focus for next slice: extract deterministic route/resolve/receipt orchestration seams from `RoomChatService::generateGmReply()` into broker-oriented services without weakening server-authoritative legality checks.

### 2026-06-26 - architecture review phase 2 (contract conformance + hardening)
- Ran subsystem contract checks and found one real contract mismatch plus one brittle contract test.
- **Contract mismatch fixed:** `GameMasterSubsystemService::buildPlayerRoomChatRouteEnvelope()` advertised `defer_npc_interjections: TRUE` while runtime path in `handlePlayerRoomChat()` forced immediate NPC resolution (`FALSE`). Envelope now matches runtime behavior (`FALSE`) to keep route metadata truthful.
- **Contract test hardening fixed:** `tests/player_free_chat_contract_test.js` had brittle string checks for inline literal route values. Updated checks to assert constant definitions plus usage (`ROUTE_FREE_PLAYER_ROOM_CHAT`, `ROUTE_DETERMINISTIC_TURN_CONTROL`) so contract tests stay valid under constant-backed implementation.
- Post-fix contract checks are green for GM subsystem route/lazy-loading coverage.

### 2026-06-26 - architecture review phase 3 (generateGmReply subsysteming blueprint)
- Mapped current `RoomChatService::generateGmReply()` flow as a mixed-concern orchestrator with seven embedded responsibilities: turn classification, deterministic short path, prompt/context assembly, LLM generation policy + cache, canonical action execution + state mutation, navigation transition, and transcript/session persistence.
- Target subsystem direction confirmed: keep `RoomChatService` as stable facade while extracting deterministic-first route/resolve/receipt orchestration behind dedicated services and reducing prompt-time mechanics contract to fallback-only paths.
- Next implementation cut-lines identified: `TurnIntentRouter`, `PromptContextAssembler`, `GmGenerationPolicy`, `CanonicalExecutionPipeline`, `NavigationTransitionPipeline`, and `TranscriptProjector` with stable outward payload contract.

### 2026-06-26 - architecture review phase 4 (documentation landing)
- Landed concrete `generateGmReply()` subsystem architecture in `dungeoncrawler-content/DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md`.
- Added explicit current-stage breakdown, target subsystem service graph, stable intermediate contracts (route/resolution/receipt/projection), and extraction order that preserves response compatibility while reducing mixed concerns.

### 2026-06-26 - architecture review phase 5 (implementation plan)
- Added an explicit execution-phase implementation plan with gates in `DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md`:
  - phase-by-phase goals,
  - primary code seams,
  - verification gates,
  - exit criteria,
  - progression policy (no phase advancement without green gate and explicit contract conformance).

### 2026-06-26 - implementation phase 1 (turn-intent router seam)
- Added `src/Service/GmSubsystem/TurnIntentRouter.php` as the first extracted subsystem from `generateGmReply()`.
- Wired router service into DI (`dungeoncrawler_content.gm_turn_intent_router`) and injected into `RoomChatService`.
- `generateGmReply()` now consumes explicit route decision metadata (`route_family`, `resolution_outcome`) from the router while preserving existing behavior.
- Added contract coverage: `tests/gm_turn_intent_router_contract_test.js`.
- Verified contract suite remains green (router contract + existing free-chat/lazy-controller contract tests).

### 2026-06-26 - implementation phase 2 (prompt context assembler seam)
- Added `src/Service/GmSubsystem/PromptContextAssembler.php` to own fallback user-prompt assembly for `generateGmReply()`.
- Wired assembler service into DI (`dungeoncrawler_content.gm_prompt_context_assembler`) and injected into `RoomChatService`.
- Replaced inline prompt concatenation block in `generateGmReply()` with `PromptContextAssembler::assemble()` while preserving guardrails text and debug metadata emission.
- Added contract coverage: `tests/gm_prompt_context_assembler_contract_test.js`.
- Verified expanded contract suite remains green.

### 2026-06-26 - implementation phase 3 (generation policy seam)
- Added `src/Service/GmSubsystem/GmGenerationPolicy.php` to own cache-aware fallback generation policy for `generateGmReply()`.
- Wired generation policy service into DI (`dungeoncrawler_content.gm_generation_policy`) and injected into `RoomChatService`.
- Replaced inline cache-hit/miss + fallback generation orchestration block with `GmGenerationPolicy::resolve(...)`, preserving behavior while isolating policy logic.
- Added contract coverage: `tests/gm_generation_policy_contract_test.js`.
- Verified full GM subsystem contract suite remains green.

### 2026-06-26 - implementation phase 4 (canonical execution pipeline seam)
- Added `src/Service/GmSubsystem/CanonicalExecutionPipeline.php` to own broker-backed canonical authoritative action execution orchestration.
- Wired pipeline service into DI (`dungeoncrawler_content.gm_canonical_execution_pipeline`) and injected into `RoomChatService`.
- Replaced inline canonical broker execution block in `generateGmReply()` with `CanonicalExecutionPipeline::execute(...)` while preserving payload semantics (`actions`, `canonical_results`, `validation_errors`, updated dungeon data).
- Added contract coverage: `tests/gm_canonical_execution_pipeline_contract_test.js`.
- Verified full GM subsystem contract suite remains green after extraction.

### 2026-06-26 - implementation phase 5 (state mutation pipeline seam)
- Added `src/Service/GmSubsystem/StateMutationPipeline.php` to own character/room mutation + state diff construction policy.
- Wired state mutation pipeline into DI (`dungeoncrawler_content.gm_state_mutation_pipeline`) and injected into `RoomChatService`.
- Replaced inline mutation/diff block in `generateGmReply()` with `StateMutationPipeline::apply(...)` while preserving outputs (`state_diff`, char/room diffs, updated dungeon data).
- Added contract coverage: `tests/gm_state_mutation_pipeline_contract_test.js`.
- Verified expanded GM subsystem contract suite remains green.

### 2026-06-26 - implementation phase 6 (navigation transition pipeline seam)
- Added `src/Service/GmSubsystem/NavigationTransitionPipeline.php` to own navigation transition orchestration (navigation handling, room-index rebase, destination-arrival append, transition recording).
- Wired navigation transition pipeline into DI (`dungeoncrawler_content.gm_navigation_transition_pipeline`) and injected into `RoomChatService`.
- Replaced inline navigation transition block in `generateGmReply()` with `NavigationTransitionPipeline::apply(...)` while preserving navigation result and state update semantics.
- Added contract coverage: `tests/gm_navigation_transition_pipeline_contract_test.js`.
- Verified full GM subsystem contract suite remains green.

### 2026-06-26 - implementation phase 6a (navigation seam review/refactor)
- Reviewed the navigation extraction seam and tightened callback wiring by replacing inline anonymous closures with explicit method-reference callbacks in `RoomChatService`.
- Kept a single adapter helper (`resolveNavigationTransitionRoomIndex()`) where signature translation is required, and removed redundant pass-through wrappers in favor of direct method references (`handleNavigationActions`, `appendDestinationArrivalNarration`, `recordLocationTransition`).
- Re-ran navigation/state/canonical + player free-chat contract checks; all green.

### 2026-06-26 - implementation phase 7 (transcript persistence/session bridge seam)
- Added `src/Service/GmSubsystem/GmTranscriptPersistencePipeline.php` to own GM transcript persistence and session bridge side effects.
- Wired transcript persistence pipeline into DI (`dungeoncrawler_content.gm_transcript_persistence_pipeline`) and injected into `RoomChatService`.
- Replaced inline projection/persistence/session-bridge block in `generateGmReply()` with `GmTranscriptPersistencePipeline::persistVisibleReply(...)`, preserving message payload shape and persistence semantics.
- Added contract coverage: `tests/gm_transcript_persistence_pipeline_contract_test.js`.
- Verified full GM subsystem contract suite remains green.

### 2026-06-26 - implementation phase 8 (transcript projection seam)
- Added `src/Service/GmSubsystem/GmTranscriptProjector.php` to own visibility flag and encounter-prefix projection policy for GM transcript output.
- Wired transcript projector into DI (`dungeoncrawler_content.gm_transcript_projector`) and injected into `RoomChatService`.
- Replaced inline visible narrative projection block in `generateGmReply()` with `GmTranscriptProjector::project(...)`, keeping persistence/session-bridge work in the dedicated persistence pipeline.
- Added contract coverage: `tests/gm_transcript_projector_contract_test.js`.
- Verified full GM subsystem contract suite remains green after projector + persistence split.

### 2026-06-26 - implementation phase 9 (narrative post-processing seam)
- Added `src/Service/GmSubsystem/GmNarrativePostProcessor.php` to own generated narrative post-processing:
  - `[CREATE_SUGGESTION]` parsing + backlog side effects,
  - player-visible narrative cleanup/sanitization,
  - cache writeback policy for clean narrative-only responses.
- Wired narrative post-processor into DI (`dungeoncrawler_content.gm_narrative_post_processor`) and injected into `RoomChatService`.
- Replaced inline suggestion extraction/sanitize/cache block in `generateGmReply()` with `GmNarrativePostProcessor::process(...)`.
- Added contract coverage: `tests/gm_narrative_post_processor_contract_test.js`.
- Verified complete GM subsystem contract suite remains green.
