# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/MapVisualStateProjector.php` (~1350 lines) as a mixed-responsibility projection monolith spanning:
  1. room/hex topology projection,
  2. connection normalization and per-room exit assembly,
  3. occupant/presentation legend shaping.
- Coupling profile:
  - room-exit payload assembly duplicated forward/reverse endpoint shaping inline in `attachRoomExits(...)`,
  - duplicated payload blocks increased drift risk for origin/target endpoint contracts.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - room exits must preserve canonical endpoint envelope keys (`origin_hex`, `target_hex`, `is_passable`, `is_discovered`, `visibility_state`),
  - reverse exits must mirror endpoint origin/target mapping for the opposite room,
  - connection-level passability/visibility contracts must remain unchanged.
- Drift risks:
  1. duplicated forward/reverse payload builders can diverge on key sets or fallback semantics,
  2. endpoint fallback logic can drift between origin/target paths.

### 2026-06-29 — Phased extraction strategy
1. **Exit payload seam**
   - extract a shared helper to build canonical room-exit payloads.
2. **Endpoint seam**
   - extract a shared endpoint helper for canonical `{hex_id,q,r}` shaping.
3. **Callsite convergence**
   - route forward and reverse exit assembly through shared helpers.
4. **Coverage lock**
   - add focused assertions to lock mirrored reverse-exit endpoint contracts.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve existing connection/exit payload keys and default semantics.
- Preserve endpoint mirroring for reverse exits.

### 2026-06-29 — Test/conformance coverage gaps
- Existing projector tests verified forward exit payloads but did not fully lock reverse endpoint-mirroring details.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `buildProjectedRoomExit(...)`,
  - extracted `buildExitHexPayload(...)`,
  - rewired forward/reverse exit assembly in `attachRoomExits(...)` to consume shared helpers.
- Added targeted unit assertions in `MapVisualStateProjectorTest` for reverse exit endpoint mirroring and passability/connection-id parity.
- Pushed in `dungeoncrawler-content` commit: `6c6b8f1a11`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
