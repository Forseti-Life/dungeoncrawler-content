# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/CharacterStateService.php` (~2100+ lines) as a mixed-responsibility runtime-state monolith combining:
  1. state load/save orchestration,
  2. consumable effect parsing/application,
  3. feat/effect projection and derived-stat overlays,
  4. runtime persistence and campaign-row synchronization.
- Coupling profile:
  - consumable condition parsing regex blocks were embedded inline in `extractConsumableConditionNames(...)`,
  - freeform-text parsing behavior was not isolated behind an explicit helper seam,
  - monolith size increases regression risk when parsing semantics evolve without focused boundaries.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - consumable text parsing must detect removal/cure condition targets deterministically,
  - extracted condition names must normalize to lowercased canonical condition keys,
  - explicit remove-condition fields and freeform text parsing must merge into one deduplicated output list.
- Drift risks:
  1. inline regex parsing can diverge from explicit-source handling over time,
  2. repeated text parsing patterns are harder to test directly without helper extraction,
  3. monolith coupling raises risk of side-effect regressions when adjusting consumable parsing.

### 2026-06-29 — Phased extraction strategy
1. **Consumable text parser extraction**
   - isolate condition-name parsing from freeform text into a dedicated helper.
2. **Extraction boundary reuse**
   - route `extractConsumableConditionNames(...)` through the shared parser helper.
3. **Consumable parsing segmentation**
   - continue separating scalar/condition/spell-slot parsing seams for isolated contract tests.
4. **State mutation boundary hardening**
   - keep parsing and mutation boundaries explicit before persistence/application steps.
5. **Service thinning**
   - retain state service facade while incrementally extracting parsing/normalization concerns.

### 2026-06-29 — Conformance safeguards
- Preserve existing regex semantics for removal/cure condition extraction.
- Preserve lowercased condition-key normalization.
- Preserve deduplicated merged output from explicit + text parsing sources.
- Preserve hard-failure/no-swallow posture on invalid operations elsewhere in state flow.

### 2026-06-29 — Test/conformance coverage gaps
- Existing unit coverage focused on state projection/save contracts.
- Missing prior to this increment:
  1. direct unit contract for freeform consumable condition-text parsing,
  2. direct unit contract for merged explicit/text condition extraction behavior.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `parseConditionNamesFromConsumableText(...)`,
  - rewired `extractConsumableConditionNames(...)` to consume the shared parser helper.
- Added targeted unit coverage in `CharacterStateServiceTest`:
  - `testParseConditionNamesFromConsumableTextExtractsConditionTargets`,
  - `testExtractConsumableConditionNamesMergesExplicitAndTextSources`.
- Pushed in `dungeoncrawler-content` commit: `7b69accc20`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
