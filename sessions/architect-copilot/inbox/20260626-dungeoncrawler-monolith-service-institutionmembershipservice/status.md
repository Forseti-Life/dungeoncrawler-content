# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/InstitutionMembershipService.php` (~1299 lines) as a mixed-responsibility institution graph service spanning:
  1. actor institution input derivation,
  2. membership/sentiment edge synchronization and mutation,
  3. subject/edge normalization and hydration helpers.
- Coupling profile:
  - character and NPC input builders duplicated seeded ancestry/profession payload assembly,
  - repeated seed payload construction increased metadata drift risk across actor types.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - seeded institution inputs must keep deterministic metadata keys (`seed_source`, `source_field`),
  - ancestry and profession seed domains/display names must remain canonicalized consistently across character and NPC paths,
  - NPC profession source-field precedence must stay `occupation` before `class`.
- Drift risks:
  1. duplicated seed payload literals can diverge in metadata shape,
  2. actor-type drift can desynchronize ancestry/profession seed semantics.

### 2026-06-29 — Phased extraction strategy
1. **Seed input builder seam**
   - extract shared helper for canonical seeded institution payload assembly.
2. **Ancestry seam**
   - extract shared ancestry-input helper consumed by character and NPC builders.
3. **Builder convergence**
   - route character/NPC profession seed creation through shared helper.
4. **Coverage lock**
   - add focused unit tests for canonical seed metadata and NPC source-field precedence.
5. **Service thinning continuation**
   - continue decomposing sync/mutation pipelines in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve existing institution domain/display_name normalization behavior.
- Preserve seed metadata contract and NPC occupation/class precedence.
- Preserve structured affiliation input merging behavior.

### 2026-06-29 — Test/conformance coverage gaps
- Existing sync tests validated relationship outputs but did not directly lock:
  1. seed metadata payload shape from input builders,
  2. NPC profession source-field precedence between `occupation` and `class`.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `buildAncestryInstitutionInput(...)`,
  - extracted `buildSeededInstitutionInput(...)`,
  - rewired `buildCharacterInstitutionInputs(...)` and `buildNpcInstitutionInputs(...)` to consume shared builders.
- Expanded targeted unit coverage in `InstitutionMembershipServiceTest`:
  - `testBuildCharacterInstitutionInputsReturnsCanonicalSeedMetadata`,
  - `testBuildNpcInstitutionInputsPrefersOccupationSourceField`.
- Pushed in `dungeoncrawler-content` commit: `bc38faa5c7`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
