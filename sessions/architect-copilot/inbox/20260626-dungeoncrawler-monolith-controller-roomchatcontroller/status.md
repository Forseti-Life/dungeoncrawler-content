# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Controller decomposition audit
- Audited `src/Controller/RoomChatController.php` (~1800+ lines) as a multi-surface monolith combining:
  1. room chat write ingress (`postChatMessage`),
  2. room transcript retrieval and turn-log projections,
  3. stream and non-stream GM response delivery modes,
  4. automation suggestion endpoint (`suggestPlayerAutomationMessage`),
  5. encounter-transition signaling and deferred interjection orchestration.
- Coupling profile:
  - request payload normalization and route-level guard semantics were embedded inline in long route methods,
  - stream/non-stream orchestration shares turn contracts but route methods still mixed context shaping with delivery sequencing,
  - route-level contracts for canonical room-channel enforcement were not factored behind a dedicated boundary.

### 2026-06-29 — Contract map and drift risks
- Core controller contracts identified:
  - room channel write contract (`channel=room` only accepts `type=player` ingress),
  - canonical player-turn option gating (`stream`, `suppress_gm`, `continue_gm` only active for player turns),
  - hard-failure access/ownership enforcement before chat persistence or GM orchestration,
  - stream event envelope stability for client progress and completion rendering.
- Drift risks:
  1. inline payload normalization increases shape drift risk across room-chat paths,
  2. inline guard logic in long methods increases ordering regression risk when stream/non-stream paths evolve,
  3. lack of dedicated request-context boundary complicates incremental controller thinning.

### 2026-06-29 — Phased extraction strategy
1. **Request-context extraction**
   - isolate post-route payload normalization + guard semantics into a single helper boundary.
2. **Write-route orchestration split**
   - separate access+context ingress from stream/non-stream response sequencing.
3. **Stream emitter boundary hardening**
   - keep event contract assembly isolated from route plumbing and request metadata prep.
4. **Suggestion-route parity extraction**
   - align payload/access normalization contracts across suggestion and post-message surfaces.
5. **Controller thinning**
   - retain route facade responsibilities while pushing orchestration details to focused helpers/services.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure access and room-channel enforcement behavior.
- Preserve canonical player-turn gating semantics for `stream`, `suppress_gm`, and `continue_gm`.
- Preserve stream and non-stream response envelope shapes and status semantics.
- Preserve no-fallback error posture for invalid route contract usage.

### 2026-06-29 — Test/conformance coverage gaps
- Existing progress tests cover stream envelopes, turn-log filtering, and stream error debug context.
- Missing prior to this increment:
  1. direct unit assertions for canonical request-context normalization in post payloads,
  2. direct unit assertion that room-channel non-player payloads hard-fail at normalization boundary.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `normalizePostChatPayload(...)` from `postChatMessage`,
  - rewired `postChatMessage` to consume the canonical request-context helper.
- Added unit coverage in `RoomChatControllerProgressTest`:
  - `testNormalizePostChatPayloadBuildsCanonicalFlags`,
  - `testNormalizePostChatPayloadRejectsNonPlayerRoomMessage`.
- Pushed in `dungeoncrawler-content` commit: `0d02e46fdf`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
