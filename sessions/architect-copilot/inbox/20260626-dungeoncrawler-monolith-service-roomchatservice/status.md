# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Implementation in progress
- Started contract-focused decomposition pass for `src/Service/RoomChatService.php`.
- Executed a behavior-preserving extraction seam in progress:
  - extracted `compactSessionContextSection(...)`,
  - rewired `buildCompactSessionContext(...)` to route section compaction through the shared helper.
- Added focused unit coverage in `RoomChatServiceNpcResolutionTest` for summary/recent-line truncation in compact session context assembly.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/RoomChatService.php` (~11.8k lines) as a mixed-responsibility monolith spanning:
  1. room chat ingestion + channel/session bridge orchestration,
  2. deterministic GM/intent classification and NPC turn planning,
  3. navigation/combat action extraction + canonical payload handling,
  4. prompt assembly/context-budget shaping + narrative sanitization.
- Coupling profile:
  - compact session-context shaping rules were embedded inline in one long method,
  - section-specific truncation policy and filtering behavior were not isolated as a reusable seam.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - compact context must preserve section semantics while enforcing bounded summary/recent-message lengths,
  - "RECENT CONVERSATION" inclusion must remain explicitly toggleable,
  - prompt context assembly must avoid leaking oversized prior-session payloads.
- Drift risks:
  1. inline section-handling branches increase mutation risk and make section policy changes harder to reason about,
  2. duplicated/embedded truncation logic can diverge from intended compact-context contract under later edits.

### 2026-06-29 — Phased extraction strategy
1. **Context compaction seam**
   - extract one helper for per-section compacting policy.
2. **Callsite convergence**
   - route `buildCompactSessionContext(...)` section handling through shared helper.
3. **Coverage lock**
   - add focused tests for summary truncation and recent-line truncation behavior.
4. **Service thinning continuation**
   - continue extracting deterministic intent and NPC-turn planning seams in later increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow architecture posture.
- Preserve compact context output contract and section-heading behavior.
- Preserve recent-message include/exclude semantics for caller-controlled context budgets.

### 2026-06-29 — Test/conformance coverage gaps
- Existing compact-context test covered dropping recent messages but did not lock truncation behavior for oversized summary/recent lines.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `compactSessionContextSection(...)`,
  - rewired `buildCompactSessionContext(...)` to consume the shared section compaction seam.
- Added dedicated unit coverage in `RoomChatServiceNpcResolutionTest`:
  - summary truncation to max summary budget,
  - recent-line truncation to 180-char cap.
- Targeted test command:
  - `/var/www/html/dungeoncrawler/vendor/bin/phpunit -c phpunit.xml tests/src/Unit/Service/RoomChatServiceNpcResolutionTest.php --filter '/BuildCompactSessionContext/'`
- Pushed in `dungeoncrawler-content` commit: `343ed42140`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
