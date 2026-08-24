# Tab Runtime Consistency Architecture

**Module**: `dungeoncrawler_content`  
**Status**: Current-state analysis + target-state architecture  
**Scope**: GameShell tabs, action rail, room chat, system log, coordinator state, encounter state projection

---

## Executive summary

The current V2 shell has a **split read architecture**:

1. **Coordinator state** from `GameCoordinatorService` / `/api/game/{campaign_id}/state`
2. **Combat read-model state** from `CombatEncounterApiController` / `/api/combat/state`
3. **Chat/session history** from room/session chat endpoints
4. **Panel-local cached state** inside `GameShell`, `CharacterPanel`, `ActionRailPanel`, and `ChatPanel`

That split causes the observed failures:

- action bar availability can be derived from one snapshot while map/combat visuals use another,
- room chat and map-tab system/action log can show different moments in time,
- transient `422`/`500` responses leave panels rendering stale but plausible state,
- retry/resync paths can update some panels without converging all panels.

The target architecture is:

1. **one authoritative runtime client store**
2. **one authoritative server action/read contract**
3. **one monotonic ordering contract**
4. **multiple projections rendered from the same committed snapshot**

There should be **no backward-compatibility requirement**, **no fallback read authority**, and **no silent failure mode** in the target state.

---

## Problem statement

The current shell behaves like a distributed system without a single client-side state authority.

The server already has a primary orchestration owner:

- `src/Service/GameCoordinatorService.php`
- `src/Service/EncounterPhaseHandler.php`

But the browser still consumes multiple partially-overlapping read surfaces:

- `js/game-coordinator/GameCoordinator.js`
- `js/v2/GameShell.js`
- `js/v2/systems/EncounterSystem.js`
- `js/v2/panels/CharacterPanel.js`
- `js/v2/panels/ChatPanel.js`
- `src/Controller/CombatEncounterApiController.php`

This creates a consistency hazard:

- **mutation authority** is mostly coordinator-driven,
- **combat read authority** is partially duplicated,
- **chat/system-log history** is independently refreshed,
- **tab panels** can render from different stores at the same time.

---

## Current-state architecture

## 1. Current authoritative and non-authoritative surfaces

| Surface | Current code owner | Current role | Problem |
|---|---|---|---|
| Coordinator action authority | `GameCoordinatorService`, `GameCoordinatorController`, `GameCoordinatorApi` | canonical action mutation lane | correct top-level authority, but not the only client read surface |
| Coordinator phase/runtime store | `GameCoordinator.js`, `PhaseManager.js` | main shell runtime state | not the only state consumed by panels |
| Combat read-model endpoint | `CombatEncounterApiController::currentState()` | read-mostly encounter payload for sync/refresh | duplicates encounter read semantics outside coordinator lane |
| Encounter state cache in shell | `GameShell.cacheEncounterServerState()` | panel convenience cache | second client authority |
| Character encounter refresh | `CharacterPanel` fetches `/api/combat/state` | direct panel state refresh | bypasses shared coordinator state |
| Encounter post-action refresh | `EncounterSystem._refreshEncounterServerStateFromApi()` | direct combat-state refresh after actions | bypasses single-store convergence |
| Chat room history | `ChatPanel` room/session view refresh | transcript view state | separate ordering surface from coordinator/action state |
| System log history | `ChatSessionController` + `ChatPanel` `system-log` view | mechanical/event log projection | separate refresh and caching path from room chat |

## 2. Current client flow

### Gameplay mutation path

```text
ActionRail / EncounterSystem
  -> GameCoordinatorApi.sendAction()
  -> /api/game/{campaign_id}/action
  -> GameCoordinatorService.processAction()
  -> EncounterPhaseHandler
  -> response with game_state + available_actions + action_contract
```

This is the correct mutation path.

### Competing read paths

```text
Coordinator bootstrap/read
  -> /api/game/{campaign_id}/state
  -> GameCoordinator phase manager

Encounter refresh/read
  -> /api/combat/state?campaignId=...&roomId=...
  -> GameShell cacheEncounterServerState()
  -> CharacterPanel / encounter helpers

Chat/session reads
  -> room history/session history/system-log endpoints
  -> ChatPanel cache/render path
```

These paths are not guaranteed to converge on the same snapshot before render.

## 3. Current state fracture points

### A. Dual encounter read authority

The shell currently treats both of these as meaningful runtime truth:

- coordinator `game_state`
- `latestEncounterState` cached from `/api/combat/state`

That is the root architecture defect.

### B. Panel-local refresh behavior

`CharacterPanel` and `EncounterSystem` independently fetch `/api/combat/state` and then mutate view behavior from those responses. This means tabs can refresh in different orders and render different versions.

### C. Chat/system-log split chronology

Room chat and system-log are separate session views with separate invalidation and fetch timing. They are related to the same gameplay actions, but are not guaranteed to be derived from the same committed state version or event cursor.

### D. Silent degraded rendering

When state refresh endpoints return `500`, the shell can continue rendering previously cached state. That produces “stable-looking but false” UI:

- action bar appears greyed out from stale availability,
- map tab and room chat disagree,
- combat outcome exists in DB but not in active tab projection.

### E. Mutation acknowledgement before global convergence

An action can succeed after a retry/resync path while one or more follow-up reads fail. The current client can then acknowledge the action and update only part of the shell.

## 4. Observed failure pattern captured from live runs

Observed evidence from campaigns `848` and `849` showed this shape:

1. action request receives `422` during version/race handling
2. client retries and later records success
3. `/api/combat/state` returns `500`
4. `/api/game/{campaign_id}/state` may also return `500`
5. panels continue rendering from mixed cached state
6. room chat and system-log no longer describe the same world moment

This is a **distributed consistency failure**, not only a combat rule failure.

---

## Current-state ownership matrix

| Concern | Current owner | Actual behavior today |
|---|---|---|
| Canonical mutation authority | `GameCoordinatorService` | mostly correct |
| Encounter read authority | split between coordinator + combat endpoint | incorrect |
| Action availability authority | coordinator response, then panel snapshots | partially correct, but drift-prone |
| Turn/round authority | coordinator `game_state`, but combat refresh can influence rendering | split |
| Room chat chronology | chat session history | separate from system-log chronology |
| System/mechanical chronology | system-log session history | separate from room chat chronology |
| Panel state convergence | none | panel-local |
| Failure mode policy | implicit | silent stale rendering possible |

---

## Target-state architecture

## 1. Core rule

**All tabs render from one committed runtime snapshot held by one client authority store.**

No panel may directly own authoritative gameplay state.

## 2. Target client authority model

Introduce one browser authority store:

- **`RuntimeStateStore`**

Owned by:

- coordinator layer only

Consumed by:

- action rail
- character panel
- combat/map presentation
- room chat projection
- system log projection
- quest panel

Panels may keep only:

- selection state
- scroll position
- local UI expansion/collapse state
- pending optimistic request markers

Panels may **not** keep:

- their own authoritative HP
- their own authoritative conditions
- their own authoritative available actions
- their own authoritative round/turn view

## 3. Target server authority model

### Canonical gameplay read/write surfaces

| Purpose | Endpoint family | Target rule |
|---|---|---|
| mutate action | `/api/game/{campaign_id}/action` | canonical |
| full runtime read | `/api/game/{campaign_id}/state` | canonical |
| events since cursor | `/api/game/{campaign_id}/events` | canonical |

### Surfaces to remove from runtime authority

| Surface | Target posture |
|---|---|
| `/api/combat/state` for active shell sync | remove from runtime authority |
| panel-direct authoritative refreshes | remove |
| fallback panel-local canonical cache | remove |

`/api/combat/state` may survive only as an admin/diagnostic read surface, not as tab runtime truth.

## 4. Canonical snapshot contract

Every authoritative runtime payload must include:

```json
{
  "snapshot_id": "opaque-unique-snapshot-id",
  "state_version": 42,
  "event_cursor": 1071,
  "phase": "encounter",
  "encounter_id": 99005,
  "active_room_id": "undead_crypt_entry_hall",
  "turn": {
    "entity": "pc-849-1033",
    "index": 1,
    "actions_remaining": 3,
    "attacks_this_turn": 0,
    "reaction_available": true
  },
  "available_actions": [...],
  "action_contract": {...},
  "runtime_views": {
    "encounter": {...},
    "room_chat": {...},
    "system_log": {...}
  }
}
```

### Required semantics

- `snapshot_id` uniquely identifies one committed runtime state
- `state_version` is monotonic for gameplay mutation order
- `event_cursor` is monotonic for chat/system-log event order
- `runtime_views` are projections of the same committed snapshot, not independent fetch products

