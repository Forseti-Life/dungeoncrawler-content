# Conditions & Effects Subsystem: Current Architecture and Target State

**Module**: `dungeoncrawler_content`  
**Status**: Current-state analysis + target-state architecture  
**Scope**: Conditions, ongoing effects, durations, application, projection, and expiration across **actors, items, rooms, hexes, and campaign runtime state**

---

## Executive summary

The current implementation has **multiple partial subsystems** for conditions and effects, each with different storage, lifecycle, and projection rules:

1. **Encounter conditions** live in `combat_conditions` and are managed by `ConditionManager`.
2. **Persistent character conditions** live in canonical character state (`CharacterStateService`).
3. **Persistent derived impacts** are mirrored into `dc_active_effects` by `ActiveEffectStoreService` and `ImpactContractService`.
4. **Room/item gameplay effects** live in room gameplay state (`gameplay_state.active_effects`) and are managed by `GameplayActionProcessor`.
5. **Spell durations** during encounters are tracked separately in `game_state['spells']['durations']`.
6. **Runtime entity projections** in dungeon payloads mirror selected conditions/effects but are not the canonical lifecycle owner.

This means the system currently supports conditions/effects, but **does not yet have one unified subsystem** for:

- effect application,
- effect projection,
- effect expiration,
- cross-scope stacking,
- source-of-truth ownership,
- or lifecycle transitions between encounter / room / persistent sheet state.

The target state should be a **single effect lifecycle subsystem** with:

- one canonical effect instance model,
- scope-aware storage,
- trigger-driven application,
- rule-driven projection,
- and explicit expiration processing.

---

## Current subsystem architecture

## 1. Current effect/condition domains

| Domain | Canonical owner | Storage | Examples |
|---|---|---|---|
| Encounter participant conditions | `ConditionManager`, `CombatEncounterStore`, `EncounterPhaseHandler` | `combat_conditions` + encounter participant state | frightened, flat-footed, persistent damage, end-of-turn decrements |
| Persistent character conditions | `CharacterStateService` | `dc_campaign_characters.character_data` / canonical character state | mage armor, doomed, wounded, fatigued, sheet-visible conditions |
| Persistent derived impacts | `ImpactContractService`, `ActiveEffectStoreService` | `dc_active_effects` | feat/equipment/condition impacts on AC, speed, senses, spell augments |
| Encounter spell duration state | `EncounterPhaseHandler` | `game_state['spells']['durations']`, `game_state['spells']['sustained']` | sustained spells, round countdowns |
| Room gameplay effects | `GameplayActionProcessor` | `room.gameplay_state.active_effects` | room inventory and environment gameplay effects |
| Runtime actor projection | `CampaignCharacterRuntimeSyncService`, `CanonicalProjectionService` | `dungeon_data['entities'][].state` | projected conditions/resources for room/encounter runtime |
| Item daily lifecycle | `MagicItemService` | `game_state['magic_items']` + item/campaign state | staff prep, wand reset, invested state |

## 2. Current major services and responsibilities

| Service | Current role |
|---|---|
| `ConditionManager` | Encounter-only condition CRUD, duration normalization, decrement, persistent-damage processing |
| `CombatEncounterStore` | Persists encounter participants and `combat_conditions` rows |
| `EncounterPhaseHandler` | Encounter turn flow, spell duration countdown, sustain/dismiss, daily-prep encounter recovery |
| `EncounterActionExecutor` | Action-level mutations, including spell resource consumption and actor runtime projection |
| `CharacterStateService` | Canonical character sheet state, conditions array, spell-slot/focus consumption, effective-state recalculation |
| `ImpactContractService` | Converts feat/equipment/condition sources into normalized persistent impact contracts |
| `ActiveEffectStoreService` | Persists normalized active effects for character scope in `dc_active_effects` |
| `CanonicalProjectionService` | Mirrors canonical state into runtime entity projections; partial daily-prep condition cleanup |
| `CampaignCharacterRuntimeSyncService` | Builds player/NPC runtime actor payloads for dungeon runtime |
| `GameplayActionProcessor` | Room/item gameplay effects and room gameplay-state active effects |
| `MagicItemService` | Daily prep resets for investments, wands, staves, and other item-specific daily lifecycle |

