# Dungeoncrawler Navigation: Road Graph Implementation

- Agent: dev-dungeoncrawler
- Delegated: 2026-06-24T11:43:00Z
- Architecture spec: from ceo-copilot-2
- Org status: **disabled — do NOT execute until org is re-enabled by Board**
- Current owner: ceo-copilot-2 (transferred 2026-06-26)

## Context

The architecture spec in `sessions/ceo-copilot-2/inbox/20260623-204502-dungeoncrawler-navigation-road-graph-contract.md` defines an explicit graph-based navigation model with strict distance contracts. This work is **architecture-defined and acceptance-criteria-locked**; it is ready for implementation.

## Implementation plan (retargeted to active stack)

This section is the execution baseline for moving from current hybrid road handling to full server-authoritative road graph distance contracts in the existing PHP/JS codebase.

### Phase 1 — Contract baseline and model normalization

**Goal:** Establish one canonical navigation contract shape in server capabilities and remove ambiguity in source payload parsing.

**Primary seams:**
- `dungeoncrawler-content/src/Service/NavigationService.php`
- `dungeoncrawler-content/tests/src/Unit/Service/NavigationServiceTest.php`

**Scope:**
- Normalize and enforce canonical edge fields at capability projection time:
  - `connection_id`, `origin_room_id`, `target_room_id`,
  - `destination_type` (`room|road`), `destination_id`,
  - `distance` (non-negative int), `bidirectional`, `blocked_reason`.
- Preserve hard-fail contract semantics:
  - missing destination metadata,
  - invalid room distance contracts (`room` edge must be `0`),
  - road edges without room anchor.
- Add/strengthen duplicate-edge conflict detection (same origin+exit label/connection identity but conflicting destination or distance contract).

**Gate to exit phase:**
- Unit tests cover normalization + all contract rejections, including duplicate conflict rejection.

### Phase 2 — Road graph and shortest-path distance resolver

**Goal:** Replace abstract road-network distance behavior with deterministic graph distance computation.

**Primary seams:**
- `dungeoncrawler-content/src/Service/NavigationRoadGraphService.php` (new)
- `dungeoncrawler-content/src/Service/NavigationService.php`
- `dungeoncrawler-content/dungeoncrawler_content.services.yml`
- `dungeoncrawler-content/tests/src/Unit/Service/NavigationRoadGraphServiceTest.php` (new)

**Scope:**
- Introduce explicit road graph primitives in server layer:
  - road nodes,
  - weighted road edges,
  - room-road anchors with `access_distance`.
- Implement deterministic shortest-path resolver (Dijkstra) for road travel.
- Compute travel distance as:
  - `from_access_distance + road_path_distance + to_access_distance`.

**Gate to exit phase:**
- New resolver unit tests pass for direct, single-leg, and multi-leg road traversal.

### Phase 3 — Capability generation integration

**Goal:** Make `NavigationService` the single authority for direct + road capability distances using the resolver output.

**Primary seams:**
- `dungeoncrawler-content/src/Service/NavigationService.php`
- `dungeoncrawler-content/src/Service/EncounterPhaseHandler.php`
- `dungeoncrawler-content/tests/src/Unit/Service/NavigationServiceTest.php`

**Scope:**
- Integrate road-graph resolver into capability construction.
- Replace abstract synthetic `road_network` `distance: 0` behavior with resolved distances where supported by graph data.
- Keep `connection_id`-based transition selection strict and deterministic.
- Remove/limit fallback-only capability generation paths that can drift from `NavigationService` contract shape.

**Gate to exit phase:**
- Capability tests prove resolved distances are emitted for road destinations and transition validation remains strict.

### Phase 4 — Client projection and UX distance semantics

**Goal:** Keep client projection-only while improving distance clarity in action rail copy.

**Primary seams:**
- `dungeoncrawler-content/js/v2/services/action-rail-navigate-panel-service.js`
- `dungeoncrawler-content/js/v2/GameShell.js`
- `dungeoncrawler-content/js/v2/systems/NavigationSystem.js`
- `dungeoncrawler-content/tests/action_rail_*`

**Scope:**
- Continue consuming only server capability payloads; no client-side legality or path computation.
- Render server-provided distance semantics consistently (direct `0`, anchor access distances, resolved compound road distances).
- Preserve `connection_id` dispatch and room transition flow.

**Gate to exit phase:**
- Action-rail contract tests validate display semantics and connection-id propagation remains intact.

### Phase 5 — Integration coverage and deprecation hardening

**Goal:** Lock regression surface with integration tests and mark legacy path behavior for removal.

**Primary seams:**
- `dungeoncrawler-content/tests/src/Unit/Service/NavigationServiceTest.php`
- `dungeoncrawler-content/tests/*navigation*` (existing + new integration coverage)
- `dungeoncrawler-content/src/Service/EncounterPhaseHandler.php`

**Scope:**
- Add scenario coverage for:
  - direct room edge (`distance=0`),
  - direct room→road anchor access,
  - multi-leg road traversal with compounded distance,
  - duplicate/conflicting exit rejection.
- Add explicit deprecation comments on legacy parallel navigation capability assumptions until removal window is approved.

**Gate to exit phase:**
- All navigation contract/unit/integration tests green with no fallback-only distance paths left for primary flows.

### Phase 6 — Release readiness (post org re-enable)

**Goal:** Promote to staging/prod with audit-backed verification.

**Scope:**
- Staging verification pass for representative room→road→room travel.
- QA rerun of production audit path once Board re-enables org automation.
- Monitor blocked-reason and navigation contract logs for drift.

**Gate to complete item:**
- Required tests green, staging behavior confirmed, QA audit rerun complete, and no contract drift findings.

### Progression policy

- Do not advance phases without explicit gate pass and status note update.
- No fallback/recovery masking: contract violations must remain explicit failures.
- Keep server authoritative for legality and distance; client remains projection + intent dispatch only.

## Legacy delegated checklist (superseded)

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

## 2026-06-26 CEO review notes (phase 1)

- **Still valid:** core contract intent (explicit destination metadata, hard-fail validation, server-authoritative distance contract, action-rail distance rendering) remains aligned with current architecture direction.
- **Stale/outdated:** implementation file targets are Dart/Flutter-oriented (`lib/...`, `test/...dart`) and do not match active Dungeoncrawler code surfaces (PHP + JS in `dungeoncrawler-content/src`, `js/v2`, `tests`).
- **Current code state:** significant pieces already exist (`NavigationService` destination/distance validation + `connection_id` propagation + action-rail destination/distance rendering), but road travel distance is still abstracted in synthetic capabilities (`distance: 0`) and not yet full graph shortest-path resolution.
- **Review outcome:** keep this item open, but retarget execution plan to current PHP/JS modules and archive the stale Dart path assumptions.
