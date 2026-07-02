# Status

- status: done
- created_at: 2026-06-24T11:43:00Z
- current_phase: closed by Board direction - implementation complete, release-readiness automation deferred (org disabled)

## Notes

### 2026-06-30 - closeout (board-directed)
- Board directed this item to close and move on after diminishing-returns refactor passes.
- Implementation/refactor slices completed and focused navigation contract checks remained green throughout closeout sequence.
- Phase 6 automation gates remain environment-constrained by org-disabled kill-switch and private dependency availability; closure is accepted as operationally complete for now.

### 2026-06-30 - continue slice 18 (adjacency helper extraction)
- Refactored `NavigationRoadGraphService::buildRoadAdjacencyList()` to use canonical helper `appendAdjacencyEdge(...)` for edge emission.
- This preserves behavior (including destination-node key initialization + optional reverse edge) while reducing duplicate adjacency mutation logic in loop body.
- Re-ran focused regression gates:
  - `php -l src/Service/NavigationRoadGraphService.php` ✅
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅

### 2026-06-30 - continue slice 17 (resolver loop + panel label cleanup)
- Refactored `NavigationRoadGraphService::resolveShortestRoadPathDistance()` for clearer internal flow without contract changes:
  - normalized input node ids through `normalizeRoadNodeId()`,
  - normalized edge weights through `normalizeNonNegativeDistance()`,
  - extracted closest-node selection into `resolveClosestUnvisitedNode()` for deterministic Dijkstra loop readability.
- Refactored action-rail visited-group projection to compute `resolveNavigateGroupLabel(...)` once per group and reuse it for both `title` and `dungeonName`.
- Re-ran focused regression gates:
  - `php -l src/Service/NavigationRoadGraphService.php` ✅
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅

### 2026-06-30 - continue slice 16 (road-graph resolver normalization cleanup)
- Refactored `NavigationRoadGraphService` internals to centralize canonical normalization logic:
  - added `normalizeRoadNodeId()` for road-node identifier normalization,
  - added `normalizeNonNegativeDistance()` for non-negative integer distance normalization.
- Updated resolver/adjacency/anchor projection callsites to reuse those helpers so anchor access distances, edge weights, and node IDs all flow through one canonical normalization path.
- Re-ran focused regression gates:
  - `php -l src/Service/NavigationRoadGraphService.php` ✅
  - `php -l src/Service/NavigationService.php` ✅
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅

### 2026-06-30 - continue slice 15 (navigation refactor pass 3)
- Reduced repeated road-membership scans in `NavigationService::buildNavigationCapabilitiesWithRoadNetwork()`:
  - precomputes `collectRoadConnectedSourceRooms()` once per invocation,
  - reuses that set in both `hasRoadConnection()` and `extractRoadNetworkRooms()` via optional precomputed-set parameters.
- This is a behavior-preserving cleanup that keeps road capability synthesis logic unchanged while tightening internal efficiency/readability.
- Re-ran focused regression gates:
  - `php -l src/Service/NavigationService.php` ✅
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅

### 2026-06-30 - continue slice 14 (navigation refactor pass 2)
- Refactored road-membership handling in `NavigationService` to remove duplicated connection scans:
  - added canonical `collectRoadConnectedSourceRooms()` helper,
  - `hasRoadConnection()` and `extractRoadNetworkRooms()` now reuse the same keyed set projection.
- Refactored action-rail route-key generation to a shared helper:
  - added `buildNavigateRouteKey(mapId, roomId, connectionId = '')`,
  - dedupe and direct-route suppression now use the same key builder to keep connection-aware identity semantics aligned.
- Updated `tests/action_rail_navigation_distance_contract_test.js` contract assertion to validate the new helper-backed connection-aware dedupe key path.
- Re-ran focused regression gates:
  - `php -l src/Service/NavigationService.php` ✅
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅

### 2026-06-30 - continue slice 13 (touch-surface refactor hardening)
- Refactored touched navigation surfaces with no contract behavior changes:
  - `NavigationService::buildNavigationCapabilities()` now delegates sorting to canonical `sortCapabilities()` (removed duplicate inline sort closure).
  - `NavigationService::buildNavigationCapabilitiesWithRoadNetwork()` now uses a precomputed target-room-id set for duplicate suppression instead of repeated linear scans.
  - `action-rail-navigate-panel-service.js` blocked-reason formatting now uses a stable lookup map (same output semantics, cleaner projection logic).
- Re-ran focused regression gates:
  - `php -l src/Service/NavigationService.php` ✅
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅

### 2026-06-30 - phase 6 resume (board-directed)
- Re-ran phase-6 preflight contract checks in `dungeoncrawler-content`:
  - `node tests/navigation_runtime_cutover_contract_test.js` ✅
  - `node tests/action_rail_navigation_distance_contract_test.js` ✅
