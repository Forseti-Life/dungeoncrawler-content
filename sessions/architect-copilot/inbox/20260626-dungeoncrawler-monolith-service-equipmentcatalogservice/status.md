# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/EquipmentCatalogService.php` (~1367 lines) as a data-heavy catalog monolith with filtering concerns concentrated in terminal query methods:
  1. type/source filter projection over canonical equipment catalog constants,
  2. criteria-composition filtering for combined type + source queries,
  3. item-level lookup and armor-stat projection accessors.
- Coupling profile:
  - source-book matching logic was duplicated inline across `getBySourceBook(...)` and `getByCriteria(...)`,
  - duplicated source matching raised drift risk for legacy default-book handling (`source_book` missing => `crb`),
  - repeated filter expressions increased maintenance risk when catalog-source rules evolve.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - items missing `source_book` must be treated as `crb`,
  - source filtering behavior must remain consistent across direct source queries and combined criteria queries,
  - `all` source pseudo-value must continue bypassing source filtering.
- Drift risks:
  1. duplicated source checks can diverge between query methods,
  2. legacy default-source semantics can be accidentally changed in one path only,
  3. inline closures slow safe decomposition.

### 2026-06-29 — Phased extraction strategy
1. **Source-match seam**
   - extract one helper for item-to-source matching with legacy default-book fallback.
2. **Filter-path convergence**
   - route both source-only and criteria source filtering through the shared helper.
3. **Catalog query segmentation**
   - continue isolating filter predicates from public query wrappers.
4. **Accessor/query boundary hardening**
   - keep lookup accessors separate from filter-predicate logic.
5. **Service thinning**
   - preserve static catalog authority while reducing duplicated predicate literals.

### 2026-06-29 — Conformance safeguards
- Preserve missing-source fallback to `crb`.
- Preserve `all` source behavior (no source filter).
- Preserve output ordering/shape semantics from existing query methods.
- Preserve hard-failure/no-swallow posture.

### 2026-06-29 — Test/conformance coverage gaps
- No dedicated unit suite previously locked EquipmentCatalogService source-filter contracts.
- Missing prior to this increment:
  1. direct helper contract for default `crb` matching behavior,
  2. source-filter parity test across `getBySourceBook(...)` and `getByCriteria(...)`,
  3. criteria behavior test for `source_book=all` with type filters.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `itemMatchesSourceBook(...)`,
  - rewired source filtering in `getBySourceBook(...)` and `getByCriteria(...)` to use the shared helper.
- Added targeted unit coverage in `EquipmentCatalogServiceTest`:
  - `testItemMatchesSourceBookDefaultsMissingSourceToCrb`,
  - `testSourceBookFilteringMatchesAcrossPublicMethods`,
  - `testCriteriaAllSourceLeavesTypeFilteringIntact`.
- Pushed in `dungeoncrawler-content` commit: `e1f71672f8`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
