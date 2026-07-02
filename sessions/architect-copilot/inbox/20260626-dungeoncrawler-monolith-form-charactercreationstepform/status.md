# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Form decomposition audit
- Audited `src/Form/CharacterCreationStepForm.php` (~8k+ lines) as a mixed-responsibility monolith spanning:
  1. route-bound form shell assembly and dynamic step rendering,
  2. dense validation and selection-contract enforcement,
  3. persistence orchestration (`saveCharacter`) with campaign/library lifecycle rules,
  4. structured affiliation creation and normalization flows,
  5. extensive option/detail projection helpers.
- Coupling profile:
  - save-path campaign/instance context resolution was embedded inline in persistence logic,
  - lifecycle context + write orchestration + transactional affiliation handling remained interleaved,
  - monolith shape increases drift risk across campaign binding and canonical source synchronization behavior.

### 2026-06-29 — Contract map and drift risks
- Core form contracts identified:
  - save-path campaign authority contract (stored campaign binding wins over request when record is already bound),
  - deterministic instance identity handoff for membership synchronization,
  - hard-failure transactional integrity for structured affiliation resolution,
  - canonical write lifecycle contract between campaign draft and library source records.
- Drift risks:
  1. inline save-context resolution can diverge from effective campaign resolution contract,
  2. repeated campaign/instance derivation logic risks future branch-order regressions,
  3. large persistence method makes targeted conformance testing harder without explicit extraction seams.

### 2026-06-29 — Phased extraction strategy
1. **Save-context extraction**
   - isolate campaign/instance/record resolution behind a dedicated helper.
2. **Persistence payload extraction**
   - separate canonical row field projection from transactional orchestration.
3. **Lifecycle sync boundary extraction**
   - isolate campaign-to-library source synchronization path.
4. **Validation/persistence seam tightening**
   - enforce explicit contracts at submit/validate -> save handoff.
5. **Form shell thinning**
   - keep form class as step facade while moving orchestration concerns to focused services.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure transactional behavior and no-fallback error posture.
- Preserve stored campaign binding precedence for existing records.
- Preserve deterministic instance ID and membership synchronization sequencing.
- Preserve existing write shape and lifecycle-state transitions.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests cover structured affiliation behavior, save routing, and effective campaign resolution.
- Missing prior to this increment:
  1. direct unit contract for save-context derivation boundary,
  2. direct unit contract for new-record save-context defaulting behavior.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `resolveSaveCharacterContext(...)` from `saveCharacter(...)`,
  - rewired `saveCharacter(...)` to consume the canonical save-context helper.
- Added targeted unit coverage in `CharacterCreationStepFormTest`:
  - `testResolveSaveCharacterContextPrefersStoredCampaignBinding`,
  - `testResolveSaveCharacterContextUsesRequestedCampaignForNewRecord`.
- Pushed in `dungeoncrawler-content` commit: `b8029be5da`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
