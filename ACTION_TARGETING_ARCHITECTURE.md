# Action Targeting Architecture

**Module**: `dungeoncrawler_content`  
**Status**: Current-state analysis + target-state architecture  
**Scope**: Action targeting for encounter, room-scene, navigation, spell, feat, skill, consumable, and interaction actions

---

## Executive summary

The current system already has the **right top-level shape** for action targeting:

1. the client builds an **action contract**,
2. the player selects a target in the map/runtime UI,
3. the coordinator sends an authoritative action intent,
4. the server routes the intent through the active phase handler,
5. the encounter executor applies the action to the resolved target.

That foundation is sound, but targeting is still **inconsistently represented** across action types:

- **Strike** has a mostly complete explicit target path.
- **Talk** and **Demoralize** also use explicit target references.
- **Stride/Step** use explicit hex targeting.
- **Cast Spell** supports an optional target in the protocol, but direct UI casting does not consistently populate it.
- Some targeting semantics live in multiple places as simple strings (`hostile_entity`, `hex`, `contextual`, etc.) without one full target-resolution subsystem.

The target architecture should therefore **keep the current coordinator and phase flow**, but add a dedicated **targeting layer** with:

- one canonical targeting taxonomy,
- one client target-selection contract,
- one server target-resolution pipeline,
- explicit validation rules per targeting mode,
- and a future-safe payload shape for single-target, self-target, room-target, and area-target actions.

---

## Current architecture

## 1. Current targeting surfaces

| Layer | Current responsibility | Current implementation |
|---|---|---|
| Action definitions | Declare target semantics for action ids | `EncounterPhaseHandler::CLIENT_ACTION_DEFINITIONS`, `ActorActionAvailabilityService::ACTION_DEFINITIONS` |
| Action contract | Expose available actions + targeting metadata to client/AI | `ActorActionAvailabilityService::buildActionContractFromAvailableActions()` |
| Client context | Resolve actor, selected entity, selected hex, turn state | `js/v2/services/action-rail-context-service.js` |
| Client selection | Hold map-selected entity / hex | `GameShell.selectEntity()`, `selectedEntity`, `selectedHex` |
| Client execution | Build action intent and send it | `js/v2/systems/EncounterSystem.js`, `GameCoordinatorApi.sendAction()` |
| Server ingress | Accept authoritative intent payload | `GameCoordinatorController::action()` |
| Server orchestration | Load state, dispatch to phase handler, persist results | `GameCoordinatorService::processAction()` |
| Phase routing | Route `type + actor + target + params` into concrete action handler | `EncounterPhaseHandler::processIntentCore()`, `EncounterIntentRouter` |
| Target application | Resolve actor/target participants and apply mechanics | `EncounterActionExecutor::processStrike()` / `processCastSpell()` and handler-specific methods |

## 2. Current action targeting taxonomy

The codebase already has a useful first-pass targeting vocabulary:

| Current token | Meaning |
|---|---|
| `none` | no target required |
| `self` | actor targets self |
| `ally` / `ally_or_self` | actor targets friendly actor(s) |
| `hostile_entity` | actor targets hostile combatant |
| `entity_or_object` | actor targets adjacent runtime entity or object |
| `entity_or_room` | actor targets a runtime entity or room-context subject |
| `self_or_target` | self by default, optional target |
| `hex` | actor targets a map hex |
| `room` | room-scoped action |
| `connected_room` | navigation target |
| `room_hazard` | room hazard target |
| `contextual` | target rules depend on action/spell/feat payload |

This is enough for current gameplay, but `contextual` currently hides too much detail for spells, feats, skills, and consumables.

## 3. Current end-to-end process flow

### Strike

1. Map selection sets `selectedEntity`.
2. `ActionRailPanel.buildContractAtomicActionEntries()` reads that state and enables Strike only when a numeric selected entity id exists.
3. `EncounterSystem.executeDirectAttack()` resolves the selected ECS entity and converts it to a stable runtime ref (`dcEntityRef` / instance id).
4. `GameCoordinatorApi.sendAction()` sends:
   - `type = strike`
   - `actor = actorRef`
   - `target = targetRef`
   - `params.weapon = ...`
5. `GameCoordinatorController` forwards intent to `GameCoordinatorService`.
6. `EncounterPhaseHandler.processIntentCore()` routes through `EncounterIntentRouter::routePrimaryCombatAction()`.
7. `EncounterActionExecutor::processStrike()` resolves attacker/target encounter participants and calls `CombatEngine::resolveAttack()`.
8. Mutations, events, narration, and updated authoritative state are returned.

