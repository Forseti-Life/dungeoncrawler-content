# Dungeoncrawler Navigation: Road Graph Implementation

- Agent: dev-dungeoncrawler
- Delegated: 2026-06-24T11:43:00Z
- Architecture spec: from ceo-copilot-2
- Org status: **disabled — do NOT execute until org is re-enabled by Board**

## Context

The architecture spec in `sessions/ceo-copilot-2/inbox/20260623-204502-dungeoncrawler-navigation-road-graph-contract.md` defines an explicit graph-based navigation model with strict distance contracts. This work is **architecture-defined and acceptance-criteria-locked**; it is ready for implementation.

## What to do

Implement the navigation model in `dungeoncrawler-content` with these exact deliverables:

### 1. Navigation Capability Payload Extension
**File:** `lib/navigation/models.dart` (or equivalent)

Define and export:
- `ExitContract`: from_room_id, exit_label, to_type (enum: room|road), to_id, distance, bidirectional
- `RoomNode`: canonical room entity
- `RoadNode`: marker on a named road with ordered position
- `RoomRoadAnchor`: room-to-road mapping with access_distance

**Acceptance criteria:**
- [ ] All four types are exported and fully typed
- [ ] Distance is always a non-negative int; direct room edges MUST validate distance == 0
- [ ] Road nodes are strictly ordered within a road graph
- [ ] Serialization roundtrips correctly

### 2. Server Validation (Hard-fail contract enforcement)
**File:** `lib/navigation/validation.dart`

Implement validators that **reject** (throw, return validation error) when:
- [ ] Exit destination metadata is missing
- [ ] Direct room edge has non-zero distance
- [ ] Road-connected room lacks a road anchor
- [ ] Duplicate exits conflict on destination or distance

All four rejections must be tested with unit tests. Hard-fail only — no silent fallbacks.

### 3. Action Rail UI Rendering
**File:** `lib/ui/action_rail.dart` (or component equivalent)

Update exit rendering to show:
```
Exit Label -> Destination (type/name) -> Distance
```

Examples:
- `North Door -> Armory (room) -> 0`
- `Gate to Road -> The Road -> access 2`
- `Road travel -> Millhouse -> 14`

**Acceptance criteria:**
- [ ] All three pieces (label, destination, distance) render
- [ ] Distance shows as literal number for direct edges, "access N" for road anchors, compound distance for multi-leg road travel
- [ ] Design is consistent with existing action rail style

### 4. Distance Resolution Logic
**File:** `lib/navigation/distance_resolver.dart`

Implement distance calculation:
```
total = from_access_distance + road_path_distance + to_access_distance
```

Where:
- from_access_distance = anchor distance from current room to road
- road_path_distance = shortest path over road graph
- to_access_distance = anchor distance from road to destination room

**Acceptance criteria:**
- [ ] Direct room edges always return 0
- [ ] Single-leg road travel calculates correctly
- [ ] Multi-leg travel (room → road → road → room) compounds correctly
- [ ] Shortest path uses well-known algorithm (Dijkstra or similar)

### 5. Integration Tests
**File:** `test/navigation_integration_test.dart`

Test matrix:
- [ ] Direct room edge: exit says destination (room) with distance 0 → verify distance_resolver returns 0
- [ ] Direct to road anchor: exit says destination (road) with access distance 2 → verify distance_resolver returns 2
- [ ] Multi-edge path (room A → road at +1 → road intermediate → road exit at +2 → room B at +1) → verify total = 1 + road_path + 1
- [ ] Validation rejects missing destination metadata
- [ ] Validation rejects direct edge with non-zero distance
- [ ] Validation rejects road-connected room without anchor

## Implementation Notes

### Pass connection_id end-to-end (for debugging + auditing)
When a transition is initiated, include `connection_id` (exit's unique identifier) in the payload all the way through the server validation and distance resolution. This enables:
- Server logs to trace exact exit used
- Client to correlate its action with server outcome
- Future audit of navigation contracts in production

### Server is single source of truth
The server's navigation capability payload (after validation) is the **only** place the distance contract lives. Client chat should only render what the server provides. Do not compute distances client-side.

### Deprecation path
The legacy parallel navigation endpoint (if it exists) should be marked deprecated in code comments. Do not delete yet — wait for CEO coordination to deprecate across all consuming clients.

## Verification Method

When org is re-enabled:
1. Deploy to staging
2. Run integration tests — all five categories must PASS
3. Manual QA: walk a room → road → room path and verify action-rail shows correct distance
4. Audit staging logs for no validation errors (hard-fails caught in dev)
5. Run Dungeoncrawler production audit (qa-dungeoncrawler) with `ALLOW_PROD_QA=1` flag

## ROI Estimate
**ROI: 8** — This unblocks navigation polish and sets up the foundation for route planning, teleport validation, and movement cost modeling.

## Notes
- Do **NOT** wait for architect approval — this is delegated from CEO based on architecture spec already written
- This work is **blocked by org re-enable** — do not attempt execution until orchestrator is running or you receive explicit Board authorization
- If you hit a blocker while prepping (e.g., need clarity on exact road graph data structure), escalate to CEO

