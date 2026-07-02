# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/CampaignInitializationService.php` (~1100+ lines) as a multi-phase bootstrap monolith combining:
  1. campaign record creation and starter dungeon seeding,
  2. tavern room entity/runtime initialization,
  3. starter quest/storyline wiring,
  4. chat session bootstrap and initial room transcript seeding.
- Coupling profile:
  - starter room narration assembly and transcript prefixing logic existed in multiple call paths (`bootstrapChatSessions` and `seedStarterRoomChatHistory`),
  - duplicated room-intro message composition increased drift risk between room session seed and runtime room-chat seed,
  - service contains many concerns; helper seam extraction is required for safe iterative thinning.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - deterministic starter room intro narrative body,
  - canonical encounter transcript prefix formatting for initial seeded narration,
  - parity between chat-session bootstrap seed and runtime room chat seed,
  - hard-failure behavior for incomplete starter-room asset contracts.
- Drift risks:
  1. duplicate intro/prefix logic can produce mismatched initial messages across chat surfaces,
  2. future edits in one seed path may silently diverge the other,
  3. monolith size raises regression risk without explicit helper boundaries and direct tests.

### 2026-06-29 — Phased extraction strategy
1. **Starter narration helper extraction**
   - isolate intro body + prefix composition into a shared helper.
2. **Seed-path reuse**
   - route both chat bootstrap and runtime room chat seeding through that helper.
3. **Chat bootstrap segmentation**
   - split root/system/room seeding phases behind explicit helper seams.
4. **Dungeon/room bootstrap segmentation**
   - isolate starter dungeon payload projection from persistence orchestration.
5. **Service thinning**
   - retain orchestration facade while migrating concern-specific blocks to focused collaborators.

### 2026-06-29 — Conformance safeguards
- Preserve canonical transcript prefix format (`Round 0: Turn 1: Actor Narrator:`) for starter seed narration.
- Preserve starter intro fallback text behavior when room description is empty.
- Preserve no-fallback-swallow posture and existing hard-failure guard behavior.
- Preserve starter chat feed/runtime chat parity for initial narrative seed content.

### 2026-06-29 — Test/conformance coverage gaps
- Existing unit coverage focused mainly on starter room seed metadata loading.
- Missing prior to this increment:
  1. direct unit contract for canonical prefixed starter seed narration assembly,
  2. direct unit contract for fallback starter intro text when description is absent.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `buildStarterRoomSeedNarration(...)`,
  - rewired `bootstrapChatSessions(...)` and `seedStarterRoomChatHistory(...)` to reuse the shared helper.
- Added targeted unit coverage in `CampaignInitializationServiceTest`:
  - `testBuildStarterRoomSeedNarrationPrefixesCanonicalEncounterEnvelope`,
  - `testBuildStarterRoomSeedNarrationFallsBackToRoomArrivalTextWhenDescriptionMissing`.
- Pushed in `dungeoncrawler-content` commit: `8746919562`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