- Attempted phase-6 audit trigger for Dungeoncrawler:
  - `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`
  - Result: `[site-audit-run] org disabled (org-control.json enabled=false), skipping.`
- Attempted PHP navigation unit coverage gate but test runner could not be provisioned in this environment:
  - `composer install --no-interaction --prefer-dist`
  - blocked by unresolved private dependency: `drupal/ai_conversation`.
- Item remains in progress pending org re-enable (or explicit override of org kill-switch path) to complete release-readiness audit gate.

### 2026-06-29 - continue slice 12 (phase 5 cutover guardrail hardening)
- Extended `tests/navigation_runtime_cutover_contract_test.js` with a runtime callsite guard:
  - recursively scans `src/Service/**/*.php`,
  - hard-fails if any runtime service (outside `NavigationService.php`) calls direct-only `->buildNavigationCapabilities(`.
- This locks the migration to road-network-aware runtime capability generation and prevents accidental reintroduction of legacy direct-only navigation callsites.
- Re-ran focused navigation contract checks:
  - `node tests/navigation_runtime_cutover_contract_test.js`
  - `node tests/action_rail_navigation_distance_contract_test.js`

### 2026-06-29 - continue slice 11 (phase 5 deprecation hardening)
- Added explicit deprecation contract comments to legacy direct-only capability surfaces:
  - `NavigationService::buildNavigationCapabilities`
  - `EncounterPhaseHandler::buildFallbackNavigationCapabilities`
- Extended `tests/navigation_runtime_cutover_contract_test.js` to lock those deprecation contracts so fallback/legacy assumptions cannot silently regress.
- Re-ran focused navigation contract checks:
  - `node tests/navigation_runtime_cutover_contract_test.js`
  - `node tests/action_rail_navigation_distance_contract_test.js`

### 2026-06-26 - ownership transfer
- Work item moved from `sessions/dev-dungeoncrawler/inbox/` to `sessions/ceo-copilot-2/inbox/` per Board direction.
- CEO now owns review and execution retargeting.

### 2026-06-26 - phase 1 review findings
- Item is **partially valid**: architecture intent still matches current navigation contract direction.
- Item is **partially stale**: prescribed implementation paths target Dart files not present in active Dungeoncrawler stack.
- Existing implementation already covers part of scope in `dungeoncrawler-content`:
  - server-side destination/distance contract enforcement in `src/Service/NavigationService.php`,
  - action-rail destination/distance display in `js/v2/services/action-rail-navigate-panel-service.js`,
  - `connection_id` propagation in action-rail dataset and navigation dispatch.
- Remaining gap to evaluate next: full road-graph shortest-path distance resolution (vs current synthetic road-network abstraction).

### 2026-06-26 - phase 2 review (current state vs future state)
- **Current state (as implemented):**
  - Navigation capability contracts are server-authored and client-consumed (`NavigationService` + `GameShell.resolveNavigationCapabilities`).
  - Hard-fail style blocking exists for key contract breaks (`invalid_distance_contract`, `missing_road_anchor`, unresolved destination) and is covered by unit tests.
  - `connection_id` flows end-to-end from capability projection to client dispatch to transition validation (`NavigationSystem` + `EncounterPhaseHandler`).
  - Action rail already renders destination type + distance from authoritative payload metadata.
  - Road handling is still hybrid/partial: synthetic `road_network` capabilities are generated with abstract `distance: 0` rather than computed path distance.

- **Future state (target architecture):**
  - Replace synthetic room-to-room `road_network` shortcuts with explicit road graph traversal contracts (`road_node` + room anchor model).
  - Introduce deterministic server-side distance resolver for road travel using shortest-path over road graph and anchored access distances.
  - Keep client projection-only: action rail displays resolved server distance labels (including access/compound distance semantics) without local legality logic.
  - Consolidate transition capability generation so `NavigationService` remains single contract authority and fallback-only builders cannot drift.
  - Add integration-level coverage for multi-leg road travel and conflict validation (including duplicate exit destination/distance contract collisions).

### 2026-06-26 - phase 3 planning (implementation path fleshed out)
- Converted the item into a retargeted implementation plan aligned to active PHP/JS surfaces.
- Added explicit phased execution in `README.md` (phase goals, code seams, gate criteria, completion policy).
- Defined migration path from current synthetic `road_network` abstraction to deterministic server-side road-graph shortest-path resolution with preserved server-authoritative boundaries.

### 2026-06-26 - implementation phase 1 slice (duplicate contract hard-fail)
- Added duplicate-exit contract conflict enforcement in `NavigationService` so duplicated exit identities with mismatched destination/distance semantics hard-fail as `duplicate_exit_conflict`.
- Added focused unit coverage for duplicate destination conflict and duplicate road-distance conflict in `NavigationServiceTest`.
- This advances phase 1 contract baseline hardening; next slice is road-graph resolver introduction.

