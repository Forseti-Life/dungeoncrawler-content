# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/CharacterManager.php` (~17k+ lines) as a high-coupling monolith combining:
  1. canonical payload construction and normalization,
  2. spell/feature compatibility mirror synchronization,
  3. creation/completion generators and static rules catalogs,
  4. persistence and projection helpers.
- Coupling profile:
  - spell selection ID sanitation logic was duplicated across canonical and legacy spell-selection readers,
  - repeated filtering logic raised drift risk between canonical and mirror spell helpers,
  - monolith scale makes small contract changes safer when centralized behind shared micro-helpers.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - canonical spell payload (`spells.cantrips`, `spells.first_level`) is authoritative for selection reads,
  - legacy mirrors (`cantrips`, `spells_first`) remain compatibility-only and must keep identical filtering semantics,
  - spell selection helpers must ignore non-string and empty IDs.
- Drift risks:
  1. duplicated spell-ID filtering can diverge between canonical and legacy helper paths,
  2. future mirror changes could silently alter compatibility behavior without a shared boundary,
  3. monolith breadth amplifies regression risk when helper semantics are copied instead of reused.

### 2026-06-29 — Phased extraction strategy
1. **Spell-ID normalization helper extraction**
   - centralize filtering/sanitization used by canonical and legacy selection helpers.
2. **Selection-helper convergence**
   - route all spell-selection readers through the shared helper.
3. **Mirror synchronization seam hardening**
   - keep canonical-vs-legacy behavior aligned with explicit helper boundaries.
4. **Canonicalization helper segmentation**
   - continue isolating tightly scoped normalization seams from broad orchestration methods.
5. **Service thinning**
   - retain CharacterManager facade while progressively extracting reusable validators/normalizers.

### 2026-06-29 — Conformance safeguards
- Preserve canonical-first spell selection behavior.
- Preserve compatibility mirror filtering behavior for legacy spell arrays.
- Preserve no-fallback-swallow posture on invalid selection payload shapes.
- Preserve existing output shapes for selection helper responses.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests already covered canonical spell helper reads.
- Missing prior to this increment:
  1. direct unit contract for shared spell-ID normalizer filtering empty and non-string values.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `normalizeSelectedSpellIds(...)`,
  - rewired:
    - `getSelectedCantripIds(...)`,
    - `getSelectedFirstLevelSpellIds(...)`,
    - `getLegacySelectedCantripIds(...)`,
    - `getLegacySelectedFirstLevelSpellIds(...)`
    to consume the shared helper.
- Added targeted unit coverage in `CharacterManagerCanonicalizationTest`:
  - `testNormalizeSelectedSpellIdsFiltersNonStringAndEmptyEntries`.
- Pushed in `dungeoncrawler-content` commit: `bca34be210`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