### Cast Spell

1. `ActionRailPanel.buildSpellActionRailPanel()` exposes spell options and metadata.
2. `EncounterSystem.executeDirectSpell()` sends `cast_spell` with spell params.
3. The unified protocol supports `target`, but the direct spell UI path does not consistently attach one.
4. `EncounterPhaseHandler` routes to `routeCastSpellIntentExecution()`.
5. `EncounterActionExecutor::processCastSpell()` consumes spell resources and applies spell-side checks.
6. Target-aware behavior exists only when `target_id` is present.

### Movement / Talk / Demoralize

- **Stride/Step**: explicit `target_hex` in `params`.
- **Talk**: explicit target ref required in client execution path.
- **Demoralize**: explicit target ref required in client execution path.

---

## Current fit problems

## 1. Targeting truth is duplicated

Target semantics are defined in both:

- `EncounterPhaseHandler::CLIENT_ACTION_DEFINITIONS`
- `ActorActionAvailabilityService::ACTION_DEFINITIONS`

That is manageable now, but it creates drift risk for targeting mode, cost, and availability metadata.

## 2. Target payload shape is too thin

The coordinator protocol accepts:

```json
{ "type": "...", "actor": "...", "target": "...", "params": { ... } }
```

That works for single-target actions, but it does not fully model:

- optional vs required targets,
- target kind,
- area templates,
- multi-target actions,
- object vs actor vs room vs hazard distinctions,
- or server-resolved contextual targeting.

## 3. Client selection and server resolution are not formally connected

The client knows about:

- `selectedEntity`
- `selectedHex`

The server knows about:

- `actor_id`
- `target_id`
- action-specific `params`

But there is no dedicated subsystem contract that says:

- what target kinds are allowed,
- how selection converts into canonical references,
- how validation should happen,
- and what resolution data executors should receive.

## 4. `contextual` is overloaded

For spells, feats, consumables, and some skill actions, `contextual` currently means:

- sometimes self,
- sometimes hostile actor,
- sometimes ally,
- sometimes room/hazard/object,
- sometimes no explicit target at all.

That blocks reliable client UX and reliable server validation.

## 5. Spell targeting is the clearest gap

The unified protocol already supports explicit spell targets, but current direct spell execution does not use it consistently. That leaves spell targeting behind strike/talk/demoralize.

---

## Target-state architecture

## 1. Design principles

1. **Server-authoritative**: the client proposes a target, the server resolves and validates it.
2. **One targeting contract**: all actions use the same target envelope, even when some fields are empty.
3. **Action-defined targeting rules**: the action contract declares what target shape is legal.
4. **Executor-ready resolution**: action executors receive a normalized resolved-target object, not ad hoc raw fields.
5. **Backward-compatible rollout**: preserve the existing `target` field while introducing richer targeting metadata.

## 2. Canonical targeting model

Every action should resolve into one of these canonical modes:

| Mode | Example actions |
|---|---|
| `none` | end turn, delay |
| `self` | raise shield, refocus |
| `single_actor_hostile` | strike, demoralize, attack-roll spell |
| `single_actor_ally` | battle medicine, aid setup |
| `single_actor_any` | talk, request, some feats |
| `single_object` | interact with object |
| `single_hazard` | disable device, trigger hazard action |
| `single_hex` | stride, step |
| `single_room` | search, seek |
| `single_connection` | transition |
| `area_hex` | burst, cone, line, emanation |
| `multi_actor` | chain or party-support effects |
| `contextual_resolved` | action-specific fallback during migration only |

`contextual_resolved` should be transitional, not the long-term steady state.

## 3. Canonical targeting contract

The action contract should keep its existing lightweight `targeting` string for compatibility, but grow an explicit `targeting_rules` object.

### Proposed action contract shape

```json
{
  "id": "cast_spell",
  "label": "Cast Spell",
  "cost": 2,
  "targeting": "single_actor_hostile",
  "targeting_rules": {
    "required": false,
    "mode": "single_actor_hostile",
    "selection_source": "entity",
    "filters": ["hostile", "alive", "in_encounter"],
    "range": {
      "type": "spell_range",
      "value": 30
    },
    "area": null,
    "cardinality": {
      "min": 0,
      "max": 1
    }
  }
}
```

### Why this fits the current system

- `ActorActionAvailabilityService` already builds the action contract.
- Spell/feat/item option metadata already exists in high-option families.
- The client already reads contract metadata before enabling buttons.
- AI validation already reads the targeting token to decide whether a target is required.

