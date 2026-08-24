# Dungeon Crawler Content Module

**Module**: `dungeoncrawler_content`  
**Type**: Drupal custom module  
**Drupal**: 10.3+ / 11.x  
**PHP**: 8.1+

This module is the game runtime for DungeonCrawler: campaign orchestration, room/campaign state, encounter/combat flow, chat/narration, procedural generation, quest/storyline systems, and PF2e character/inventory services.

## 1. Current Architecture Snapshot

The current implementation is an **encounter-first runtime** with a unified coordinator entry point:

- Gameplay actions are orchestrated through `GameCoordinatorService`.
- Active runtime phase is `encounter`; `exploration` is not an active runtime phase.
- Room chat + canonical action execution are server-authoritative for state mutation.
- Campaign/runtime records are persisted to campaign tables (`dc_campaign_*`) and canonical content tables (`dungeoncrawler_content_*`).

Primary action entry point:

- `POST /api/game/{campaign_id}/action`

Legacy combat mutation endpoints under `/api/combat/*` are non-canonical support surfaces; player action flow is coordinator-driven.

## 1A. Hexmap UI API Ownership Contract

Hexmap V2 panels must map directly to authoritative read/write API families.

| UI surface | Read authority | Mutation authority |
|---|---|---|
| Action Rail | `/api/map/visual-state`, `/api/game/{campaign_id}/state`, `/api/character/{character_id}/actions` | `/api/game/{campaign_id}/action`, `/api/character/{character_id}/cast-spell` |
| Combat HUD / initiative | `/api/game/{campaign_id}/state`, `/api/game/{campaign_id}/events` | `/api/game/{campaign_id}/action` |
| Character sheet | `/api/character/{character_id}/state`, `/api/campaign/{campaign_id}/relationships/matrix` | character-specific mutation endpoints |
| Inventory | `/api/inventory/{owner_type}/{owner_id}` | `/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location` and inventory mutation routes |
| Room chat / channels | `/api/campaign/{campaign_id}/room/{room_id}/chat`, `/channels...`, session chat APIs | same family |
| Room view | `/api/campaign/{campaign_id}/room/{room_id}/view-image` | none |
| Merchant | `/api/campaign/{campaign_id}/room/{room_id}/merchant/{merchant_ref}` and `/search` | `/transaction` |
| Quest journal | `/api/campaign/{campaign_id}/character/{character_id}/quest-journal` or `/api/campaign/{campaign_id}/quest-journal` | quest lifecycle routes |

Rules:

- No panel may null out or replace API-owned actor identity with panel-local shell state.
- Local selection/highlight state may refine presentation, but not canonical legality, turn owner, or actor identity.
- If a panel cannot name its read authority and mutation authority, its state ownership is incomplete.

## 2. Subsystem Map (Current)

| Subsystem | Core services/controllers | Responsibility |
|---|---|---|
| Gameplay Orchestration | `GameCoordinatorService`, `EncounterPhaseHandler`, `GameCoordinatorController` | Canonical action routing, turn/phase flow, room transition handling |
| Combat & Encounter Engine | `CombatEngine`, `ActionProcessor`, `RulesEngine`, `HPManager`, `ConditionManager`, `ReactionHandler`, `CombatApiController` | PF2e encounter lifecycle, action resolution, conditions, initiative, combat state |
| Chat, Session & Narration | `RoomChatService`, `ChatSessionManager`, `ChatChannelManager`, `NarrationEngine`, `AiGmService`, `RoomChatController`, `ChatSessionController` | Room dialogue, session hierarchy, narration/event feed |
| Content Generation | `CampaignInitializationService`, `DungeonGeneratorService`, `RoomGeneratorService`, `MapGeneratorService`, `EntityPlacerService`, `EncounterGeneratorService` | Dungeon/room/entity generation and campaign materialization |
| Quest & Storyline | `StorylineQuestLifecycleService`, `QuestGeneratorService`, `QuestTrackerService`, `QuestValidatorService`, `QuestTouchpointService`, `StorylineGenerationService`, `StorylineRealizationService`, `StorylineManagerService` | Quest lifecycle, storyline creation/activation/journals, contract validation |
| Character & Progression | `CharacterManager`, `CharacterStateService`, `CharacterLevelingService`, `CharacterCreationGmService`, character controllers/forms | Character creation, progression, runtime character state |
| Inventory & Equipment | `InventoryManagementService`, `ContainerManagementService`, `GameObjectInventoryService`, `EquipmentCatalogService`, inventory APIs | Item/container management and equipment interactions |
| NPC / Institutions / Psychology | `NpcService`, `NpcSheetGenerationService`, `NpcPsychologyService`, institution services | NPC generation/runtime data, psychology, campaign institutions/factions |
| Validation & Contract Enforcement | `StateValidationService`, `ImpactContractService`, validator/report controllers | Hard validation of canonical content contracts and runtime consistency |
| Generated Assets & Media | image generation services + generated image controllers | Portraits, room/terrain/sprite image generation and retrieval |