## 5. Projection model

The shell should render one authoritative store into many projections:

```text
RuntimeStateStore
  -> ActionRailProjection
  -> EncounterProjection
  -> CharacterProjection
  -> RoomChatProjection
  -> SystemLogProjection
  -> QuestProjection
```

Each projection is pure:

- input: committed snapshot
- output: render model

No projection may fetch alternate authoritative state.

## 6. Unified event timeline

Room chat and system log should become sibling projections of one server event stream.

### Target event classes

| Event class | Example |
|---|---|
| `actor.turn.started` | round/turn starts |
| `action.requested` | client-authorized action accepted |
| `action.resolved` | strike/spell/skill/interact resolution |
| `damage.applied` | HP mutation |
| `condition.applied` | dying/unconscious/prone |
| `narration.public` | room-visible narration |
| `narration.private` | actor-scoped filtered narration |
| `chat.player` | room player line |
| `chat.npc` | NPC reply |
| `chat.gm` | GM reply |

### Projection rule

- **room chat** shows narrative/chat-visible events
- **system log** shows mechanical/system-visible events
- both are ordered by the same canonical event sequence

They must not be assembled from unrelated refresh paths.

## 7. Action rail target-state rules

The action rail must render strictly from:

- authoritative `action_contract`
- authoritative `available_actions`
- authoritative `turn`
- authoritative `snapshot_id`

It must not infer legality from stale selected state alone.

### Required render gate

Action rail render model is valid only when:

1. selected actor matches authoritative `turn.entity` when turn-gated
2. current panel snapshot matches latest committed `snapshot_id`
3. sync health is `healthy`

If any are false, the rail must enter explicit blocked/desynced mode.

## 8. Failure handling policy

Target state has **no silent failure**.

### Hard failure rules

| Failure | Required behavior |
|---|---|
| `422` / version mismatch | one explicit resync transaction, then retry at most once |
| authoritative state read `500` | set shell sync health to `degraded`, freeze gameplay actions that require fresh authority |
| repeated read failure threshold | enter `read_only_desynced` mode and show blocking banner |
| projection build failure | explicit visible error for that view; do not pretend stale state is current |
| missing required payload field | hard-fail payload application and log structured error |

### Forbidden target-state behavior

- no fallback to stale canonical state without visible degraded status
- no panel-local “best guess” action legality
- no hidden downgrade from coordinator authority to combat endpoint authority
- no silent suppression of version drift

## 9. Tab-switch rules

Tab switch is a **view change**, not an authority change.

Therefore:

- tab switch may subscribe to different projections,
- tab switch may not trigger an alternate authoritative read lane,
- tab switch may not mutate runtime state authority,
- tab switch may request projection hydration only from committed store state.

## 10. Observability requirements

Every authoritative apply and panel render should emit structured telemetry with:

- `campaign_id`
- `encounter_id`
- `snapshot_id`
- `state_version`
- `event_cursor`
- `panel_name`
- `sync_health`
- `render_source`

### Required drift metrics

- `panel_snapshot_mismatch_count`
- `room_chat_vs_system_log_cursor_gap`
- `action_contract_snapshot_age_ms`
- `desynced_mode_entry_count`
- `authoritative_read_failure_count`

---

## Future-state ownership model

| Concern | Future owner |
|---|---|
| gameplay mutation authority | `GameCoordinatorService` |
| gameplay read authority | `/api/game/{campaign_id}/state` + `/events` only |
| client authority state | `RuntimeStateStore` |
| tab projections | pure projection builders from committed snapshot |
| action legality | authoritative `action_contract` + `available_actions` only |
| room chat chronology | canonical event stream projection |
| system-log chronology | canonical event stream projection |
| sync health | explicit coordinator-owned client state |

---

## Explicit non-goals

This architecture packet does **not** preserve:

- old panel-local authority patterns
- `/api/combat/state` as runtime truth
- implicit stale-state rendering
- compatibility fallbacks for legacy split-state consumers

The target posture is a **clean authority collapse** onto one runtime store and one server read/write contract.

---

## Related architecture documents

- `ARCHITECTURE.md`
- `README.md`
- `GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md`
- `CHAT_AND_NARRATION_ARCHITECTURE.md`
- `COMBAT_ENGINE_ARCHITECTURE.md`
- `UNIFIED_COMBAT_RUNTIME_SLICE_A_AUTHORITY_MATRIX.md`
