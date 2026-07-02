# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/NpcPsychologyService.php` (~1290 lines) as a mixed-responsibility psychology monolith spanning:
  1. psychology profile generation and persistence orchestration,
  2. role-scoped motivation/fear text synthesis,
  3. JSON contract hydration, normalization, and update-field handling.
- Coupling profile:
  - role-pool normalization/fallback logic was duplicated inline across `generateMotivations(...)` and `generateFears(...)`,
  - duplicated fallback handling increased drift risk for neutral-role default behavior.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - role lookups should normalize key casing/whitespace and fall back to neutral pools consistently,
  - motivation output remains canonical semicolon-delimited text from role pools,
  - fear output remains a single canonical string from role pools.
- Drift risks:
  1. duplicated role-fallback logic can diverge between motivation and fear generation paths,
  2. unnormalized role input can silently alter generated psychology semantics.

### 2026-06-29 — Phased extraction strategy
1. **Role-pool seam**
   - extract a shared role-pool resolver with normalized role-key + neutral fallback handling.
2. **Callsite convergence**
   - route motivation and fear generation branches through the shared resolver.
3. **Coverage lock**
   - add dedicated service unit coverage for normalized role lookup and unknown-role fallback behavior.
4. **Service thinning continuation**
   - continue extracting additional persistence/normalization seams in later increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve canonical role pool vocabulary and output formats for motivations and fears.
- Preserve current randomization behavior while centralizing pool selection semantics.

### 2026-06-29 — Test/conformance coverage gaps
- No dedicated `NpcPsychologyService` unit suite existed to directly lock role-pool fallback and normalization contracts.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `resolveRolePsychologyPool(...)`,
  - rewired `generateMotivations(...)` and `generateFears(...)` to consume shared role-pool normalization/fallback logic.
- Added dedicated unit coverage in `NpcPsychologyServiceTest`:
  - normalized role-key lookup + neutral fallback behavior,
  - unknown-role fallback contract for motivation and fear generation paths.
- Pushed in `dungeoncrawler-content` commit: `ff24a2bbb3`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
