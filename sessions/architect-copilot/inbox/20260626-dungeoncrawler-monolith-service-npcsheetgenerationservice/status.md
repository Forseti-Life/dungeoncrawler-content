# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/NpcSheetGenerationService.php` (~1140 lines) as a mixed-responsibility NPC generation monolith spanning:
  1. queue + AI/fallback generation orchestration,
  2. contract normalization/legacy field projection,
  3. campaign/library persistence and psychology payload synthesis.
- Coupling profile:
  - legacy psychology-string precedence logic (`sheet -> seed -> derived`) was duplicated inline across motivations/fears/bonds in `normalizeGeneratedSheet(...)`,
  - inline duplication increased drift risk for legacy contract parity across fields.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - legacy strings must consistently resolve by precedence: explicit sheet value, then seed value, then derived psychology fallback,
  - derived motivations/fears/bonds must remain stable and deterministic from structured psychology payloads,
  - normalized sheet output must preserve canonical keys consumed by campaign persistence.
- Drift risks:
  1. duplicated inline precedence blocks can diverge between motivations/fears/bonds,
  2. future edits to one legacy field path can accidentally desynchronize the others.

### 2026-06-29 — Phased extraction strategy
1. **Legacy-precedence seam**
   - extract shared resolver helper for legacy psychology fields.
2. **Callsite convergence**
   - route motivations/fears/bonds normalization through shared helper.
3. **Coverage lock**
   - add focused assertions for sheet/seed/derived precedence behavior.
4. **Service thinning continuation**
   - continue decomposition across AI prompt building and persistence seams in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve motivations/fears/bonds precedence semantics exactly.
- Preserve structured psychology-derived fallback behavior.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests covered derived legacy strings from normalized sheets, but did not directly lock shared precedence resolution semantics as a dedicated seam.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `resolveLegacyPsychologyField(...)`,
  - rewired `normalizeGeneratedSheet(...)` motivations/fears/bonds fields to consume shared resolver helper.
- Added targeted unit coverage in `NpcSheetGenerationServiceTest` for:
  - sheet override precedence,
  - seed fallback precedence,
  - derived fallback usage when sheet/seed values are absent.
- Pushed in `dungeoncrawler-content` commit: `3d9ea5617c`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