## 2A. Generator-to-Validator Mapping (Authoritative)

Use this as the canonical pairing when touching generation code.

| Generator / generation policy | Validator equivalent | Notes |
|---|---|---|
| `StorylineGenerationService` | `StorylineManagerService::validateStorylineEndToEndContract()` + generated-bundle contract stages | Also enforces objective/task/entity/generated-outline checks before persistence. |
| `QuestGeneratorService` | `QuestDestinationValidatorService` + `ObjectiveTypeService::assertObjectivePhases()` + `StateValidationService::validateQuestSummary()` | Quest template/runtime contract enforcement. |
| `DungeonGeneratorService` | Internal `validateContext()` + `DungeonLayoutProfileResolver::validateContext()` + downstream room/schema validations | Delegates room-level structural validation to `RoomGeneratorService`. |
| `RoomGeneratorService` | `SchemaLoader::validate('room', ...)` | Room payload schema validation (warning-path today). |
| `MapGeneratorService` | `StateValidationService::validateNavigationReceipt()` + connector parity assertions | Navigation persistence contract enforcement. |
| `NpcSheetGenerationService` | `StateValidationService::validateNpcSheet()` | Hard contract gate before sheet persistence. |
| `GmRealityCheckGenerationService` | `GmRealityCheckPolicyAdapter` (`validateRoleBoundary`, `validateActionResources`) | GM output/action safety validation boundary. |
| `ConnectorGenerationPolicy` | Canonical connector contract enforced by `ConnectorDefinitionService` | Normalizes raw generation edges to canonical connector shape. |
| `GmGenerationPolicy` | GM cache-first orchestration contract via `GmTurnCoordinatorService` | Controls cache-hit/miss/generation handoff semantics. |
| `EncounterGeneratorService` | No dedicated validator service (budget/threat logic is internal) | Consider adding explicit contract validator if behavior expands. |
| `FactionGenerationService` | No dedicated validator service (institution normalization/registry guards) | Uses normalization + manifest persistence contracts. |
| `CharacterPortraitGenerationService` / `SpriteGenerationService` / `TerrainImageGenerationService` | No dedicated validator service (provider/repository contract checks) | Runtime correctness is currently integration + persistence based. |
| `ImageGenerationIntegrationService` / `GeminiImageGenerationService` / `VertexImageGenerationService` | No dedicated validator service (provider readiness + API contract checks) | Effective provider is chosen by integration status. |
| `NameGeneratorService` / `CampaignNameGeneratorService` / `NumberGenerationService` / `TerrainGeneratorService` | No dedicated validator service | Deterministic utility generators without schema validators. |
| `ContentGenerator` | Deprecated legacy service | Not part of authoritative runtime generation path. |
| `DungeonGenerationEngine` | Deprecated legacy service | Replaced by `DungeonGeneratorService`. |

