# Next Map-Tab Slice Analysis

## Current Boundary

The strict canonical visual-state cutover is complete. All display surfaces (room summaries, action-rail interactables, inspector, navigation listings) now source exclusively from canonical `map_visual_state` with no payload fallback.

## Remaining Payload Reads

Audit of `js/hexmap.js` shows remaining `dungeonData` reads are concentrated in two intentional boundaries:

### 1. Encounter/Combat Management (Lines 8757-8759, 2690, 5293)
- `initiative_order` from `game_state.initiative_order`
- Entity filters for ally NPC selection in combat UI
- These belong to gameplay/ECS flows, not visual display

### 2. Room Creation/Navigation Mutations (Lines 8000-8069)
- Payload injection when creating new rooms during navigation
- Writing entities/connections back to payload for persistence
- These are mutation flows that require richer runtime state

### 3. Navigation Context Resolution (Lines 9093, 9195, 9398-9406)
- `dungeonId`, `level_id`, `map_id` reads for context propagation
- Connection reads for navigation capability derivation
- These are part of the active-room initialization flow

## Decision: Leave These As-Is

Per the stored architectural decisions, this is a visual-only cutover, not a full gameplay/ECS migration. The remaining payload reads serve:
- **Gameplay loops**: encounter management, combat state tracking
- **Mutation flows**: room creation, entity persistence, connection management
- **Context flows**: navigation initialization, dungeon/level/map scoping

These intentionally remain separate from the visual display layer because:
1. They require richer runtime state (position, health, initiative, etc.)
2. They write mutations back to payload for persistence
3. They support gameplay systems, not pure display rendering

## Unresolved Blockers

1. **BrowserTest environment issue** (403 on `/hexmap/demo`)
   - Blocks functional test rerun verification
   - Environment/host setup issue, not a contract regression
   - Requires separate investigation

## Recommended Next Slices

1. **Portrait/merchant UI helpers** (if prioritized)
   - Likely still reading payload entities for NPC/item display
   - Similar cutover pattern to action-rail interactables

2. **BrowserTest investigation** (if prioritized)
   - Resolve 403 route-access for `/hexmap/demo`
   - Restore functional test coverage

3. **Legacy bootstrap removal** (deferred)
   - Only after all visual display is confirmed canonical-only
   - Keep `hexmapDungeonData` for gameplay/mutation flows

## Conclusion

The strict visual-state cutover is complete and verified green across all test suites. The remaining payload reads are intentionally separated in gameplay/ECS and mutation flows, which are outside the scope of this display-layer cutover.

**Status: Boundary Clear, Next Slice Identified**
