# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/DowntimePhaseHandler.php` (~2206 lines) as a mixed-responsibility phase monolith combining:
  1. downtime intent routing and action legality contracts,
  2. PF2e rules execution for subsist/earn-income/retrain/affliction/social actions,
  3. state mutation and persistence bridges across canonical/entity projections.
- Coupling profile:
  - `processSubsist(...)` repeated near-identical result-payload construction across all degree branches,
  - repeated payload shaping increased drift risk for baseline subsist response contracts,
  - monolith size increases regression risk when branch-specific keys evolve.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - all subsist outcomes must return a stable baseline result shape (`success`, `degree`, `skill`, `environment`, `dc`, `covered`, `extra_covered`, `penalty_cp`, `days_elapsed`),
  - critical-failure branch must append fatigue/message semantics without altering the baseline shape,
  - degree-specific covered/penalty values must remain unchanged.
- Drift risks:
  1. inline per-branch payload arrays can diverge on shared keys,
  2. future key additions can land in some branches but not others,
  3. duplicated result-shaping blocks slow safe decomposition.

### 2026-06-29 — Phased extraction strategy
1. **Subsist result-shaping seam**
   - extract one helper to build normalized subsist result payloads.
2. **Degree-path convergence**
   - route all `processSubsist(...)` branches through the shared helper.
3. **Action response segmentation**
   - continue isolating response shaping from action rule execution branches.
4. **Phase-state boundary hardening**
   - keep rules computation, mutation, and persistence boundaries explicit.
5. **Service thinning**
   - preserve public intent contracts while reducing duplicated branch scaffolding.

### 2026-06-29 — Conformance safeguards
- Preserve all existing subsist degree outcomes and numeric values.
- Preserve critical-failure fatigue/message semantics.
- Preserve `days_elapsed` reporting and downtime counter flow.
- Preserve hard-failure/no-swallow posture.

### 2026-06-29 — Test/conformance coverage gaps
- Existing subsist tests covered success/crit-success/failure but not explicit critical-failure fatigue contract.
- Missing prior to this increment:
  1. direct assertion that critical-failure path emits fatigue flag,
  2. direct assertion that critical-failure day counter increments.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `buildSubsistResult(...)`,
  - rewired all `processSubsist(...)` degree branches to use the shared helper.
- Added targeted unit coverage in `DowntimePhaseHandlerTest`:
  - `testSubsistCriticalFailureSetsFatigueFlag`.
- Pushed in `dungeoncrawler-content` commit: `c9b32d0606`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