This is therefore an extension of the current design, not a replacement.

## 4. Canonical action intent target envelope

The coordinator request should preserve the current top-level `target` field for backward compatibility, but formally support a richer optional `targeting` payload.

### Proposed intent shape

```json
{
  "type": "cast_spell",
  "actor": "actor_ref",
  "target": "entity_instance_ref",
  "targeting": {
    "mode": "single_actor_hostile",
    "primary_target_ref": "entity_instance_ref",
    "target_refs": ["entity_instance_ref"],
    "target_hex": null,
    "area": null,
    "selection_context": {
      "selected_entity_id": 123,
      "selected_hex": null
    }
  },
  "params": {
    "spell_id": "magic_missile"
  }
}
```

### Migration rule

- **Now**: support both `target` and action-specific params.
- **Target state**: server first normalizes `target + params + targeting` into one resolved envelope.

## 5. Dedicated targeting subsystem

Add a dedicated server-side targeting layer between phase routing and execution.

### Proposed services

| Service | Responsibility |
|---|---|
| `ActionTargetingDefinitionRegistry` | canonical targeting definitions per action/spell/feat/item option |
| `TargetIntentNormalizerService` | normalize raw `{ target, params, targeting }` into one internal target request |
| `TargetResolutionService` | resolve runtime refs / hexes / room ids / hazard ids into canonical target handles |
| `TargetValidationService` | validate legality: turn, hostility, ally-ness, range, line of effect, adjacency, encounter presence |
| `ResolvedTargetContextBuilder` | build executor-ready payload for action handlers |

### Resolved target context shape

```php
[
  'mode' => 'single_actor_hostile',
  'primary_target_ref' => 'entity_ref:...',
  'primary_target_type' => 'actor',
  'primary_participant_id' => 42,
  'target_refs' => ['entity_ref:...'],
  'target_hex' => NULL,
  'room_id' => NULL,
  'connection_id' => NULL,
  'hazard_id' => NULL,
  'validation' => [
    'in_range' => TRUE,
    'in_encounter' => TRUE,
    'relationship_ok' => TRUE,
  ],
]
```

Executors should consume this instead of hand-parsing `target_id` or custom params.

---

## Targeting process flow

## 1. Client flow

1. **Action contract load**
   - Action rail receives canonical actions and `targeting_rules`.

2. **Selection state**
   - Player selects entity and/or hex.
   - State manager stores `selectedEntity` and `selectedHex`.

3. **Target compatibility check**
   - Client checks whether current selection satisfies action targeting rules.
   - Button UX explains why an action is disabled.

4. **Intent build**
   - Client converts selected UI object into stable runtime ref(s).
   - Client sends `target` and optional richer `targeting` payload.

5. **Authoritative request**
   - Coordinator endpoint remains the single action ingress.

## 2. Server flow

1. **Ingress**
   - `GameCoordinatorController::action()` accepts request.

2. **Phase orchestration**
   - `GameCoordinatorService::processAction()` loads authoritative state and active phase.

3. **Intent normalization**
   - `TargetIntentNormalizerService` converts raw input into a canonical target request.

4. **Target resolution**
   - `TargetResolutionService` resolves target refs, participants, hexes, rooms, hazards, and connections.

5. **Target validation**
   - `TargetValidationService` checks:
     - target presence if required,
     - target type,
     - actor/turn ownership,
     - range / adjacency / room connectivity,
     - ally/hostile constraints,
     - encounter membership,
     - spell/item/feat-specific restrictions.

6. **Execution**
   - Phase handler routes action.
   - Executor receives resolved target context.

7. **Mutation + projection**
   - Combat, condition, effect, room, or narrative side effects apply.
   - Updated state, events, and narration return through the existing coordinator response path.

## 3. Example flows

### Example A: Strike

- Contract mode: `single_actor_hostile`
- Client selection source: `selectedEntity`
- Normalized target: `primary_target_ref`
- Server resolution: encounter participant
- Validation: hostile + in encounter + legal turn
- Executor: `CombatEngine::resolveAttack()`

### Example B: Cast a targeted spell

- Contract mode: `single_actor_hostile` or `single_actor_ally`
- Option metadata supplies spell-specific target rules
- Client requires compatible selected entity when target is required
- Server validates spell range and target legality
- Executor consumes resolved target context and applies spell result/effects

### Example C: Cast an area spell

- Contract mode: `area_hex`
- Client selection source: `selectedHex`
- Intent carries center hex + area template metadata
- Server resolves all affected participants from authoritative board state
- Executor applies spell outcome to each resolved target