### Actor Psychology Invocation Summary

The actor psychology subsystem is invoked in two canonical gameplay lanes:

- **Dialogue lane** (room chat + NPC replies): `HexMapController` and `MapGeneratorService` bootstrap room NPC profiles, while `RoomChatService` builds NPC prompt context from `NpcPsychologyService::buildUnifiedActorContext()` (via `buildNpcContextForPrompt()`), injects canonical actor action-availability envelope context, and records post-dialogue state shifts via `recordInnerMonologue()`.
- **Next-action lane** (encounter AI decisions): `EncounterActorContextBuilder` consumes the same `NpcPsychologyService::buildUnifiedActorContext()` path to inject `current_actor_profile` + `npc_psychology` into action context, with `EncounterPhaseHandler` retaining compatibility wrappers.
- **Unified decision envelope convergence (2026-07-23):** action decisions now emit `actor_decision_v1` (`tool=action`) from `EncounterAiIntegrationService` using `ActorDecisionContractService`, and chat dialogue payloads emit `actor_decision_v1` (`tool=chat`) through `RoomChatService` contract assembly.
- **Conformance refresh (2026-07-08):** recent RoomChatController facade decomposition and stream/result boundary extraction did not change actor-psychology invocation authority; invocation remains service-owned (`RoomChatService`, `EncounterActorContextBuilder` via `EncounterPhaseHandler`) and server-authoritative.

### Actor Psychology Subsystem Component Breakdown (2026-07-20)

| Component | Primary responsibility | Key implementation surface |
|---|---|---|
| Room-entry bootstrap | Ensure active-room NPCs have runtime psychology profiles before first dialogue turn | `HexMapController::ensureRoomNpcPsychologyProfiles()` → `RoomChatService::ensureNpcProfiles()` |
| Generation-time bootstrap | Ensure newly generated room NPCs are psychology-backed at creation time | `MapGeneratorService` Step 10b (`ensureRoomNpcProfiles`) |
| Profile authority + persistence | Own canonical profile lifecycle, attitude history, and inner-monologue persistence | `NpcPsychologyService`, `dc_psychology` |
| Dialogue context assembly | Build bounded prompt-ready NPC psychology + sheet context from the canonical unified actor context envelope plus canonical action-availability envelope | `NpcPsychologyService::buildUnifiedActorContext()` / `buildNpcContextForPrompt()`, `ActorActionAvailabilityService::resolveEncounterAvailability()` |
| Dialogue orchestration | Invoke psychology context for direct reply/interjection flows and persist post-response thought updates | `RoomChatService*Trait` methods (`buildNpcContextForPrompt`, `recordInnerMonologue`) |
| Encounter decision context | Build structured + narrative psychology payloads for NPC next-action recommendations from the same canonical actor context envelope | `NpcPsychologyService::buildUnifiedActorContext()` consumed by `EncounterActorContextBuilder` (via `EncounterPhaseHandler` wrappers) |
| Encounter AI boundary | Enforce handoff of psychology fields into model/provider payload contract | `AiConversationEncounterAiProvider` (`current_actor_profile`, `npc_psychology`) |
| Unified actor decision contract | Canonical action/chat decision envelope mapping + shared action-contract hash normalization | `ActorDecisionContractService`, `ActorDecisionValidatorService` |

### Hostility, Aggression, and Combat Entry Framework (2026-08-20)

The runtime uses **three separate layers**. They should not be conflated:

| Layer | What it decides | Canonical surfaces | How to tune it |
|---|---|---|---|
| **1. Baseline actor disposition / psychology** | Who this actor generally is: attitude, personality axes, goals, fears, motivation, memory | `NpcPsychologyService`, `ActorDispositionService`, `DispositionAuthorityContract`, `dc_psychology`, disposition state/event stores | Adjust canonical attitude labels/scores in `DispositionAuthorityContract`; adjust personality/profile generation and update behavior in `NpcPsychologyService`; adjust durable trigger deltas in `DispositionTriggerCatalog` / `DispositionTriggerService` |
| **2. Effective source→target disposition resolver** | How actor A currently feels about actor B in context | `DispositionResolverService`, `DispositionSceneContextService`, relationship-attitude service, institution score assembler | Adjust factor set and factor weights in `DispositionResolverService`; adjust situational inputs such as `threat_level`, coercion, recent harm/help in `DispositionSceneContextService`; adjust relationship/institution inputs in their respective services |
| **3. Aggression / combat-entry policy** | Whether hostility escalates into combat right now | `AggressionPolicyService`, `CombatEntryService`, `GmOrchestrationBrokerService` | Adjust combat-entry thresholds and hostility-pressure formula in `AggressionPolicyService`; adjust policy input sourcing/defaults in `GmOrchestrationBrokerService`; adjust final blocking/persistence behavior in `CombatEntryService` |

#### Layer 1 — Baseline actor disposition / psychology

- **Canonical hostility label:** `DispositionAuthorityContract::LABEL_HOSTILE`
- **Canonical hostile score mapping:** `DispositionAuthorityContract::attitudeToScore('hostile') => -100`
- **Canonical hostile threshold helper:** `DispositionAuthorityContract::HOSTILE_SCORE_THRESHOLD` (`<= -70`)

This layer is where the actor's **long-lived social baseline** lives:

- personality axes (`boldness`, `honesty`, `empathy`, `discipline`, `cunning`, `motivation`)
- motivations / fears / bonds / goals
- attitude history
- inner monologue memory
- durable disposition shifts from domain events

**Primary adjustment points**

- `src/Service/DispositionAuthorityContract.php`
  - change label-to-score mapping
  - change hostile threshold
- `src/Service/NpcPsychologyService.php`
  - change default axes, profile generation, monologue-based attitude updates
  - change room bootstrap/default role behavior
- `src/Service/DispositionTriggerCatalog.php` and `src/Service/DispositionTriggerService.php`
  - add/remove event types that mutate disposition
  - adjust actor/relationship deltas
  - adjust repeat-window damping / idempotency policy

#### Layer 2 — Effective source→target disposition resolver

This layer answers: **"Given the actor's baseline, relationships, and scene context, how hostile is actor A toward target B right now?"**

`DispositionResolverService` currently combines:

- actor baseline score
- relationship edge score
- situational score
- institution score
- recent harm score
- recent help score
- coercion score
- recent impulse score

It also emits policy-facing fields such as:

- `effective_disposition_score`
- `effective_disposition_label`
- `policy_flags.hostile`
- `policy_flags.attack_authorized_candidate`

**Primary adjustment points**

- `src/Service/DispositionResolverService.php`
  - add/remove factors in the resolver equation
  - adjust factor weights
  - adjust confidence logic
- `src/Service/DispositionSceneContextService.php`
  - change how `threat_level` maps to situational pressure
  - change how recent harm/help/coercion/impulse are normalized
- relationship / institution services
  - change upstream edge scores or institution adjustments that feed the resolver

**Important boundary:** `policy_flags.hostile` and `attack_authorized_candidate` are **not combat entry authority**. They are inputs/signals for the combat-entry layer.

#### Layer 3 — Aggression / combat-entry policy

This layer answers: **"Even if hostility exists, are we authorized to start combat now?"**

`GmOrchestrationBrokerService::buildCombatPolicyInput()` assembles the canonical policy input from:

- actor attitude / actor score
- most hostile relationship edge toward the selected targets
- fear score
- aggression bias score
- recent harm score
- recent help score
- aggression signal
- threat level
- explicit attack declaration
- valid target ids
- prior aggression state

`AggressionPolicyService::evaluateAggressionState()` then computes **hostility pressure**:

```text
(0.35 * actor_score)
+ (0.35 * relationship_score)
+ (0.15 * aggression_bias_score)
+ (0.25 * recent_harm_score)
- (0.20 * recent_help_score)
- (0.10 * fear_score)
+ (0.20 * threat_score)
```

