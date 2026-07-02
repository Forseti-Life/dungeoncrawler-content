# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/GameCoordinatorService.php` (~1222 lines) as a mixed-responsibility coordinator spanning:
  1. action execution/state transition orchestration,
  2. actor-scoped action availability projection,
  3. payload shaping and controller-facing contract assembly.
- Coupling profile:
  - actor availability code reloaded/rederived phase context in multiple paths,
  - empty availability payload shape was duplicated inline,
  - duplicated context/payload branches raised drift risk between action-list and action-contract endpoints.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - actor availability endpoints must resolve from canonical phase handler context,
  - unavailable context/handler must return deterministic empty availability payload,
  - actor-scoped `available_actions` and `action_contract` must remain aligned to one resolved handler/state snapshot.
- Drift risks:
  1. duplicated context assembly can diverge on future phase/handler rules,
  2. duplicated empty payload literals can drift in shape between endpoints,
  3. duplicated availability wiring increases regression risk during later coordinator decomposition.

### 2026-06-29 — Phased extraction strategy
1. **Availability context seam**
   - extract one helper to resolve dungeon/game-state/handler context for actor-scoped availability reads.
2. **Empty payload seam**
   - extract one helper for deterministic empty action-availability payload shape.
3. **Callsite convergence**
   - route both actor availability endpoints through shared context/payload helpers.
4. **Coverage lock**
   - add focused unit contracts for context resolution and empty payload shape.
5. **Service thinning continuation**
   - continue isolating coordinator read/projection helpers from orchestration paths in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure posture for missing campaign state.
- Preserve actor-scoped `available_actions` and `action_contract` payload contracts.
- Preserve encounter-phase default behavior.
- Preserve no-fallback/no-swallow error handling posture.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests covered actor-id resolution but did not directly lock:
  1. shared action-availability context resolution semantics,
  2. empty actor availability payload shape as a dedicated helper contract.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `resolveActionAvailabilityContext(...)`,
  - extracted `emptyActionAvailabilityPayload(...)`,
  - rewired `getAvailableActionsForActor(...)` and `getActionAvailabilityForActor(...)` to consume shared helpers.
- Added targeted unit coverage in `GameCoordinatorServiceTest`:
  - `testResolveActionAvailabilityContextBuildsEncounterContext`,
  - `testEmptyActionAvailabilityPayloadShape`.
- Pushed in `dungeoncrawler-content` commit: `8aec9e6b26`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
