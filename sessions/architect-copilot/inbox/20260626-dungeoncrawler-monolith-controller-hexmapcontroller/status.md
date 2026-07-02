# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Controller decomposition audit
- Audited `src/Controller/HexMapController.php` (~3280+ lines) as a high-coupling launch/render monolith with multiple embedded domains:
  1. launch request normalization and campaign-access gating,
  2. dungeon payload assembly/mutation pipeline,
  3. character/runtime projection and portrait hydration,
  4. room/NPC/quest injection and contract normalization,
  5. API/render delivery surfaces (`demo`, `visualState`).
- Coupling profile:
  - controller owns both HTTP delivery and large domain mutation/projection orchestration,
  - launch and visual-state delivery share contracts but had duplicated orchestration paths,
  - downstream helper chain depth is high, increasing sequencing drift risk.

### 2026-06-29 — Contract map and drift risks
- Core contracts:
  - launch context canonicalization from query + runtime hydration,
  - campaign ownership hard-failure gating,
  - canonical visual-state API shape used by client projections,
  - deterministic dungeon payload mutation order before map projection.
- Drift risks:
  1. duplicated launch orchestration across `demo` and `visualState` can diverge access/order semantics,
  2. API payload assembly inline in endpoint method can drift from canonical frontend contract,
  3. deep pipeline order changes can silently alter visual-state projection behavior.

### 2026-06-29 — Phased extraction strategy
1. **Launch orchestration extraction**
   - Consolidate request-context -> access -> state-bundle path into one shared launch orchestration boundary.
2. **Response projection extraction**
   - Isolate canonical visual-state payload projector used by API and any non-page consumers.
3. **Pipeline segmentation**
   - Segment dungeon mutation pipeline into explicit pre-injection, entity-injection, and finalization phases.
4. **Domain service split**
   - Move portrait/NPC/quest/dungeon normalization concerns into dedicated service modules with explicit contracts.
5. **Controller thinning**
   - Retain controller as route facade + response mapper only.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure campaign-access enforcement behavior.
- Preserve launch context hydration precedence (query-explicit values vs persisted runtime values).
- Preserve visual-state payload shape and canonical key naming.
- Preserve dungeon payload mutation order before visual-state projection.

### 2026-06-29 — Test/conformance coverage gaps
- Existing functional tests cover route-level demo/API behavior and selected launch/contract expectations.
- Missing before deeper extraction:
  1. shared launch orchestration parity tests across page/API surfaces,
  2. visual-state payload projection unit snapshots,
  3. mutation pipeline stage-order assertions for deterministic payload outcomes.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented refactor increment in `dungeoncrawler-content`:
  - extracted `resolveLaunchStateBundle(...)` to unify launch access + state-bundle orchestration,
  - extracted `buildVisualStatePayload(...)` for canonical API payload assembly,
  - rewired `demo` + `visualState` to consume shared helpers.
- Added unit coverage `HexMapControllerVisualStatePayloadTest` for visual-state payload projection contract.
- Pushed in `dungeoncrawler-content` commit: `1b7cf6f7cc`.

### 2026-06-29 — Completion
- Delivered decomposition plan, safeguards, contract risk map, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
