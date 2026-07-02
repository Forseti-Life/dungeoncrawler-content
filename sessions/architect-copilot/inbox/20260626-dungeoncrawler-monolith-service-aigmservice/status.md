# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/AiGmService.php` (~1100+ lines) as a multi-surface orchestration service combining:
  1. narration trigger entry points,
  2. AI invocation/session threading logic,
  3. fallback narrative templates,
  4. context extraction and helper projections.
- Coupling profile:
  - session-context prompt prefixing logic was duplicated across NPC attitude and generic narration paths,
  - duplicated prompt/session envelope assembly increases risk of drift in future prompt contract updates,
  - helper seams existed but lacked a single canonical boundary for session-context prefixing.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - campaign-scoped session isolation in prompt threading,
  - canonical prompt envelope delimiter (`--- CURRENT REQUEST`) used for continuity,
  - hard-failure rate-limit enforcement before AI invocation,
  - fallback continuity when AI is unavailable.
- Drift risks:
  1. duplicated session-prefix logic can diverge on delimiter format and campaign guard semantics,
  2. trigger-specific prompt paths may evolve with inconsistent session-context behavior,
  3. missing direct unit coverage on prompt-prefix boundary leaves regression risk unpinned.

### 2026-06-29 — Phased extraction strategy
1. **Prompt/session envelope extraction**
   - isolate session-context prompt prefix assembly into a single helper.
2. **Narration-path reuse**
   - route NPC and generic narration paths through the shared helper.
3. **Invocation helper tightening**
   - keep rate-limit and AI invocation boundaries explicit and stable.
4. **Fallback routing stabilization**
   - preserve deterministic trigger fallback contracts while thinning orchestration paths.
5. **Service thinning**
   - retain AiGmService as facade while continuing helper-level extraction by concern.

### 2026-06-29 — Conformance safeguards
- Preserve campaign-scoped session isolation and delimiter format.
- Preserve AI unavailable fallback behavior and no-fallback-swallow posture for failures.
- Preserve rate-limit enforcement sequence.
- Preserve existing narration trigger contracts and response shapes.

### 2026-06-29 — Test/conformance coverage gaps
- Existing test suite uses `AiGmService` mostly as a mocked dependency in downstream handlers.
- Missing prior to this increment:
  1. direct unit contract for prompt prefixing when session context exists,
  2. direct unit contract for non-campaign prompt behavior (no session prefix lookup).

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `buildPromptWithSessionContext(...)`,
  - rewired `narrateNpcAttitudeShift(...)` and `invokeNarration(...)` to consume the shared helper.
- Added targeted unit coverage in new `AiGmServiceTest`:
  - `testBuildPromptWithSessionContextPrefixesCurrentRequestWhenSessionExists`,
  - `testBuildPromptWithSessionContextReturnsOriginalPromptForNonCampaignScope`.
- Pushed in `dungeoncrawler-content` commit: `0df24ba610`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