## 3. Current storage model

### 3.1 Encounter-scoped conditions

- Table: `combat_conditions`
- Managed by: `ConditionManager`
- Duration model:
  - `duration_type`
  - `duration_remaining`
- Lifecycle:
  - apply
  - decrement
  - remove
  - special-case persistent damage processing

**Strength**: explicit encounter lifecycle  
**Weakness**: separate from persistent character conditions and persistent active effects

### 3.2 Persistent character conditions

- Stored in canonical character state under `state['conditions']`
- Owned by: `CharacterStateService`
- Used by:
  - sheet rendering
  - effective-state recalculation
  - runtime projection

**Strength**: canonical for sheet-visible persistent conditions  
**Weakness**: expiration is currently ad hoc, not governed by a general lifecycle engine

### 3.3 Persistent derived impacts

- Stored in `dc_active_effects`
- Owned by:
  - `ImpactContractService`
  - `ActiveEffectStoreService`
  - `CharacterStateService::saveState()`

These are **normalized mechanical impacts**, not user-facing conditions.

Example targets:

- `defenses.armorClass.armorBonus`
- `defenses.armorClass.otherBonuses`
- `movement.speed.total`
- `defenses.perception.featBonus`
- `spells.innate`
- `senses`

**Strength**: closest thing to a reusable effect model  
**Weakness**: currently rebuilt from source state; not yet a full cross-scope lifecycle engine

### 3.4 Room gameplay effects

- Stored in room gameplay state (`active_effects`)
- Owned by `GameplayActionProcessor`
- Used for room/item/environment systems

**Strength**: room-local effect channel exists  
**Weakness**: not unified with actor/item/effect lifecycle contracts

---

## Current lifecycle behavior

## 1. Application

Application is currently **source-specific**, not subsystem-specific:

- encounter conditions -> `ConditionManager`
- persistent character spell/resource changes -> `CharacterStateService`
- persistent stat impacts -> derived from feats/equipment/conditions
- room gameplay effects -> `GameplayActionProcessor`
- runtime entity projection -> `CanonicalProjectionService` / runtime sync services

There is **no single effect application API** for all scopes.

## 2. Projection

Projection is also split:

- encounter conditions project into encounter flow and combat state
- persistent conditions project into character sheet effective state
- persistent impacts project into `effectiveState`
- canonical state projects into runtime actor entities
- room effects project into room inventory/gameplay state consumers

There is **no unified projection pipeline** from one canonical effect instance model.

## 3. Expiration

Expiration exists, but only in fragments:

| Effect type | Current expiration path |
|---|---|
| Encounter timed conditions | `ConditionManager` decrement/removal |
| Encounter spell round durations | `EncounterPhaseHandler` decrements `game_state['spells']['durations']` |
| Sustained spells | `sustain_spell` / `dismiss_spell` in `EncounterPhaseHandler` |
| Daily-prep recovery conditions | `CanonicalProjectionService::applyCanonicalDailyPreparationConditionRecovery()` |
| Item daily resets | `MagicItemService::performDailyPreparations()` |
| Persistent sheet effects in `dc_active_effects` | no general expiration subsystem |
| Room gameplay active effects | no universal scheduler/lifecycle manager |

### Important current gap

The codebase now has a concrete expiration path for `until_next_daily_preparations` **conditions** via daily-preparation recovery, but it still does **not** have a universal subsystem that governs expiration for all persistent effect instances across:

- actor sheet conditions,
- active-effect rows,
- room effects,
- hex effects,
- item-bound temporary effects.

---

## Current architectural problems

## 1. Multiple sources of truth

Different effect categories live in different stores:

