# H3 Placement Target Architecture and Reconciliation Plan

**Status:** Proposed target architecture  
**Scope:** Dungeon room cells, actors, followers, objects, and connector endpoints  
**Primary goal:** Make canonical H3 room-cell identity the system of record for placement and reduce `q/r` to a derived room-local projection

---

## 1. Executive summary

The current placement model is split between:

- actor/runtime storage that persists `room_id + position_q + position_r`
- sparse room-cell authority that persists `room_id + h3_index + source_q + source_r`
- runtime payloads that sometimes carry both `placement.hex` and `placement.h3_index_res14`

That split forces runtime code to reconstruct canonical H3 placement from `q/r`, creates fallback complexity, and makes connector/actor placement vulnerable to drift.

**Target state:**

1. A room owns canonical H3 cells
2. An actor references one owned H3 cell
3. A connector endpoint references one owned H3 cell
4. `q/r` is derived from the room-owned H3 cell for rendering and local pathing only

---

## 2. Problem statement

### 2.1 Current authority split

Today the codebase uses two placement authorities:

| Surface | Current authority | Problem |
|---|---|---|
| `dc_campaign_characters` | `last_room_id/location_ref` + `position_q/position_r` | actors are not H3-native |
| `dungeoncrawler_content_h3_room_cells` | `dungeon_id + room_id + h3_index + source_q/source_r` | canonical room-cell authority exists but is not actor-native |
| `dc_campaign_connections` | endpoint `from_hex_q/from_hex_r/to_hex_q/to_hex_r` | connectors are not H3-native |
| runtime payloads | `placement.hex` and often `placement.h3_index_res14` | runtime shape is ahead of persisted storage |

### 2.2 Observed runtime consequences

Observed during incident analysis:

1. Sparse snapshots often omit dense `rooms[].hexes` and `entities`
2. Runtime sync must fall back to sparse room-cell lookup
3. Actors commonly have valid room + `q/r`, but not persisted H3
4. Followers may have neither valid room nor valid placement and require deterministic defaulting

### 2.3 Resolution mismatch

Another inconsistency exists in the current design:

- `dungeoncrawler_content_h3_room_cells.h3_resolution` defaults to **13**
- active runtime validation and placement logic hard-gates on **res14**
- runtime payloads carry `h3_index_res14`

This architecture therefore already assumes **res14 is the active gameplay placement resolution**, even where older storage defaults still say res13.

---

## 3. Current-state findings

### 3.1 Actors

Runtime actor rows in `dc_campaign_characters` currently persist:

- `location_ref`
- `last_room_id`
- `position_q`
- `position_r`

They do **not** persist a canonical actor H3 identifier.

Incident analysis confirmed:

- Burasco, Eldric, Marta the Scholar, and Gribbles Rindsworth had valid room + `q/r`
- those `q/r` values mapped to real room-owned sparse H3 cells
- Mimi did not have valid persisted room placement and required fallback assignment

### 3.2 Sparse room-cell authority

`dungeoncrawler_content_h3_room_cells` already represents the correct authority layer. It persists:

- `dungeon_id`
- `room_id`
- `cell_role`
- `h3_resolution`
- `h3_index`
- `source_q`
- `source_r`
- `center_latitude`
- `center_longitude`

This table already expresses the canonical relationship:

> one room owns a set of canonical H3 cells, each of which may also expose a room-local `q/r` projection

### 3.3 Connectors

`dc_campaign_connections` currently persists connector endpoints as `q/r`:

- `from_hex_q`
- `from_hex_r`
- `to_hex_q`
- `to_hex_r`

It does not persist canonical H3 endpoints.

### 3.4 Runtime payloads

Runtime entity payloads commonly already expose:

```json
{
  "placement": {
    "room_id": "tavern_entrance",
    "hex": { "q": -4, "r": -3 },
    "h3_index_res14": "8f..."
  }
}
```

This shape is close to the target architecture, but persisted storage still treats `q/r` as primary.

---

## 4. Target architecture

## 4.1 Placement identity model

Every placeable thing must be identified by the triple:

- `dungeon_id`
- `room_id`
- `h3_index_res14`

This is the only canonical placement key for:

- PCs
- NPCs
- followers / familiars / companions
- hazards
- interactables
- objects
- room anchors
- connector endpoints

### Derived projection

The following values are derived, not authoritative:

- `q`
- `r`
- `latitude`
- `longitude`

---

## 4.2 Room ownership model

Each room owns a set of canonical res14 H3 cells in `dungeoncrawler_content_h3_room_cells`.

That ownership model becomes authoritative for:

