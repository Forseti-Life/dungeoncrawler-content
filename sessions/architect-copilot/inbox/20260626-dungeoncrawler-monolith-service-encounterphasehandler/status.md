# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/EncounterPhaseHandler.php` (~9459 lines) as a high-coupling encounter monolith combining:
  1. encounter intent validation/routing,
  2. tactical action execution and state mutation contracts,
  3. room-scene/rest-action bridge rules and transition orchestration.
- Coupling profile:
  - rest-action identifiers were duplicated inline between room-scene validation and rest-action detection paths,
  - duplicated action catalogs raised drift risk for legal-intent vs rest-behavior consistency,
  - monolith scale increases risk of contract divergence under incremental feature edits.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - room-scene validation must permit the canonical rest-action set consistently,
  - rest-action checks must stay synchronized with legal rest-intent identifiers,
  - non-rest actions (e.g. `talk`) must not be classified as rest actions.
- Drift risks:
  1. inline rest-action lists can diverge across call sites,
  2. adding/removing a rest action can miss one branch and create validation mismatches,
  3. duplicated catalogs slow safe decomposition.

### 2026-06-29 — Phased extraction strategy
1. **Rest-action catalog seam**
   - extract one helper that returns canonical encounter rest-action identifiers.
2. **Validation/check convergence**
   - route room-scene legal-action list assembly and rest-action detection through the shared helper.
3. **Action-catalog segmentation**
   - continue isolating action-set definitions from execution logic.
4. **Validation/execution boundary hardening**
   - keep intent legality checks explicit and independent from downstream mutation code.
5. **Service thinning**
   - preserve public behavior while reducing duplicated catalog literals.

### 2026-06-29 — Conformance safeguards
- Preserve room-scene action legality behavior.
- Preserve `isRestAction(...)` semantics for existing rest activities.
- Preserve non-rest action classification behavior.
- Preserve hard-failure/no-swallow posture.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests covered room-scene action legality but did not directly lock shared rest-catalog consistency.
- Missing prior to this increment:
  1. direct unit contract verifying rest-action catalog values,
  2. direct unit contract ensuring rest catalog aligns with legal intents and `isRestAction(...)`.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `getRestActionTypes(...)`,
  - rewired room-scene validation list assembly and `isRestAction(...)` to use the shared catalog helper.
- Added targeted unit coverage in `EncounterPhaseHandlerTest`:
  - `testRestActionCatalogMatchesLegalAndRestChecks`.
- Pushed in `dungeoncrawler-content` commit: `55b88a9720`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