- `combat_conditions`
- `character_data.conditions`
- `dc_active_effects`
- `game_state['spells']['durations']`
- `room.gameplay_state.active_effects`
- runtime entity state mirrors

This makes reconciliation and cross-scope reasoning difficult.

## 2. Source-specific logic instead of subsystem logic

Spell effects, item effects, feat effects, room effects, and combat conditions each have custom application and removal paths. The engine has **behavior**, but not yet a unified **effect model**.

## 3. Partial expiration coverage

Duration management exists for:

- encounter turn/round conditions,
- sustained spells,
- daily prep resets,

but not as one lifecycle manager that handles:

- end of turn,
- start of turn,
- end of round,
- room exit,
- room entry,
- next daily preparations,
- next rest,
- elapsed campaign time,
- item unequip/drop/destroy,
- source invalidation,
- target invalidation.

## 4. Projection duplication

The same conceptual effect may be represented in:

- canonical character state,
- derived impact rows,
- encounter participant rows,
- runtime actor state,
- room gameplay state.

That creates repeated mutation-synchronization risk.

## 5. Weak scope modeling

Current effect storage is organized more by implementation surface than by effect scope. The system needs explicit scope classes like:

- actor
- item
- room
- hex
- connection
- campaign
- encounter participant

---

## Target-state architecture

## 1. Core principle

All conditions/effects should become **effect instances** with:

- one canonical source,
- one scope,
- one lifecycle,
- one projection contract,
- and one expiration contract.

## 2. Proposed subsystem

### New subsystem: `EffectLifecycleSubsystem`

Primary responsibilities:

1. create effect instances
2. validate scope/target/source contracts
3. apply rule-defined mechanical impacts
4. project effects into runtime surfaces
5. expire effects when triggers fire
6. reconcile effect projections across actor/item/room/encounter runtime

### Proposed core services

| Service | Responsibility |
|---|---|
| `EffectDefinitionRegistryService` | Authoritative definitions of effect/condition behavior, stacking, projection, and expiration triggers |
| `EffectInstanceService` | Create/update/remove canonical effect instances |
| `EffectLifecycleService` | Evaluate triggers and expire/advance effects |
| `EffectProjectionService` | Project effect instances onto character, encounter, room, hex, and item runtime surfaces |
| `EffectScopeResolverService` | Normalize targets and scope keys (actor/item/room/hex/etc.) |
| `EffectExpirationPolicyService` | Rule engine for end-of-turn, daily-prep, time-elapsed, source invalidation, etc. |
| `EffectAuditService` | Immutable audit trail of effect applications, refreshes, and expirations |

## 3. Canonical effect instance model

### Proposed canonical row

```text
effect_instance
- effect_instance_id
- definition_id
- source_type
- source_id
- source_scope
- target_scope_type
- target_scope_id
- target_subscope
- phase_scope
- stacking_type
- value_payload
- application_policy
- expiration_policy
- trigger_policy
- created_at
- activated_at
- expires_at
- expired_at
- is_active
- metadata_json
```

### Scope examples

| Target | Example |
|---|---|
| Actor | `actor:pc-812-1033` |
| Item | `item:wand-of-shield:instance-88` |
| Room | `room:undead_crypt_entry_hall` |
| Hex | `hex:room=undead_crypt_entry_hall:q=3:r=1` |
| Encounter participant | `encounter-participant:1671:pc-812-1033` |
| Campaign | `campaign:812` |

---

## 4. Expiration model

Expiration should be declarative, not hand-coded per spell.

### Proposed expiration policies

| Policy | Example |
|---|---|
| `end_of_turn` | frightened in encounter |
| `start_of_turn` | temporary turn effects |
| `end_of_round` | short tactical effects |
| `until_dismissed` | sustained aura until dismiss |
| `next_daily_preparations` | mage armor |
| `time_elapsed_campaign_seconds` | exploration-room buffs |
| `until_source_removed` | equipment-granted effects |
| `until_room_exit` | room environmental aura |
| `until_hex_exit` | hazard field on a tile |
| `until_consumed` | item charges/temporary imbues |

