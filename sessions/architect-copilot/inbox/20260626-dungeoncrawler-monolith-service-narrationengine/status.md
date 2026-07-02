# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/NarrationEngine.php` (~1033 lines) as a mixed-responsibility narration monolith spanning:
  1. room-event queueing and message projection,
  2. role/scope normalization for speaker ownership,
  3. immediate/batch narration generation and perception filtering.
- Coupling profile:
  - speech-like detection logic for narrator reassignment lived inline in `normalizeEventRoleScope(...)`,
  - inline predicate construction increased drift risk for role normalization behavior over future edits.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - speech-like narrator content must remap to GM scope (`Game Master` / `gm`),
  - narrator-owned procedural event types must remain narrator-scoped,
  - role normalization must preserve existing system/narrator routing precedence.
- Drift risks:
  1. inline dialogue-signal logic can diverge from role assignment branches,
  2. narration-role ownership can regress without dedicated contract coverage.

### 2026-06-29 — Phased extraction strategy
1. **Dialogue-signal seam**
   - extract a dedicated helper for speech-like content detection.
2. **Callsite convergence**
   - route narrator speech remap logic through shared helper.
3. **Coverage lock**
   - add focused tests for narrator speech-to-GM remap and narrator-event retention.
4. **Service thinning continuation**
   - continue decomposing queueing/perception/generation seams in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve existing role ownership precedence in `normalizeEventRoleScope(...)`.
- Preserve narrator event routing and narrator speech remap behavior.

### 2026-06-29 — Test/conformance coverage gaps
- Existing narration tests only covered constructor/service wiring and did not lock role/scope normalization behavior.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `eventContentLooksLikeDialogue(...)`,
  - rewired narrator speech detection in `normalizeEventRoleScope(...)` to use shared helper.
- Added targeted unit coverage in `NarrationEngineWiringTest` for:
  - narrator speech-like content remapping to GM scope,
  - narrator-owned event type retention as narrator scope.
- Pushed in `dungeoncrawler-content` commit: `aef344b08d`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
