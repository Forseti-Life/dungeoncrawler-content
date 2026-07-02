# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Controller decomposition audit
- Audited `src/Controller/CharacterViewController.php` (1867 lines) as a mixed-surface monolith combining:
  1. primary character sheet render pipeline (`viewCharacter`, ~430 lines),
  2. follower actor sheet render path (`viewFollowerActor`, ~275 lines),
  3. dense projection/normalization helpers (abilities/skills/attacks/effects/personality/metadata),
  4. lifecycle mutation endpoints (`archiveCharacter`, `unarchiveCharacter`),
  5. embedded sheet transport (`viewCharacterEmbed`).
- Coupling profile:
  - request/access gating, canonicalization, state projection, and render-build assembly are all mixed in-controller,
  - repeated bucket-normalization logic for state payloads and contextual actor data,
  - mixed responsibilities across pure projection helpers and route-level mutation/response orchestration.

### 2026-06-29 — Contract map and drift risks
- Core controller contracts identified:
  - ownership/access contract for character sheet visibility,
  - canonical character/state projection contract before sheet rendering,
  - follower parity contract (same actor-sheet dimensions and psychology surfaces),
  - archive/unarchive mutation contract with route-level redirect/message semantics.
- Drift risks:
  1. Request/access context and render-state context normalization living inline in long methods raises ordering regression risk.
  2. State bucket extraction repeated in-route encourages shape drift when state schema evolves.
  3. Render assembly and mutation endpoints in one class increase chance of cross-surface coupling changes.

### 2026-06-29 — Phased extraction strategy
1. **Request/access context extraction**
   - Isolate route-level request + access + record resolution helpers for character/follower views.
2. **State projection extraction**
   - Isolate canonical state bucket normalization and view-model assembly helpers.
3. **Render model assembler extraction**
   - Move sheet payload construction into dedicated assembler service(s), keeping controller as HTTP facade.
4. **Follower view alignment extraction**
   - Normalize follower and primary character sheet view-model contracts through shared projection boundaries.
5. **Lifecycle endpoint isolation**
   - Move archive/unarchive orchestration into narrow application services while preserving redirect/message contracts.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure behavior for not-found and unauthorized access.
- Preserve canonical state/character projection ordering before sheet build.
- Preserve follower actor-sheet parity surfaces and psychology dimension projections.
- Preserve existing route response shape contracts for view, embed, archive, and unarchive paths.

### 2026-06-29 — Test/conformance coverage gaps
- Existing tests:
  - `CharacterViewControllerEffectSummaryTest` covers effect-summary projection contracts.
  - `CharacterViewControllerTest` covers route-level accessibility/shape at a broad level.
- Missing before larger structural movement:
  1. character/follower request-access context behavior matrix (owner/admin/campaign-owner),
  2. state bucket normalization contract snapshots,
  3. archive/unarchive side-effect and redirect contract snapshots.

### 2026-06-29 — Implementation increment I1 (executed)
- Executed real refactor increment in `dungeoncrawler-content`:
  - extracted `resolveViewCharacterRequestContext(...)` from `viewCharacter` for request/access/record resolution,
  - extracted `splitCharacterStateForSheet(...)` for deterministic state bucket normalization,
  - updated `viewCharacter` to consume extracted helpers without changing response contracts.
- Added unit coverage in `CharacterViewControllerStateSliceTest` for state-slice normalization contract.
- Pushed in `dungeoncrawler-content` commit: `9690cd8f01`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