### Trigger sources

- encounter turn transitions
- encounter round transitions
- daily preparations
- refocus/rest cycles
- room transitions
- time resolver advancement
- item equip/unequip/drop/destroy
- source actor defeated/removed
- explicit dismiss/counteract

---

## 5. Projection model

Canonical effect instances should project to different runtime consumers without changing ownership.

| Consumer | Projection |
|---|---|
| Character sheet | derived effective state |
| Encounter runtime | encounter participant modifiers and temporary condition view |
| Actor runtime entity | canonical runtime actor state slice |
| Room gameplay state | room-local aura/environment effects |
| Hexmap rendering | badge/overlay/tooltip decoration |
| Item state | remaining charges, infused/temporary flags |

Rule: **projection is derived, not authoritative**.

---

## 6. Condition vs effect model

### Condition

A condition is a **player-/GM-visible rules label**:

- frightened
- flat-footed
- mage armor
- wounded

### Effect

An effect is the **mechanical contract**:

- `armorClass.otherBonuses += -1`
- `movement.speed.total += -10`
- `armorClass.otherBonuses += +1`

Target architecture:

- conditions can own one or more effects
- effects can exist without a player-facing condition
- tooltips/rendering read from condition definitions + projected effects

---

## 7. Target ownership rules

| Concern | Target owner |
|---|---|
| Canonical effect instance | `EffectInstanceService` |
| Expiration trigger evaluation | `EffectLifecycleService` |
| Character-sheet impact calculation | `EffectProjectionService` + `CharacterStateService` |
| Encounter tactical condition state | `EffectProjectionService` + encounter adapters |
| Room/hex/environment effect state | `EffectProjectionService` + room adapters |
| Tooltip metadata | `EffectDefinitionRegistryService` |

---

## Recommended migration path

## Phase 1 — Normalize definitions

1. Add a registry for persistent and encounter conditions/effects.
2. Define `mage_armor`, `frightened`, `flat_footed`, `speed_penalty_*`, etc. as formal effect definitions.

## Phase 2 — Unify persistent actor effects

1. Keep `CharacterStateService.conditions` as the player-facing state.
2. Back it with canonical effect instances instead of ad hoc condition logic.
3. Make `dc_active_effects` the authoritative persistent-effect instance store rather than a mirrored artifact.

## Phase 3 — Add expiration engine

1. Introduce trigger-driven expiration evaluation.
2. Move `until_next_daily_preparations` cleanup out of special-case methods into policy evaluation.
3. Add room/hex/time-based expiration policies.

## Phase 4 — Encounter convergence

1. Keep `combat_conditions` only if needed for combat-speed projections.
2. Otherwise migrate encounter conditions to canonical effect instances + fast tactical projection.

## Phase 5 — Room/hex/item effect unification

1. Replace ad hoc `gameplay_state.active_effects` payload logic with canonical effect instances scoped to room/hex/item.
2. Project those into room inventory, room image generation, and hex overlays.

---

## Immediate conclusions

## Current architecture

- **Supports** conditions/effects in several places
- **Does not yet provide** one unified subsystem for all scopes and lifecycles

## Target architecture

Should be:

- **definition-driven**
- **scope-aware**
- **projection-based**
- **trigger-expiring**
- **source-of-truth unified**

## Short answer

Today the codebase has **multiple effect/condition mechanisms**, not one complete subsystem.

The future target state should be a single **Effect Lifecycle Subsystem** that manages:

- application,
- projection,
- stacking,
- expiration,
- and audit

for **actors, items, rooms, hexes, and encounter participants** from one canonical model.

---

## Detailed current-state analysis

## 1. Current write paths by scope

### 1.1 Actor / character scope

There are currently **three** distinct actor-related effect channels:

| Channel | Writer | Reader | Notes |
|---|---|---|---|
| `character_data.conditions` | `CharacterStateService`, selective encounter/rest flows | Character sheet, runtime sync, effective-state resolution | Canonical for sheet-visible persistent conditions |
| `dc_active_effects` | `CharacterStateService::saveState()` via `syncCharacterImpacts()` | `CharacterStateService::buildPersistentEffectContext()` | Derived mirror of impacts, not yet full canonical lifecycle store |
| runtime actor state (`dungeon_data.entities[].state`) | `CampaignCharacterRuntimeSyncService`, `CanonicalProjectionService`, encounter flows | Action availability, room runtime, encounter runtime | Projection surface; should not be source of truth |

### 1.2 Encounter participant scope

Encounter conditions are currently their own subsystem:

| Channel | Writer | Reader | Notes |
|---|---|---|---|
| `combat_conditions` | `ConditionManager`, encounter processors | `RulesEngine`, encounter flow, combat API | Proper turn/round duration support exists here |
| `game_state['spells']['durations']` | `EncounterPhaseHandler` | `EncounterPhaseHandler` | Spell-duration sidecar, separate from `combat_conditions` |
| encounter participant `entity_ref` | `CanonicalProjectionService`, encounter mutation flows | combat runtime | Used for cached runtime projection (spell slots, focus, etc.) |

### 1.3 Item scope

There is no generalized item effect subsystem yet. Item-bound effect behavior is currently fragmented:

- equipment impacts are inferred by `ImpactContractService::buildEquipmentImpactContracts()`
- consumable effects are applied in `CharacterStateService::applyConsumableEffects()`
- item daily lifecycle resets run through `MagicItemService::performDailyPreparations()`
- room inventory item effects are tracked in `GameplayActionProcessor`

### 1.4 Room scope

Room effects live in `room.gameplay_state.active_effects`, but that channel is currently owned by room gameplay logic rather than a general effect engine.

Used for:

- room-local interactive gameplay modifiers
- environmental or temporary room state
- item/room synthesis inside `GameplayActionProcessor::buildRoomInventory()`

### 1.5 Hex scope

There is **no canonical hex-scoped effect instance store** today.

Hex-level gameplay effects are effectively represented indirectly through:

- room hazards
- movement validation
- room gameplay-state active effects
- hexmap render state

That means hex effects currently lack:

- one canonical store,
- one duration model,
- one projection path.

## 2. Current read paths by consumer

### 2.1 Character sheet

The character sheet reads canonical state through `CharacterStateService::getState()`, then resolves:

1. feat effects
2. equipment effects
3. condition effects
4. persisted active-effect rows
5. derived defense/speed/etc. calculations

Important observation: this is the **richest read model** in the system today.

### 2.2 Action availability / action rail

The Action Rail ultimately depends on:

- runtime actor state from `CampaignCharacterRuntimeSyncService`
- actor-scoped action contract from `ActorActionAvailabilityService`
- coordinator state reads from `GameCoordinatorService`

This path is highly sensitive to runtime projection shape and is **not** currently driven by canonical effect instances directly.

### 2.3 Encounter runtime

Encounter runtime reads from:

- `combat_conditions`
- `game_state`
- encounter participant projections
- canonical character-state spell/focus resources when spellcasting mutates the sheet

This is a hybrid of encounter-local state and canonical character state.

### 2.4 Room gameplay/runtime

Room gameplay reads from:

- `room.gameplay_state.active_effects`
- runtime entity projections
- canonical room inventory synthesis

This is the least normalized effect channel today.

## 3. Current expiration trigger coverage map

| Trigger | Current support | Owner | Coverage quality |
|---|---|---|---|
| end of turn | yes | `ConditionManager` | good for encounter conditions |
| start of turn | partial | `EncounterPhaseHandler` | ad hoc for selected flags/spells |
| end of round | partial | `EncounterPhaseHandler` | spell-duration special case |
| explicit dismiss | yes | `EncounterPhaseHandler` | spell-specific |
| next daily preparations | partial | `CanonicalProjectionService`, `MagicItemService` | condition-specific, not generalized |
| rest cycle | partial | rest-action handlers | special-case flows |
| elapsed campaign time | no general subsystem | n/a | fragmented |
| room entry/exit | no general subsystem | n/a | fragmented |
| hex entry/exit | no general subsystem | n/a | missing |
| source removed/unequipped | partial | equipment-derived impacts | derived, not lifecycle-managed |
| campaign/session teardown | no general subsystem | n/a | missing |

