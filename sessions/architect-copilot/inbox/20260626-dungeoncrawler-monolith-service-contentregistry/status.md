# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/ContentRegistry.php` (~1168 lines) as a mixed-responsibility content monolith combining:
  1. import pipeline orchestration and source filtering,
  2. content normalization/validation for multiple content types,
  3. registry persistence and update contracts.
- Coupling profile:
  - canonical ID-alias normalization for spell/feat payloads was duplicated inline in `normalizeContentData(...)`,
  - repeated per-field alias handling increased drift risk for canonical content ID contracts,
  - monolith size increases risk of inconsistent behavior as content types evolve.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - spell and feat payload ID aliases (`id`, type-specific `_id`, `content_id`) must normalize deterministically to canonical IDs,
  - non-string/empty alias values must remain untouched to preserve existing behavior,
  - canonicalization logic must stay aligned across spell and feat branches.
- Drift risks:
  1. duplicated alias loops can diverge between spell and feat handling,
  2. future alias expansion can miss one branch and introduce inconsistent IDs,
  3. repeated inline logic slows safe decomposition.

### 2026-06-29 — Phased extraction strategy
1. **ID-alias normalization seam**
   - extract one helper that normalizes configured alias fields with a supplied canonicalizer.
2. **Spell/feat convergence**
   - route both spell and feat alias normalization through the shared helper.
3. **Branch-level segmentation**
   - continue isolating per-content-type normalization seams from broader import flow.
4. **Validation/persistence boundaries**
   - keep normalization, validation, and persistence boundaries explicit.
5. **Service thinning**
   - preserve the current facade while incrementally reducing duplicated normalization blocks.

### 2026-06-29 — Conformance safeguards
- Preserve canonical spell/feat ID normalization behavior.
- Preserve pass-through behavior for empty/non-string alias values.
- Preserve existing spell/feat branch-specific normalization semantics beyond alias handling.
- Preserve hard-failure/no-swallow posture.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests validated public normalization outcomes but did not isolate alias-loop extraction as a direct seam.
- Missing prior to this increment:
  1. direct contract test for helper-driven alias normalization across configured fields,
  2. direct contract test for pass-through behavior on empty/non-string alias values.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `normalizeContentIdAliases(...)`,
  - rewired spell and feat branches in `normalizeContentData(...)` to use the shared helper.
- Added targeted unit coverage in `ContentRegistryTest`:
  - `testNormalizeContentIdAliasesNormalizesConfiguredFields`,
  - `testNormalizeContentIdAliasesSkipsEmptyOrNonStringValues`.
- Pushed in `dungeoncrawler-content` commit: `0cbed66b05`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