1. room membership
2. walkable placement
3. room anchor cells
4. connector endpoints
5. actor/object placement validation

**Invariant:** no runtime entity or connector endpoint may reference a cell not owned by its room.

---

## 4.3 Actor storage model

### Required persisted actor fields

`dc_campaign_characters` should persist:

- `last_room_id`
- `location_ref`
- `position_h3` (res14 authority)

### Optional cached projection fields

- `position_q`
- `position_r`

These may remain for performance or compatibility during migration, but they are no longer authoritative.

**Conflict rule:** if cached `q/r` disagrees with H3-derived `q/r`, the H3-derived projection wins.

---

## 4.4 Connector storage model

`dc_campaign_connections` should persist:

- `from_room_id`
- `to_room_id`
- `from_h3_index_res14`
- `to_h3_index_res14`

Optional cached fields:

- `from_hex_q`
- `from_hex_r`
- `to_hex_q`
- `to_hex_r`

Connector semantics must bind to room-owned H3 cells first, not to free-floating axial coordinates.

---

## 4.5 Runtime payload model

Every runtime entity placement must expose:

```json
{
  "room_id": "tavern_entrance",
  "h3_index_res14": "8f28308280f18ff",
  "hex": {
    "q": -4,
    "r": -3
  }
}
```

### Runtime payload rules

1. `placement.h3_index_res14` is authoritative
2. `placement.hex.q/r` is derived from room-owned H3 cell projection
3. `placement.room_id` must match the ownership room of that H3 cell

---

## 4.6 Sparse snapshot model

Sparse snapshots remain valid if they preserve:

- `room_ids`
- connector identities
- runtime entity `placement.h3_index_res14`
- enough room-cell authority to project room-local `q/r`

Dense `rooms[].hexes` and `entities[]` are delivery optimizations, not placement truth.

---

## 5. Architectural invariants

### Invariant A — H3 wins

If an H3 placement identifier exists, it is authoritative over `q/r`.

### Invariant B — room ownership must hold

If an actor, object, or connector endpoint H3 identifier is not owned by the persisted room, hard-fail.

### Invariant C — migration may derive once

If H3 is absent but `room_id + q/r` exists, resolve H3 from sparse room-cell authority once and persist it immediately.

### Invariant D — no free-floating connector endpoints

Connector endpoints must always resolve to canonical room-owned H3 cells.

### Invariant E — deterministic startup defaults

If a required actor has no valid placement:

1. try the resolved owner room
2. otherwise use the bootstrap room
3. otherwise hard-fail

For current startup expectations, the bootstrap/default room is `tavern_entrance`.

---

## 6. Reconciliation strategy

The migration should not attempt to support dual authorities indefinitely. The plan is:

1. add canonical H3 fields
2. backfill them from room-cell authority
3. flip readers to H3-first
4. demote `q/r` to cache/projection status

---

## 7. Migration plan

## Phase 1 — schema extensions

### 7.1 Actor runtime rows

Add to `dc_campaign_characters`:

- `position_h3` (stores the canonical actor H3 index at resolution 14)

### 7.2 Connector rows

Add to `dc_campaign_connections`:

- `from_h3_index_res14`
- `to_h3_index_res14`

### 7.3 Optional future normalization

If needed later, consider explicit runtime tables for actor placement and connector placement history, but that is not required for the first reconciliation step.

---

## Phase 2 — authoritative backfill

### 7.4 Actor backfill

For each runtime actor row:

1. resolve room from `last_room_id` or `location_ref`
2. read `position_q/position_r`
3. resolve matching sparse room cell by:
   - `dungeon_id`
   - `room_id`
   - `source_q`
   - `source_r`
4. persist `position_h3`

If no matching room-owned cell exists, hard-fail and report the row as invalid.

### 7.5 Follower repair

Followers like Mimi that lack valid room + placement need deterministic repair:

1. if owner actor has valid H3 placement, assign follower to unused adjacent or unused same-room canonical H3 cell
2. if owner room is unavailable during bootstrap, assign unused canonical H3 cell in `tavern_entrance`
3. persist both room and H3

### 7.6 Connector backfill

For each connector row:

1. resolve `from_room_id + from_hex_q/from_hex_r`
2. resolve `to_room_id + to_hex_q/to_hex_r`
3. map each endpoint to a room-owned sparse H3 cell
4. persist `from_h3_index_res14` and `to_h3_index_res14`

---

## Phase 3 — H3-first runtime readers

Update the runtime readers to use H3 first:

1. read persisted H3
2. validate room ownership
3. derive `q/r` from sparse room-cell projection
4. use `q/r` fallback only during migration window

