# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/MapGeneratorService.php` (~2415 lines) as a mixed-responsibility navigation/generation monolith spanning:
  1. template/library matching and campaign room reuse,
  2. AI setting generation/normalization contracts,
  3. room/entity persistence and NPC registry hydration.
- Coupling profile:
  - generated NPC/object normalization lived inline in `normalizeSetting(...)`,
  - duplicated inline normalization logic increased drift risk for canonical defaults and stable-ID behavior.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - generated NPC/object IDs must be deterministic and collision-safe per payload,
  - generated contract defaults (`stats`, `equipment`, object flags) must remain canonical across all callsites,
  - normalization must preserve existing generated-setting schemas consumed by downstream room/entity builders.
- Drift risks:
  1. inline normalization blocks can diverge when defaults evolve,
  2. shared ID/dedupe behavior can drift across NPC/object branches without dedicated helpers.

### 2026-06-29 — Phased extraction strategy
1. **Normalization seam**
   - extract dedicated helpers for generated NPC and object contract normalization.
2. **Callsite convergence**
   - route `normalizeSetting(...)` NPC/object branches through shared helpers.
3. **Coverage lock**
   - add focused unit tests that assert canonical defaults and deterministic dedupe behavior for helper paths.
4. **Service thinning continuation**
   - continue decomposing other MapGeneratorService subsystems in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve canonical generated NPC/object payload keys and default values.
- Preserve deterministic stable-ID and dedupe behavior for generated contracts.

### 2026-06-29 — Test/conformance coverage gaps
- Existing determinism tests validated `normalizeSetting(...)` output shapes but did not directly lock newly extracted helper contracts.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `normalizeGeneratedNpcContract(...)`,
  - extracted `normalizeGeneratedObjectContract(...)`,
  - rewired `normalizeSetting(...)` to consume shared helper seams.
- Added targeted unit coverage in `MapGeneratorServiceDeterminismTest`:
  - generated NPC helper canonical defaults + deterministic dedupe IDs,
  - generated object helper canonical defaults + deterministic dedupe IDs.
- Pushed in `dungeoncrawler-content` commit: `22182e7366`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