## 4. Architectural defect patterns in the current system

### 4.1 “Shape-correct, lifecycle-wrong”

The code often has the right **data fields** but no durable lifecycle owner.

Examples:

- a condition exists on a character, but no subsystem expires it
- an active effect row exists, but it is rebuilt from source state rather than lifecycle-managed
- a room effect exists, but only room gameplay logic knows when to remove it

### 4.2 “Projection as storage”

Runtime actor entities and encounter participant state often end up carrying effect state that is really a **projection**, not a source of truth. This causes synchronization bugs whenever writes happen in more than one lane.

### 4.3 “One-off expiration”

Expiration logic is presently attached to:

- specific actions,
- specific phase handlers,
- specific spell families,
- or daily-prep helpers,

rather than to one generic effect lifecycle service.

### 4.4 “Condition label != mechanical contract”

The system mixes:

- condition labels for UI,
- mechanical adjustments,
- and source/lifecycle metadata

inside ad hoc arrays.

That makes it hard to answer:

- what caused this effect?
- what does it modify?
- when does it expire?
- where should it be rendered?

---

## Target-state architecture in more detail

## 1. Target architectural principles

### Principle 1 — canonical instance, derived projections

Every effect/condition should have **one canonical effect instance**. All views (`character_data`, encounter runtime, room runtime, hexmap overlays, badges, tooltips) are projections.

### Principle 2 — definitions separate from instances

Definitions answer:

- what the effect means,
- what it modifies,
- how it stacks,
- how it expires,
- how it should be rendered.

Instances answer:

- who/what has it,
- when it started,
- whether it is active,
- when it expires,
- what custom parameters it carries.

### Principle 3 — scope is first-class

Every instance must declare exactly one primary scope:

- actor
- encounter participant
- item
- room
- hex
- connection
- campaign

### Principle 4 — expiration is trigger-driven

Expiration should not be embedded inside spell/item/condition-specific business logic whenever the policy can be declared.

### Principle 5 — application and rendering are decoupled

The engine should apply mechanics independently of how the effect is displayed in:

- badges
- sheet conditions
- room summaries
- hex overlays
- chat/system logs

## 2. Target service boundaries

### 2.1 `EffectDefinitionRegistryService`

Responsibilities:

- register definitions for:
  - conditions
  - spell effects
  - item effects
  - room effects
  - hazard auras
  - hex effects
- expose:
  - stacking rules
  - scope rules
  - projection rules
  - tooltip/rendering metadata
  - expiration policies

### 2.2 `EffectInstanceService`

Responsibilities:

- create instance
- refresh instance
- remove instance
- query active instances by scope
- attach source metadata and audit metadata

Suggested methods:

```php
applyEffect(array $definition, array $target_scope, array $source_scope, array $params = []): array
expireEffect(string $effect_instance_id, string $reason): void
refreshEffect(string $effect_instance_id, array $params = []): array
listActiveEffectsByScope(string $scope_type, string $scope_id): array
```

### 2.3 `EffectLifecycleService`

Responsibilities:

- process trigger events:
  - `turn.started`
  - `turn.ended`
  - `round.started`
  - `daily_preparations.completed`
  - `campaign_time.advanced`
  - `room.entered`
  - `room.exited`
  - `hex.entered`
  - `hex.exited`
  - `item.unequipped`
  - `source.removed`
- advance duration counters
- expire effects
- invoke projection refresh

### 2.4 `EffectProjectionService`

Responsibilities:

