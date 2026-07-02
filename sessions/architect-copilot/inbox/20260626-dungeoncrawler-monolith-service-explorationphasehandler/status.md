# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/ExplorationPhaseHandler.php` (~5149 lines) as a mixed-responsibility exploration monolith combining:
  1. exploration intent routing and action legality contracts,
  2. room/search/perception and transition rule execution,
  3. room-state projection/persistence and narration bridge helpers.
- Coupling profile:
  - room lookup loops were duplicated across `getActiveRoom(...)`, `getActiveRoomIndex(...)`, and `findRoomInDungeon(...)`,
  - duplicated lookup branches increased drift risk for active-room and direct-room retrieval behavior,
  - repeated traversal logic raised maintenance risk when room payload contracts evolve.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - active room resolution must remain deterministic from `active_room_id`,
  - room lookup by ID must return NULL for unknown IDs,
  - helper methods returning room arrays must preserve room object shape.
- Drift risks:
  1. duplicated loops can diverge in null/empty handling,
  2. room-index and room-object paths can drift on future guard changes,
  3. duplicated traversal slows safe decomposition.

### 2026-06-29 — Phased extraction strategy
1. **Room index-lookup seam**
   - extract one shared helper to resolve room index by room_id.
2. **Lookup-path convergence**
   - route active-room index and direct room fetch through the shared helper.
3. **Room accessor segmentation**
   - continue isolating room lookup/accessor logic from action execution methods.
4. **Lookup/mutation boundary hardening**
   - keep pure lookup helpers explicit and free of side effects.
5. **Service thinning**
   - preserve exploration behavior while reducing duplicated room traversal logic.

### 2026-06-29 — Conformance safeguards
- Preserve active-room resolution semantics.
- Preserve unknown-room NULL-return behavior.
- Preserve returned room payload shape.
- Preserve hard-failure/no-swallow posture.

### 2026-06-29 — Test/conformance coverage gaps
- Existing exploration tests focused on feature flows; room-lookup helper contracts were not isolated.
- Missing prior to this increment:
  1. direct helper contract for room index lookup,
  2. direct contract for active-room index+object resolution,
  3. direct contract for generic room lookup by ID.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `findRoomIndexById(...)`,
  - rewired `getActiveRoomIndex(...)`, `getActiveRoom(...)`, and `findRoomInDungeon(...)` to use shared room-index lookup.
- Added targeted unit coverage in `ExplorationPhaseHandlerRoomLookupTest`:
  - `testFindRoomIndexByIdReturnsMatchingIndex`,
  - `testGetActiveRoomResolvesRoomFromActiveId`,
  - `testFindRoomInDungeonReturnsRoomById`.
- Pushed in `dungeoncrawler-content` commit: `fd7b7f2c10`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
