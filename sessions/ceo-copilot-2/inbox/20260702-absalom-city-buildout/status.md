# Status

- status: active
- created_at: 2026-07-02T18:33:12.657+00:00
- current_phase: phase-4-room-validation-hardening

## Notes

### 2026-07-02 - work item created
- Created CEO inbox tracker for Absalom city buildout scope.
- Baseline counts confirmed from provided location taxonomy:
  - planned landmarks: 34
  - detailed landmarks: 30
  - missing detailed logs: 4
  - provided actor allocation total: 642
- Missing-detail landmarks are currently all in Infrastructure & Production:
  - The Graveyard / Mausoleum Complex
  - The Public Bathhouse
  - The Aqueducts & Pumping Stations
  - The Lower Ward / Slums

### 2026-07-02 - phase 1 completed (DB surface audit)
- Canonical content surface inventory confirmed:
  - `dungeoncrawler_content_registry` currently has only **3** `location` records (`ltba-grandmas-house`, `nantambu`, `next_story_location`).
  - None of the 34 planned Absalom landmarks exist as canonical `location` entries yet.
- Existing Absalom canonical footprint is minimal:
  - `dungeoncrawler_content_dungeons`: `tpl_dungeon_tavern_basement` (name `Absalom`) with one room.
  - `dungeoncrawler_content_rooms`: `tpl_room_tavern_entrance` only.
  - `dungeoncrawler_content_characters` + registry NPCs currently include Eldric/tavern-keeper references only.
- Runtime copies exist in campaign scope (`dc_campaign_dungeons` rows named `Absalom`) but do not replace canonical library buildout requirements.
- DB gap breakdown captured in:
  - `02-db-fleshing-breakdown.md`

### 2026-07-02 - infrastructure detail completion received
- Added the four missing Infrastructure & Production landmark details:
  - The Graveyard / Mausoleum Complex
  - The Public Bathhouse
  - The Aqueducts & Pumping Stations
  - The Lower Ward / Slums
- Baseline now fully detailed:
  - planned landmarks: 34
  - detailed landmarks: 34
  - missing detailed logs: 0
  - provided actor allocation total: 729
- Locked execution constraint for DB implementation:
  - create NPC entities only for explicitly named NPCs.
  - include non-named actors in location description text only (no separate actor entities/fields).
- Updated artifacts:
  - `01-location-allocation-baseline.md`
  - `02-db-fleshing-breakdown.md`
  - `03-infrastructure-landmark-details.md`

### 2026-07-02 - canonical structure payload completed
- Built full ingest-ready canonical structure payload for all 34 landmarks:
  - `04-absalom-canonical-structures.json`
- Payload includes:
  - deterministic `location_id` values for every landmark,
  - location-level `actor_count` and description text with non-named actor representation,
  - named NPC entity references per location,
  - visible inventory lists per location.
- Count checks on payload:
  - locations: 34
  - actor_count total: 729
  - named NPC references: 102

### 2026-07-02 - room explorer validator wiring completed
- Pivoted focus from Absalom ingest to room/location validation with database-authoritative contracts.
- Implemented canonical room validator in `StateValidationService`:
  - validates `dungeoncrawler_content_rooms` availability and non-empty dataset,
  - enforces required `room_id`, `name`, `layout_data`, `contents_data`,
  - enforces `layout_data.hexes` contains at least one hex,
  - emits aggregate and per-room diagnostics for explorer surfaces.
- Replaced Room Explorer stub path with live room validation diagnostics:
  - `/analysis/explorer/rooms` now reports PASS/FAIL summary, per-room table, and detailed error list.
- Added/updated unit coverage:
  - `StateValidationServiceTest` room validator tests (pass, missing-table fail, missing-hexes fail),
  - `AnalysisExplorerPageControllerTest` room report loader tests.

### 2026-07-02 - room explorer parity pass (item explorer basis)
- Updated `/analysis/explorer/rooms` to match item/actor explorer interaction model:
  - filter controls (search + status + reset),
  - selectable table rows with room profile columns,
  - selected room overview card with full-field flattening table,
  - scoped summary/error cards using filtered report projection.
- Canonical room records from `validateCanonicalRoomLibraryContracts()` now drive the full explorer surface.
- Added controller unit coverage for room filter state resolution and filtered summary recomputation.

### 2026-07-02 - room validator hardening ruleset implemented
- Expanded `validateCanonicalRoomLibraryContracts()` to enforce hardened contracts:
  - blocked prompt-derived room/source IDs (`i-want-*`, `hello-*`),
  - source room linkage to existing canonical room IDs,
  - non-empty, deduplicated environment tags,
  - strict layout hex schema and object contract typing,
  - required `entry_points`/`exit_points` with coordinate mapping into `hexes`,
  - traversable entry→exit path enforcement,
  - required contents buckets with typed entry validation,
  - canonical content registry resolution for structured `content_id` references.
- Added focused unit coverage for new hardening paths:
  - blocked prompt-derived room IDs,
  - disconnected entry/exit path rejection,
  - updated canonical pass fixture for stricter contract requirements.
- Current live validation snapshot after hardening:
  - `total=20`, `valid=0`, `invalid=20` (all failures now explicit contract-level defects surfaced by stricter rules).

### 2026-07-02 - room validator review/refactor pass
- Reviewed hardened validator behavior against live canonical room rows and identified two refinement points:
  - mixed-object hex traversability (e.g., wall + passable door on the same coordinate),
  - overly strict registry coupling for non-reference room-local content buckets.
- Refactored validator internals for maintainability:
  - introduced constants for required buckets, registry-reference buckets, allowed object categories, blocked-id pattern, and hex neighbor offsets.
- Refined validation behavior (without fallback semantics):
  - hex is now considered blocked only when blocker objects exist without any explicitly passable/non-blocking object on that coordinate,
  - registry resolution remains strict for `npcs/items/entities`, while room-local `content_id` values in other buckets are pattern-validated but not required to resolve in registry.
- Added focused unit tests for both refinements:
  - room-local obstacle `content_id` acceptance,
  - boundary entry hex with wall+door mix remaining traversable.

### 2026-07-02 - explicit room-to-room exit linkage enforcement
- Added hard validation rule: every canonical room must define at least one explicit link to another room in `layout_data.exits[*].target_room_id`.
- New linkage contract checks enforce:
  - `layout_data.exits` exists and is non-empty,
  - each `target_room_id` is canonical-pattern, non-blocked, non-self, and resolves to an existing canonical room.
- Added focused unit coverage for missing-link rejection and updated passing fixtures to include two-room cross-linking.
- Normalized canonical room DB records to include explicit cross-room links for all 20 current rooms.
- Live post-normalization validator snapshot:
  - `total=20`, `valid=20`, `invalid=0`.