- project canonical effect instances onto:
  - character effective state
  - encounter participant state
  - runtime actor state
  - room gameplay state
  - hex overlay render payload
  - item runtime state

### 2.5 `EffectScopeResolverService`

Responsibilities:

- normalize IDs and scope keys
- resolve parent/child scope relationships
- support queries like:
  - all room effects affecting this actor
  - all hex effects affecting this occupant
  - all item-granted effects currently attached to this actor

## 3. Target persistence model

### Recommended storage

Introduce a dedicated canonical table such as:

`dc_effect_instances`

Suggested columns:

| Column | Purpose |
|---|---|
| `effect_instance_id` | stable public/canonical identifier |
| `definition_id` | effect/condition definition key |
| `target_scope_type` | actor, room, hex, item, encounter_participant, campaign |
| `target_scope_id` | canonical target identifier |
| `source_scope_type` | spell, feat, equipment, room, hazard, item, GM, system |
| `source_scope_id` | canonical source identifier |
| `stacking_type` | item/status/circumstance/untyped/custom |
| `phase_scope` | persistent-sheet / encounter / room-runtime / campaign-runtime |
| `value_payload_json` | effect parameters |
| `expiration_policy_json` | trigger-driven expiration data |
| `trigger_policy_json` | trigger conditions / refresh behavior |
| `render_payload_json` | tooltip/badge/render hints |
| `created` / `updated` | audit fields |
| `expires_at` | optional absolute expiration |
| `is_active` | soft active flag |
| `expired_reason` | why effect ended |

### Recommended near-term compatibility

Short term:

- keep `combat_conditions`
- keep `character_data.conditions`
- keep `room.gameplay_state.active_effects`

but make them **projection outputs** wherever possible.

---

## Codex implementation handoff plan

## Goal

Move from fragmented effect handling to a unified effect lifecycle subsystem **without breaking live encounter flow, character sheets, room gameplay, or runtime projections**.

## Non-goals for the first implementation wave

- full migration of every historical condition/effect into the new table
- deleting `combat_conditions` immediately
- rewriting every spell/item in one pass
- changing user-facing PF2e rules behavior beyond existing contracts

## Workstream A — foundation and definitions

### Deliverables

1. `EffectDefinitionRegistryService`
2. `dc_effect_instances` schema
3. base definition set for:
   - `flat_footed`
   - `frightened`
   - `wounded`
   - `doomed`
   - `mage_armor`
   - `speed_penalty_*`

### Likely files

- `src/Service/EffectDefinitionRegistryService.php`
- `src/Service/EffectInstanceService.php`
- `dungeoncrawler_content.install`
- `dungeoncrawler_content.services.yml`
- new unit tests under `tests/src/Unit/Service/`

### Acceptance criteria

- definitions resolve by ID
- instances can be created and listed by scope
- instance persistence works
- expiration policy fields are stored, not yet fully executed

## Workstream B — actor persistent effects

### Deliverables

1. `CharacterStateService` reads persistent actor effect instances
2. `CharacterStateService` writes canonical spell/item/condition effects through `EffectInstanceService`
3. `dc_active_effects` becomes compatibility output or is superseded cleanly

### Likely files

- `src/Service/CharacterStateService.php`
- `src/Service/ImpactContractService.php`
- `src/Service/ActiveEffectStoreService.php`
- `tests/src/Unit/Service/CharacterStateService*.php`

### Acceptance criteria

- Mage Armor exists as an effect instance, not only a condition array entry
- AC changes come from projected effect instances
- tooltip metadata can be derived from effect definitions

## Workstream C — encounter convergence

### Deliverables

1. effect trigger hooks for:
   - turn start
   - turn end
   - round start
   - explicit dismiss
2. encounter projection adapter from canonical instances -> tactical runtime
3. compatibility bridge from `combat_conditions`

### Likely files

- `src/Service/EncounterPhaseHandler.php`
- `src/Service/ConditionManager.php`
- `src/Service/CanonicalProjectionService.php`
- `src/Service/RulesEngine.php`