### Example D: Search

- Contract mode: `single_room`
- No entity target required
- Server uses room context already present in encounter/runtime state

---

## How this fits within the current systems

## 1. GameCoordinator stays the authoritative ingress

No change in top-level authority:

- client still sends intent,
- `GameCoordinatorController` still receives it,
- `GameCoordinatorService` still orchestrates it,
- phase handlers still own game-rule execution.

The new targeting layer slots **inside** the current phase/action pipeline, not beside it.

## 2. ActorActionAvailabilityService becomes the preferred targeting-definition owner

The action contract is already built there, so it should become the primary place where action targeting rules are assembled.

Recommended direction:

1. keep `EncounterPhaseHandler::CLIENT_ACTION_DEFINITIONS` only as legacy/static fallback,
2. treat `ActorActionAvailabilityService` as the canonical builder for client-facing targeting metadata,
3. extract shared targeting definitions into one registry so both callers stop drifting.

## 3. EncounterPhaseHandler remains the routing owner

`EncounterPhaseHandler::processIntentCore()` already routes actions well. It should keep that responsibility, but stop doing ad hoc target interpretation where possible.

Recommended direction:

- `processIntentCore()` receives intent,
- target normalizer/resolver runs,
- route method receives `ResolvedTargetContext`,
- handler-specific action methods execute against resolved target objects.

## 4. EncounterActionExecutor becomes cleaner, not bigger

Right now `processStrike()` and `processCastSpell()` still partially interpret raw target data.

Target state:

- executor methods should receive a resolved target context,
- not manually discover whether target is present or what kind it is,
- and not duplicate target presence checks that belong in shared validation.

## 5. Action Rail gets better UX without changing its role

The Action Rail should remain a thin client:

- read contract,
- show compatible actions,
- guide selection,
- emit authoritative intent.

It should not own game legality; it should only provide early UX feedback.

---

## Immediate architecture improvements

## 1. Make spell targeting explicit

First practical slice:

1. classify spell options as `self`, `single_actor_hostile`, `single_actor_ally`, `single_room`, or `area_hex`,
2. emit `target` from `executeDirectSpell()` when required/selected,
3. validate target legality server-side before spell execution,
4. stop relying on implicit contextual spell targeting for direct action rail casts.

This is the most important parity fix because the protocol already supports it.

## 2. Collapse duplicated targeting definitions

Create one shared action-targeting registry so these two do not drift:

- `EncounterPhaseHandler::CLIENT_ACTION_DEFINITIONS`
- `ActorActionAvailabilityService::ACTION_DEFINITIONS`

## 3. Replace `contextual` where rules are already known

Reduce `contextual` usage for:

- spells,
- consumables,
- feats,
- common skill actions.

Reserve `contextual` only for genuinely dynamic targeting rules during migration.

## 4. Normalize on stable refs at the protocol boundary

The client may still use numeric ECS ids for UI wiring, but coordinator-bound target payloads should always normalize to stable runtime refs before server execution.

That is already happening for strike and should become universal.

---

## Recommended implementation sequence

1. **Extract shared targeting definitions**
   - One canonical registry for action targeting tokens and rules.

2. **Add target intent normalizer**
   - Normalize `target`, `params.target_*`, and optional `targeting` object.

3. **Add target resolution/validation services**
   - Shared across strike, spell, talk, demoralize, interact, movement, and navigation.

4. **Wire spell targeting**
   - Make direct spell casts emit explicit targets when applicable.

5. **Expand to feat/skill/consumable options**
   - Drive target mode from option metadata and canonical content contracts.

6. **Add area targeting support**
   - Hex-centered templates and server-side participant collection.

---

## Definition of done for the targeting architecture

The targeting subsystem is in the desired state when:

1. every player-facing action has a canonical targeting mode,
2. the action contract exposes enough metadata for the client and AI to select legal targets,
3. the coordinator request can represent single-target, self-target, room-target, hex-target, and area-target intents,
4. the server resolves and validates targets through one shared pipeline,
5. strike, spell, feat, skill, consumable, interaction, and navigation actions all use that same resolution path,
6. executors receive normalized resolved target contexts instead of ad hoc raw target fields.

---

## Bottom line

The current system already has the **correct authority boundaries** for targeting. The missing piece is not a new gameplay architecture; it is a **dedicated targeting contract and resolution layer** that sits between:

- **client selection**, and
- **phase/executor rule application**.

That lets us preserve the current coordinator design while making targeting explicit, consistent, validator-friendly, and extensible for targeted spells, feats, consumables, and future area effects.