Combat entry is currently authorized only when one of these gates passes:

1. `hostility_pressure <= -65`
2. `hostility_pressure <= -40` **and** (`explicit_attack_declared` **or** current state is `threatened|hostile|engaged`)
3. `explicit_attack_declared` **and** `hostility_pressure <= -20` **and** `threat_score >= 25`

Then `CombatEntryService` applies final blockers, especially:

- `no_valid_targets`
- no resolved enemy entities
- encounter start failure from runtime coordinator

**Primary adjustment points**

- `src/Service/AggressionPolicyService.php`
  - adjust hostility-pressure weights
  - add/remove input variables
  - change escalation-state defaults
  - change authorization thresholds
  - change `threat_level -> threat_score`
- `src/Service/GmOrchestrationBrokerService.php`
  - change how policy inputs are inferred/defaulted
  - change how explicit attack and aggression signals are derived from canonical actions
  - change which relationship edge becomes canonical input
- `src/Service/CombatEntryService.php`
  - change final block/enter behavior, summary persistence, and state transitions

#### Is the framework clear enough to adjust?

**Mostly yes, with one caveat.**

The framework is reasonably clear for tuning because each layer has a dominant service:

- baseline disposition / psychology → `NpcPsychologyService` + disposition stores/contracts
- target-specific hostility resolution → `DispositionResolverService`
- combat authorization → `AggressionPolicyService`

The main caveat is that **some inputs are assembled outside the policy service**, especially in `GmOrchestrationBrokerService`. So if combat behavior feels wrong, the fix may belong in:

1. the **source data** (baseline disposition / relationship / scene context),
2. the **resolver weights**, or
3. the **combat-entry policy thresholds**.

Use this rule of thumb:

- **Actor is too hateful in all contexts** → adjust **Layer 1**
- **Actor is only too hateful toward specific targets or scenes** → adjust **Layer 2**
- **Actor feels hateful but starts combat too easily / too rarely** → adjust **Layer 3**

#### Recommended maintenance rule

When changing this subsystem, preserve the separation:

- **Layer 1** may decide who an actor is.
- **Layer 2** may decide how that actor currently feels about a target.
- **Layer 3** alone should decide whether that hostility becomes combat.

See subsystem docs for full invocation points and context payload details:

- `CHAT_AND_NARRATION_ARCHITECTURE.md`
- `COMBAT_ENGINE_ARCHITECTURE.md`

## 3. Runtime Data Authority

| Concern | Authoritative surface |
|---|---|
| Campaign runtime state | `dc_campaigns`, `dc_campaign_dungeons`, `dc_campaign_rooms`, `dc_campaign_characters`, `dc_campaign_quests` |
| Canonical content library | `dungeoncrawler_content_registry`, `dungeoncrawler_content_rooms`, `dungeoncrawler_content_dungeons`, quest template/content tables |
| Storyline templates/contracts | `dungeoncrawler_content_quest_templates` and related storyline tables |
| Encounter persistence | combat encounter tables used by `CombatEncounterStore` |

Reference JSON/template files are input/reconciliation artifacts. Runtime contracts are enforced from database-backed canonical surfaces.

## 3A. Placement Resolution Contract (Humanoids)

- **Active placement resolution:** H3 **resolution 14**.
- **Authoritative actor field:** `dc_campaign_characters.position_h3` (stores res14 H3 index).
- **Authoritative runtime payload field:** `placement.h3_index_res14`.
- `position_q/position_r` and payload `placement.hex.q/r` are derived room-local projections, not placement authority.
- **Deterministic startup default:** if a humanoid (PC/NPC/follower) has no valid placement, assign an unused canonical res14 cell in `tavern_entrance`.

## 3B. Validation Subsystems (Do Not Conflate)

The module has two separate validation subsystems with different responsibilities:

| Subsystem | Scope | Core entry points | What it validates | What it must NOT do |
|---|---|---|---|---|
| Campaign Runtime Quest-State Validation | Live campaign progression in `dc_campaign_*` | `POST /api/campaign/{campaign_id}/quest-touchpoints` → `QuestTrackerController::ingestTouchpoint()` → `QuestTouchpointService` / `QuestTrackerService` | Touchpoint-to-objective mapping, ambiguity confirmation flow, objective progress mutation, runtime quest state transitions | Validate canonical template/storyline structure |
| Canonical Library Validator | Canonical storyline/quest library structure and contracts | `StorylineManagerService::validateStorylineEndToEndContract()` and Storyline Explorer diagnostics | Storyline schema/cross-reference integrity, objective control-chain contract, canonical entity linkage contracts | Mutate or advance live campaign quest state |

Rule: runtime campaign issues are diagnosed in the runtime touchpoint/tracker path; canonical template integrity issues are diagnosed in the storyline/canonical validator path.
Runtime quest lifecycle contract: touchpoints never auto-start offered quests; explicit `/api/campaign/{campaign_id}/quests/{quest_id}/start` is required before objective progress can mutate that quest.

## 4. API Surface (Grouped from Routing)

The routing surface is organized around these domains:

- Admin + architecture/analysis pages
- Public pages and game UI routes
- Character management and character APIs
- NPC APIs
- Campaign/dungeon/room state APIs
- Room chat, chat channels, and chat session hierarchy APIs
- Inventory management APIs
- Game coordinator APIs (canonical gameplay loop)
- Combat support APIs (encounter-scoped support + legacy endpoints)
- Dungeon/room generation APIs
- Quest/storyline/reward/touchpoint APIs
- Spell/feat/content catalog APIs
- Play session and subsystem management APIs

See `dungeoncrawler_content.routing.yml` for route-level details.

## 5. Codebase Structure (Module Core)

Top-level module code lives in:

- `src/Controller/` — route handlers and API/page controllers
- `src/Service/` — core game/runtime services
- `src/Form/` — Drupal forms and admin workflows
- `src/Access/` — access checkers
- `src/Commands/` — Drush command surfaces
- `src/EventSubscriber/` — event-driven quest/runtime hooks

### RoomChat source-of-truth policy

- **Canonical authoring path:** `src/` at the module root.
- The legacy `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/` mirror path is retired and must not be reintroduced.
- Use `scripts/check-roomchat-tree-drift.sh` to enforce single-tree canonical RoomChat ownership and block legacy mirror-path drift.

Wiring and route registration:

- `dungeoncrawler_content.services.yml`
- `dungeoncrawler_content.routing.yml`

## 6. Architecture Docs Index

Canonical architecture references in this repository:

- `ARCHITECTURE.md` — module architecture baseline
- `GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md` — gameplay/runtime orchestration
- `CHAT_AND_NARRATION_ARCHITECTURE.md` — chat/session/narration model
- `COMBAT_ENGINE_ARCHITECTURE.md` — encounter/combat subsystem coverage
- `TAB_RUNTIME_CONSISTENCY_ARCHITECTURE.md` — current-state and target-state tab/runtime consistency architecture
- `TAB_RUNTIME_CONSISTENCY_IMPLEMENTATION_PLAN.md` — implementation plan for single-store tab/runtime convergence
- `HEXMAP_ARCHITECTURE.md` — map/runtime model
- `HEXMAP_UI_API_FRAMEWORK.md` — authoritative query/post framework for Hexmap UI surfaces
- `HEXMAP_UI_API_CONFORMANCE_IMPLEMENTATION_PLAN.md` — validation and remediation plan for Hexmap UI/API conformance
- `DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md` — deterministic GM orchestration direction
- `docs/README.md` — docs index for implementation/contract documents

## 7. Development Notes