### Acceptance criteria

- existing encounter conditions still function
- spell/condition round expiration is serviced through one lifecycle pathway
- no encounter mutation-envelope regressions

## Workstream D — room and hex effects

### Deliverables

1. room-scoped and hex-scoped effect instance support
2. room/hex trigger policies
3. room gameplay-state and hex overlay projections

### Likely files

- `src/Service/GameplayActionProcessor.php`
- `src/Service/RoomStateService.php`
- `src/Service/NavigationRuntimeService.php`
- `js/hexmap.js`
- `js/v2/**`

### Acceptance criteria

- room auras/hazards can be represented as canonical effect instances
- hex entry/exit can activate or expire effects
- room/hex views read projected data, not custom one-off arrays

## Workstream E — UI and observability

### Deliverables

1. unified tooltip payload generation from definitions + instances
2. effect/condition badges use projection metadata
3. audit/debug views for effect instances and expiration history

### Likely files

- `js/v2/panels/CharacterPanel.js`
- `js/hexmap.js`
- `templates/hexmap-v2.html.twig`
- `templates/character-sheet.html.twig`
- optional admin/debug controller or report

### Acceptance criteria

- every condition/effect badge can explain:
  - source
  - mechanical impact
  - duration
  - expiration trigger
- UI no longer needs ad hoc per-condition tooltip text in multiple places

## Migration order for Codex

1. **Do not start with room/hex effects.** Start with persistent actor effects.
2. Add canonical definitions and instance persistence first.
3. Migrate `mage_armor` as the first pilot.
4. Migrate one encounter condition family next (`frightened`, `flat_footed`).
5. Only then add room and hex scopes.

## Required implementation constraints

1. **Projection is not authority.** Runtime actor state, room gameplay state, and encounter participant payloads must stay derived.
2. **No silent fallbacks.** Missing definitions, invalid scopes, or undeclared mutation targets must fail loudly.
3. **Keep current contracts alive during migration.** Existing UI and encounter consumers should continue reading compatibility projections until cutover is complete.
4. **Preserve mutation-envelope discipline.** Any effect application that mutates actor/room/connection runtime state must declare targeted slices explicitly.

## Test plan for Codex handoff

### Unit tests

- definition registry resolution
- effect instance create/expire/refresh
- actor projection to AC/speed/spells
- encounter trigger expiration
- daily-prep expiration
- room/hex scope resolution

### Integration tests

- cast Mage Armor -> condition/effect appears -> AC rises
- daily preparations -> Mage Armor expires
- frightened decrements correctly in encounter
- room aura applies on entry and clears on exit
- hazard hex effect applies on entry and clears on exit

### Runtime/contract tests

- coordinator full-state includes projected effect data
- Action Rail remains authoritative from action contract
- room runtime payload and actor runtime payload stay shape-stable
- no undeclared runtime-slice mutations

## Concrete first Codex milestone

### Milestone name

`effects-foundation-persistent-actor-v1`

### Definition of done

1. `dc_effect_instances` exists
2. `EffectDefinitionRegistryService` exists
3. `EffectInstanceService` exists
4. `mage_armor` is stored as a canonical effect instance
5. `CharacterStateService` projects `mage_armor` from effect instances into AC
6. daily preparations expire `mage_armor` through lifecycle policy, not special-case direct removal
7. character-sheet tooltip metadata can be built from definition + instance

### Explicit exclusions

- room/hex effects
- `combat_conditions` replacement
- broad item-effect migration
- full GM tooling

---

## Final recommendation

Use the current system as a migration base, but **do not continue scaling it by adding more ad hoc spell/item/room condition paths**.

The repo now has enough pieces to evolve into a proper effect platform:

- canonical character state
- persistent impact storage
- encounter condition storage
- runtime projection services
- room gameplay effect channels

What it lacks is the **unifying lifecycle layer**. That should be the next architectural investment, and the attached Codex handoff plan is structured to deliver it incrementally without destabilizing live gameplay.