### Priority code paths

- `CampaignCharacterRuntimeSyncService`
- `CampaignCharacterRuntimeResolverService`
- `EncounterPhaseHandler`
- `ExplorationPhaseHandler`
- `NavigationRuntimeService`
- `HexMapController`
- `RuntimeGraphAssemblerService`

---

## Phase 4 — H3-native payload generation

Require builders to emit canonical H3 placement for:

- runtime entities
- encounter participants
- follower entities
- connector endpoints
- room entry / exit anchors

`hex.q/r` remains in payloads as derived output.

---

## Phase 5 — de-authorize q/r

After backfill and reader cutover:

1. stop treating `position_q/position_r` as authoritative for actors
2. stop treating `from_hex_* / to_hex_*` as authoritative for connectors
3. keep them only as cached projection, or remove them in a later cleanup migration

---

## 8. Tactical defaults required immediately

Until actor H3 storage is fully cut over, the runtime must still behave deterministically.

### Required immediate behavior

1. player placement fallback must choose an unused canonical room-owned cell
2. follower placement fallback must choose an unused canonical room-owned cell
3. if no resolved room is available, default to an unused cell in `tavern_entrance`

This requirement applies directly to startup actors such as:

- Burasco
- Mimi

---

## 9. Validation and enforcement plan

Validation must be expanded to enforce:

1. every persisted actor H3 identifier belongs to the persisted room
2. every connector endpoint H3 identifier belongs to its endpoint room
3. every runtime entity payload carries `placement.h3_index_res14`
4. every referenced H3 cell exists in `dungeoncrawler_content_h3_room_cells`
5. runtime placement logic does not silently invent out-of-room placements

### Additional consistency checks

1. detect rows where `position_q/position_r` no longer project to persisted H3
2. detect connector endpoints whose cached `q/r` no longer project to endpoint H3
3. detect sparse room-cell rows at a non-active resolution when runtime expects res14 placement

---

## 10. Refactoring work plan

### Workstream A — schema

1. add actor H3 field
2. add connector endpoint H3 fields

### Workstream B — backfill

1. actor backfill
2. follower repair
3. connector backfill

### Workstream C — runtime readers

1. refactor runtime sync to H3-first
2. refactor encounter/exploration transition placement to H3-first
3. refactor launch/bootstrap readers to H3-first

### Workstream D — runtime writers

1. write H3 on new actor materialization
2. write H3 on follower materialization
3. write H3 on connector materialization
4. write H3 on movement / transition persistence

### Workstream E — validation and tests

1. add unit coverage for sparse H3 placement
2. add regression coverage for harness snapshot/bootstrap
3. add contract coverage for connector H3 endpoints
4. add validator gates for H3 ownership consistency

---

## 11. Cutover criteria

The architecture is considered reconciled when:

1. actor placement is persisted as canonical H3 identity
2. connector endpoint placement is persisted as canonical H3 identity
3. runtime readers no longer need `q/r` as primary lookup keys
4. sparse snapshots can rehydrate room-local projection from canonical H3 ownership alone
5. startup/bootstrap logic does not depend on dense payload `rooms[].hexes`
6. no runtime path depends on free-floating `q/r` without room-owned H3 resolution

---

## 12. Recommended implementation order

1. add actor H3 field
2. add connector H3 endpoint fields
3. backfill actors
4. repair follower defaults
5. backfill connectors
6. refactor runtime sync to H3-first
7. refactor movement / encounter / exploration placement to H3-first
8. update payload builders
9. add validation hard gates
10. demote `q/r` to derived cache status

---

## 13. Final architecture statement

The target system is:

- **room owns canonical H3 cells**
- **actor references a room-owned H3 cell**
- **connector references room-owned H3 endpoint cells**
- **runtime payload exposes authoritative H3 plus derived q/r**
- **q/r is no longer a placement authority**

That removes the current split authority and converts sparse room-cell storage from a recovery fallback into the canonical placement model for the entire runtime.

---

## 14. Resolution and default policy (humanoids)

### Active gameplay placement resolution

- **Humanoids (PCs and NPCs) operate at H3 resolution 14.**
- Persisted actor field `dc_campaign_characters.position_h3` is the canonical res14 placement identity.
- Runtime payload field `placement.h3_index_res14` is authoritative and must match persisted `position_h3`.

### Default placement behavior

- If a humanoid lacks valid persisted placement at startup, assign an **unused canonical res14 cell in `tavern_entrance`**.
- This default applies to both the player character (e.g. Burasco) and follower actors (e.g. Mimi) when deterministic owner-room placement cannot be resolved.