- Module dependency: `drupal/ai_conversation` (see `composer.json` and `dungeoncrawler_content.info.yml`).
- Use targeted PHPUnit/Node contract tests for changed subsystem surfaces.
- Prefer updating subsystem-specific architecture docs together with behavior changes.

## 8. LangGraph Actor Harness (Burasco) — In Progress

The repository now includes a LangGraph-driven actor harness runner that uses existing Drupal runtime surfaces as tools.

### Drush tool commands added

- `dc:actor-harness-bootstrap`
  - Creates a campaign, attaches a ready character by name, starts a starter quest, and returns bootstrap JSON:
    - `campaign_id`, `character_id`, `actor_id`, `room_id`, `started_quest_id`
- `dc:actor-harness-snapshot <campaign_id> <actor_id> --character-id=<id>`
  - Returns runtime snapshot + quest tracking context.
- `dc:actor-harness-action <campaign_id> --payload='<json>'`
  - Submits one canonical gameplay intent to `GameCoordinatorService::processAction()`.

### LangGraph runner

- Script: `scripts/langgraph-actor-harness-run.py`
- Flow:
  1. bootstrap (optional if campaign context not pre-supplied)
  2. snapshot
  3. deterministic wayfinding check from active objective destination metadata
  4. LLM decision (`action` or `chat` or `stop`) when deterministic routing is not required
  5. strict action-intent contract validation (`available_actions` + `action_contract`) before execution
  6. execute action/chat
  7. assess completion vs block after every turn
  8. on block: notify GM and log enhancement issue

Deterministic waypoint contract:

- `dc:actor-harness-snapshot` now emits `deterministic_wayfinding` when an active objective references a destination.
- Snapshot includes actor tool availability context (`available_actions`) plus structured action contract (`action_contract`) for explicit decision constraints.
- If that objective destination exists but no canonical navigation capability can resolve it, the graph hard-fails with `objective_wayfinding_unresolved` and escalates (no silent fallback path).
- If the decider emits an action intent outside the current actor contract, the harness hard-stops with `invalid_action_intent_contract:*` and escalates.
- If no open quest objectives are present but Eldric is visible, the harness seeds a default storyline objective and deterministically interacts with Eldric (or chats if interact is unavailable) to request a new lead.
- If that deterministic Eldric seed turn does not produce a new open objective after one post-seed snapshot refresh, the run hard-stops with `default_storyline_seed_unresolved` (no silent looping).
- Runner command parsing tolerates Drush warning prelude lines and still requires a valid JSON object payload (hard-fail if absent).

### Required runtime configuration

- Shared HQ GenAI wrapper available at `COPILOT_HQ_ROOT/llm/genai_wrapper.py` (defaults to `/home/ubuntu/forseti.life/copilot-hq`)
- `HQ_AGENTIC_BACKEND=local-server` (or `HARNESS_LLM_BACKEND=local-server`) for org-standard local-server routing
- Optional: `HARNESS_LLM_MODEL_ID` to pin a local-server model ID
- Optional: `HARNESS_DECIDER_TIMEOUT_SEC` (default `120`)
- Optional: `HARNESS_DECIDER_MAX_TOKENS` (default `800`) to bound local-model decision output cost

Decider prompt contract notes:

- Decision context is compacted to objective-first fields (`active_objective`, room/NPC summaries, allowed actions, compact action contract).
- `mode=action` must include `action_intent.type` from allowed actions and `action_intent.params` as an object.
- Action-specific required params are hard-enforced (`transition.target_room_id`, talk payload presence), and violations hard-stop with `invalid_action_intent_contract:*`.

### Run example

```bash
python3 scripts/langgraph-actor-harness-run.py --character-name Burasco --uid 1 --max-turns 12
```

`--uid` (or `HARNESS_OWNER_UID`) is required when bootstrapping a new campaign.

### Contract test

```bash
node tests/langgraph_actor_harness_contract_test.js
```

---

This README is now architecture-first and intended as the top-level entry point. Deep implementation details should live in subsystem docs, not in this file.