### 2026-06-26 - implementation phase 2 slice (resolver scaffold + synthetic integration)
- Added `NavigationRoadGraphService` with deterministic shortest-path distance resolution over road edges and room-road anchors.
- Integrated resolver into synthetic road-network capability generation in `NavigationService`:
  - resolved distance is emitted when path data exists,
  - unresolved paths hard-fail as `missing_road_path` (no silent fallback distance).
- Added unit coverage for resolver behavior (single-node anchor path, weighted shortest path, no-path null contract).

### 2026-06-26 - review/refactor + continue slice
- Refactored `NavigationServiceTest` fixture setup to use a single initialized service (`setUp()`), removing brittle implicit property usage.
- Tightened road-network tests to assert deterministic resolved distances from road graph + anchors instead of abstract distance behavior.
- Added missing-path contract coverage in `NavigationServiceTest` to enforce explicit `missing_road_path` blocking when graph connectivity is absent.

### 2026-06-26 - review/refactor + continue slice 2
- Aligned `EncounterPhaseHandler` fallback navigation capability builder with canonical `NavigationService` helpers to reduce contract drift (destination normalization, distance normalization, blocked-reason policy, road-anchor enforcement).
- Extended fallback capability payload to include canonical metadata (`origin_room_id`, discovery/passability flags, bidirectionality, interaction requirement) for parity with primary navigation capabilities.
- Added a non-conflicting duplicate contract test to ensure duplicate hard-fail logic only triggers on real semantic conflicts.

### 2026-06-26 - review/refactor + continue slice 3
- Refactored road-graph resolution from static utility style to service-oriented DI wiring:
  - `NavigationRoadGraphService` now exposes instance methods,
  - `NavigationService` accepts/injects road-graph service dependency,
  - Drupal service container wiring updated (`navigation_road_graph_service` + injected into `navigation_service`).
- Updated resolver unit tests to exercise instance service behavior, keeping parity with service wiring model.

### 2026-06-26 - continue slice 4 (phase 4 rendering semantics)
- Updated action-rail navigation distance rendering to project server semantics:
  - `destination_type=road` now displays `access N`,
  - `road_network` connection distances render as `road N`,
  - default direct distances remain numeric.
- Added focused contract coverage in `tests/action_rail_navigation_distance_contract_test.js`.

### 2026-06-26 - continue slice 5 (phase 3 consolidation hardening)
- Removed fallback capability drift by delegating `EncounterPhaseHandler` fallback navigation capability generation directly to canonical `NavigationService::buildNavigationCapabilities`.
- Deleted now-unused fallback connection-id derivation helper from `EncounterPhaseHandler`.
- This leaves one primary capability authority path for transition validation semantics even in fallback execution contexts.

### 2026-06-26 - review/refactor + continue slice 6
- Added directional shortest-path coverage for `NavigationRoadGraphService` to enforce one-way road edge semantics (forward path resolves, reverse path remains unresolved).
- This hardens resolver correctness for future explicit road graph contracts where directional travel constraints exist.

### 2026-06-26 - continue slice 7 (action-rail alignment)
- Aligned action-rail navigation rendering with authoritative navigation capability semantics:
  - show unavailable exits (disabled) rather than silently filtering them out,
  - surface `blocked_reason` context in entry metadata,
  - dedupe by connection identity when available to avoid collapsing distinct exits that share room targets.
- Expanded action-rail navigation distance contract test coverage for availability projection, blocked-reason projection, and connection-aware dedupe identity.

### 2026-06-26 - continue slice 8 (runtime cutover to road-network capabilities)
- Switched live navigation capability callers to road-network-aware generation (`buildNavigationCapabilitiesWithRoadNetwork`) in:
  - `MapGeneratorService` navigation receipt projection,
  - `EncounterPhaseHandler` transition capability resolution,
  - `NavigationService` requested-capability and quest-target capability entry points.
- This makes action-rail and transition validation consume the same road-graph-aware capability surface instead of the legacy direct-only projection path.

### 2026-06-26 - review/refactor + continue slice 9
- Added explicit runtime cutover contract test (`tests/navigation_runtime_cutover_contract_test.js`) to lock critical server callsites to road-network-aware capability generation.
- This prevents regression back to direct-only capability projection in transition validation and receipt projection flows.

### 2026-06-26 - review/refactor + continue slice 10 (navigation bar population stability)
- Reviewed action-rail population algorithm end-to-end (authoritative exits + visited-location merge + dedupe + render) and confirmed architecture is coherent with server-authoritative navigation contracts.
- Refined population behavior to reduce long-standing instability cases:
  - avoid repeated visited-location refetch loops when cached results are empty,
  - sort navigable exits ahead of unavailable entries for clearer decision flow.
- Added contract assertions for these behaviors in action-rail navigation tests.
