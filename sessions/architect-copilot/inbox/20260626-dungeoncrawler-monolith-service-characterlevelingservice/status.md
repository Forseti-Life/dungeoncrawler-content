# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/CharacterLevelingService.php` (~2100+ lines) as a dense leveling orchestration monolith combining:
  1. draft/apply leveling lifecycle transitions,
  2. pending-choice intake and validation handlers,
  3. feat/spell/skill eligibility and prerequisite enforcement,
  4. runtime/canonical row synchronization and rollback paths.
- Coupling profile:
  - `submitSkillIncrease(...)` embedded rank progression + level-gate validation inline with submission orchestration,
  - skill-choice validation logic was not factored into a dedicated helper boundary,
  - mixed validation + persistence responsibilities increase regression risk when expanding skill progression contracts.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - rank progression must move exactly one step at a time (`untrained -> ... -> legendary`),
  - level gates must hard-fail (`master` before 7, `legendary` before 15),
  - pending-choice payload shape must remain deterministic for advancement plan persistence,
  - no silent fallback on invalid skill input or rank transition.
- Drift risks:
  1. inline skill validation can drift across handlers as progression contracts evolve,
  2. gate-check updates are harder to pin without a dedicated validation seam,
  3. monolith scale raises chance of coupling regressions when modifying submission methods.

### 2026-06-29 — Phased extraction strategy
1. **Skill-choice validation extraction**
   - isolate skill rank progression + level-gate validation into a dedicated helper.
2. **Submission handler seam tightening**
   - keep `submitSkillIncrease(...)` as orchestration-only path consuming canonical choice payload.
3. **Choice submission convergence**
   - incrementally align ability/skill/feat submission boundaries around dedicated validators.
4. **Plan/persistence boundary extraction**
   - continue separating validation from advancement-plan persistence/finalization.
5. **Service thinning**
   - retain leveling service facade while extracting concern-specific validators/helpers.

### 2026-06-29 — Conformance safeguards
- Preserve exact rank progression ordering and hard-failure gate behavior.
- Preserve pending-choice payload shape (`skill`, `previousRank`, `newRank`).
- Preserve no-fallback-swallow posture for invalid skill/rank selections.
- Preserve advancement plan update/finalize sequencing in `submitSkillIncrease(...)`.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests covered high-level trigger/apply flows and several catalog validations.
- Missing prior to this increment:
  1. direct unit contract for skill-choice payload derivation,
  2. direct unit contract for master-rank gate rejection before level 7.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `resolveSkillIncreaseChoice(...)`,
  - rewired `submitSkillIncrease(...)` to consume the shared validator helper.
- Added targeted unit coverage in `CharacterLevelingServiceTest`:
  - `testResolveSkillIncreaseChoiceBuildsChoicePayload`,
  - `testResolveSkillIncreaseChoiceRejectsMasterBeforeLevelSeven`.
- Pushed in `dungeoncrawler-content` commit: `2f2090e21a`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
