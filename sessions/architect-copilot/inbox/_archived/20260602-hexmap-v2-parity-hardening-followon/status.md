# Status

- status: in_progress
- created_at: 2026-06-02T11:56:00+00:00
- current_phase: action-bar architecture continuation (next target selection)

## Notes

This is the active follow-on item after the Phase 0–10 HexMap V2 cutover item was closed to prevent SLA drift.

### 2026-06-07 — Action bar consumables wired to authoritative encounter contract
- Consolidated action-bar direct execution ownership into `EncounterSystem` for `consumable` actions (matching the already-migrated feat pattern).
- Added canonical consumable encounter routing to coordinator intent `consume_item` with state-resync handling (`_sendCoordinatorActionWithResync`), plus non-encounter fallback parity to `/api/character/{id}/inventory`.
- Removed duplicate `user:action-selected` consumable/feat subscription ownership from `PlayerAutomation` to prevent split routing paths.
- Added focused regression `tests/action_rail_consumable_binding_test.js` locking emit → handler → backend-intent contract.
- Bumped V2 asset version tokens and Drupal library version; rebuilt caches.
- Landed and pushed in `dungeoncrawler-content` commit: `0247adc`.

### 2026-06-07 — Encounter availability gating aligned on action rail
- Added authoritative encounter-mode gating for tab actions in `ActionRailPanel` using server contract IDs:
  - spells → `cast_spell`
  - consumables → `consume_item`
  - skills → `skill`
  - feats → `feat`
- Tabs now keep entries visible but disable execution when the current turn contract marks that action unavailable.
- Added focused regression `tests/action_rail_availability_gating_test.js` to lock the gating contract.
- Bumped V2 asset/library version tokens and rebuilt Drupal caches.
- Landed and pushed in `dungeoncrawler-content` commit: `acfef5a`.

### 2026-06-07 — Code review + refactor: direct-action ownership cleanup
- Refactored `EncounterSystem` action-selected routing into a canonical handler map (`ACTION_SELECTION_HANDLERS`) with explicit rest-action key set, reducing switch drift risk.
- Hardened `executeDirectSkill` with strict guards for active `characterId` and canonical non-empty `skillName` before dispatch.
- Removed duplicated feat/consumable direct execution methods from `PlayerAutomation`; direct action execution ownership remains centralized in `EncounterSystem`.
- Updated focused tests to lock the refactored dispatch contract:
  - `tests/action_rail_feat_binding_test.js`
  - `tests/action_rail_consumable_binding_test.js`
  - `tests/action_rail_execution_ownership_test.js` (new)
- Bumped V2 asset/library version tokens and rebuilt Drupal caches.
- Landed and pushed in `dungeoncrawler-content` commit: `775f55a`.

### 2026-06-07 — Architecture review: Action Bar + subcomponents
- Reviewed contracts end-to-end across:
  - template tab/category surface (`templates/hexmap-v2.html.twig`)
  - panel rendering + routing (`js/v2/panels/ActionRailPanel.js`)
  - direct-action execution ownership (`js/v2/systems/EncounterSystem.js`)
  - navigate execution (`js/v2/systems/NavigationSystem.js`)
  - shell orchestration and shim layer (`js/v2/GameShell.js`)
- Key architecture findings:
  1. **Contract duplication risk** across category IDs, execute keys, bus routes, and server action IDs (currently mapped in multiple files).
  2. **Boundary leak in panel layer**: `ActionRailPanel` mixes UI concerns with domain/data-fetch logic (visited-locations fetch + action availability mapping + execution routing decisions).
  3. **Duplicate navigate data loader logic** exists in both `ActionRailPanel` and `NavigationSystem` (`ensureNavigateLocationGroups`), creating divergence risk.
  4. **Lifecycle gap**: panel DOM listeners are bound via dataset sentinels, with no explicit teardown path, which is brittle under re-init/re-attach flows.
  5. **Test architecture drift**: legacy action-rail tests assert APIs that no longer exist; focused contract tests are current, but broad legacy tests are stale.
- Next hardening direction: create a single shared Action Rail contract module (category + execute + route + server-action mapping), move navigate/availability data prep behind service-layer adapters, and add lifecycle-safe DOM listener management in panel init/destroy.

### 2026-06-07 — Phase 1 architecture hardening landed (shared Action Rail contracts)
- Added `js/v2/contracts/action-rail-contract.js` as the canonical shared contract layer for:
  - category normalization
  - direct-route mapping
  - selectable-action whitelist
  - execute-key → server-action mapping
  - action-selected execution handler map
  - rest-activity key detection
- Refactored `ActionRailPanel` to consume shared contract helpers for:
  - category resolution
  - direct-route + selectable-action dispatch
  - execute-key-based server availability lookups
  - unsupported-action guard logging
- Refactored `EncounterSystem` to consume shared handler/rest-key contracts instead of local duplicated constants.
- Added/updated focused regressions:
  - `tests/action_rail_contract_routing_test.js` (new)
  - `tests/action_rail_execution_ownership_test.js`
  - `tests/action_rail_feat_binding_test.js`
  - `tests/action_rail_consumable_binding_test.js`
  - `tests/action_rail_availability_gating_test.js`
- Resolved concurrent upstream token changes while preserving latest chat wait-indicator version and published unified V2 version token.
- Landed and pushed in `dungeoncrawler-content` commit: `3d8b2f9`.

### 2026-06-07 — Phase 2 architecture hardening landed (navigate service boundary)
- Added shared service adapter: `js/v2/services/navigate-location-service.js` with canonical visited-location fetch + shape contract.
- Refactored both `ActionRailPanel` and `NavigationSystem` to consume `fetchVisitedNavigateLocationGroups(campaignId)` instead of duplicating `/api/campaign/{id}/visited-locations` fetch/parsing logic.
- Added focused contract regression `tests/action_rail_navigate_service_contract_test.js` to lock shared-service ownership and de-dup guarantees.
- Published updated V2 version token while preserving concurrent upstream module token updates.
- Landed and pushed in `dungeoncrawler-content` commit: `92fc142`.

### 2026-06-07 — Phase 3 architecture hardening landed (lifecycle-safe panel listeners)
- Reworked `ActionRailPanel` DOM listener lifecycle to explicit bind/unbind management:
  - added `bindActionRailDomListener(...)`
  - added `teardownActionRailDomListeners()`
  - teardown now runs in both `setupActionRail()` and `destroy()`
- Removed dataset-sentinel listener ownership pattern and routed tab activation through canonical panel methods:
  - `resolveActionRailCategory(...)`
  - `setActiveActionRailCategory(...)`
- Updated contract routing test and added dedicated lifecycle regression:
  - `tests/action_rail_contract_routing_test.js`
  - `tests/action_rail_lifecycle_contract_test.js` (new)
- Published updated V2 version token while preserving concurrent upstream module token updates.
- Landed and pushed in `dungeoncrawler-content` commit: `ebf9ac3`.

### 2026-06-07 — Phase 4 architecture hardening landed (test-suite consolidation)
- Replaced stale legacy Action Rail test harnesses with current-contract coverage:
  - `tests/action_rail_panel_test.js`
  - `tests/action_rail_tabs_contract_test.js`
- New tests now validate current architecture boundaries:
  - coordinator-driven panel context + server availability contract
  - shared contract routing + selectable-action guardrails
  - canonical tab category surface (template + contract alignment)
  - service-boundary usage and lifecycle-safe listener patterns
- This retires obsolete assumptions from deprecated UIManager-era internals and aligns the suite with active V2 contracts.
- Landed and pushed in `dungeoncrawler-content` commit: `830e934`.

### 2026-06-07 — Phase 5 architecture hardening landed (context service boundary)
- Added shared context adapter: `js/v2/services/action-rail-context-service.js`.
- Moved Action Rail context assembly out of `ActionRailPanel.getActionRailContext()` into `buildActionRailContext(stateManager)`, including:
  - coordinator phase snapshot extraction
  - actor/turn context resolution
  - available-actions + action-contract exposure
  - status/canAutomate synthesis
- Updated panel boundary to consume the new service (`return buildActionRailContext(this.stateManager);`).
- Added focused context boundary regression:
  - `tests/action_rail_context_service_contract_test.js` (new)
  - updated `tests/action_rail_panel_test.js` to lock service delegation contract
- Published updated V2 version token while preserving concurrent upstream module-token updates.
- Landed and pushed in `dungeoncrawler-content` commit: `c457948`.

Focus order:
1. Gameplay correctness parity (actions/automation, encounter flows)
2. Room-transition side effects (`setActiveRoom()` consumers)
3. Map artifact/sprite contract edge cases
4. Cosmetic/layout parity

### 2026-06-02 — Node regression baseline restored
- `dungeoncrawler-content` Node regression loop (`for f in tests/*.js; do node $f; done`) is green.
- Hardened `tests/js_test_loader.js` for multi-line ESM imports.
- Updated/added contract-based panel regressions: Quest/Inventory/Character, Portrait/Merchant, Room/Party/Status.

### 2026-06-02 — Encounter round/turn indicators
- EncounterSystem now emits round-start + turn-start indicators into room chat (speaker: Narrator) and keeps structured console traces.
- Updated `tests/encounter_system_logging_contract_test.js` to lock the contract.

### 2026-06-02 — Room data object standardized (`room_id` only)
- Standardized room identifiers to `room_id` only (removed `room.id` alias) across GameShell room contracts + room-view payloads, HexCanvas, and RoomViewPanel.
- Added regressions in `tests/room_render_contract_test.js` to forbid `id` alias drift.
- Updated `tests/action_rail_panel_test.js` to match the current ActionRailPanel contract (actor header intentionally blank).
- Full Node regression loop is green.

### 2026-06-02 — Map renderables carry position + facing (locked)
- Extended `tests/room_render_contract_test.js` to assert renderables preserve `(q,r)`, `render.spriteKey`, and `render.orientation` for entities, authored room objects, and visual occupants.
- Hardened hex-object orientation normalization to accept `orientation | facing | direction | placement.orientation`.
- Updated `HexTokenRenderer` to rotate sprites to match `render.orientation` (labels/rings remain unrotated), while keeping the facing indicator overlay.
- Backend now preserves authored room-template facing by injecting `placement.orientation` + `state.metadata.orientation` for NPCs and fixed template items in `HexMapController` (e.g., Eldric `se`).
- Full Node regression loop remains green.

### 2026-06-02 — Always-visible orientation indicators
- Confirmed room render contract includes `render.orientation` (default `'n'`) for all renderables.
- Updated `HexTokenRenderer` to always draw the facing indicator (including North) so orientation is visible for every token, not only non-`'n'` entities.

### 2026-06-02 — Hover spread for crowded hexes
- Restored old-version UX: hovering a hex with multiple entities temporarily spreads them out around the hex.
- Spread mode overrides top-only visibility while hovered; reverts cleanly on hover-out.
- Node regressions: `tests/hex_token_renderer_spread_test.js` + `tests/hex_token_renderer_stacking_test.js` both pass.

### 2026-06-02 — Top-only hex rendering (stacking rules)
- Implemented deterministic hex stacking rules: only one visible token per hex.
- Priority: characters (PC/NPC/creature) > items > obstacles/terrain.
- Visually enforces “only one character/NPC per hex” (if multiple appear, shows the best candidate deterministically; selected character wins).
- Added Node regression: `tests/hex_token_renderer_stacking_test.js`.

### 2026-06-02 — Crowded-hex labels + hover jitter
- Disabled the hover-driven crowded-hex “spread” behavior (and its interaction-target overlays) that could thrash hover-in/out and cause visible flicker/jitter.
- Added deterministic per-hex token offsets so multiple entities/items in the same hex no longer perfectly overlap (labels become readable).
- Prevented hover from shifting the map by (1) keeping the `hexInfo` element layout-stable (toggle `visibility`, not `hidden`/`display:none`), (2) adding missing CSS so `.hexmap-hud-overlays` is `position:absolute` over the canvas (HUD updates no longer participate in layout), and (3) ensuring the PIXI canvas is inserted via `prepend()` so HUD nodes can never push it down even if CSS is stale.

### 2026-06-02 — Room background anchoring (hex-aligned)
- Implemented client support for `room.map_background` to render a room art image in the PIXI world background layer.
- Added a hard placement contract: `image_url`, `authored_hex_size`, `anchor_hex {q,r}`, `anchor_px {x,y}`.
- Placement math is locked via Node regression in `tests/hex_canvas_test.js` (`computeRoomBackgroundPlacement`).

### 2026-06-02 — Room battlemap generation + linking (admin UI)
- Extended the image generation admin UI to support a dedicated “Room battlemap background” use-case.
- When selected, generated images persist and link to `dc_campaign_rooms` as `slot=background` with `visibility=campaign_party`.
- HexMapController now injects `room.map_background.image_url` automatically when a linked background exists (default anchor center-to-origin; authors can override later).

### 2026-06-02 — Gilded Tankard background generated + wired through visual-state
- Generated a top-down battlemap background for campaign `131`, room `tavern_entrance` (The Gilded Tankard) and persisted it as a linked `slot=background` image.
- Updated `MapVisualStateProjector` to carry `room.map_background` through to `map_visual_state.topology.rooms[room_id]` so the client HexCanvas can render it.
- Verified via Drush that `bg_url` is present in the visual-state output and the PNG is accessible.

### 2026-06-02 — Campaign 133: Gilded Tankard background missing → fixed
- Root cause: campaign `133` had **no** `dc_generated_image_links` row for `dc_campaign_rooms:tavern_entrance` with `slot=background`.
- Fix: linked existing generated background (image_id `448`) to campaign `133` for `tavern_entrance`.
- Verified via Drush (calling `HexMapController::demo()` with the full launch context) that both `drupalSettings.hexmapDungeonData.rooms[tavern_entrance].map_background.image_url` and `drupalSettings.map_visual_state.topology.rooms[tavern_entrance].map_background.image_url` are present.

### 2026-06-02 — Template background fallback (prevents per-campaign drift)
- Added backend fallback for room background resolution: if no `dc_campaign_rooms` background exists for a room in a campaign, HexMapController now looks for a canonical template background keyed by `room.source_room_id` in `dungeoncrawler_content_room_templates`.
- Inserted a canonical template background link for `tavern_entrance` (image_id `448`).
- Validated link state: campaign `130` has `campaign=0` / `template=1` background link counts; repository lookup resolves the template URL successfully.

### 2026-06-02 — Room transition parity + guardrails
- Hardened `GameShell.setActiveRoom()` to mark `room:changed` with `_source: 'shell'` (prevents double transition via the room-change bridge) and to apply transition side-effects (reset view flags, resync ECS entities, reload chat + room view, prefetch connected context).
- Standardized explicit Search contract payloads to `{ search_mode: 'explicit' }` only (strict no-backcompat policy).
- Restored encounter transcript round/actor prefix contract in `ChatPanel.formatEncounterChatMessage()` (`Round ${context.round}: Actor ${context.actorName}: ...`).
- Node regression loop (`for f in tests/*.js; do node $f; done`) is green (including `deprecated_exploration_phase_test.js`).

### 2026-06-02 — Map tab deep-link → image generation (battlemap)
- Added an admin-only Map HUD button “Generate battlemap background” (Map tab) that deep-links to the image-generation interface with `use_case=room_battlemap` and auto-fills `target_campaign_id` + `target_room_id` for the active room.
- Added query-string prefill support in `ImageGenerationInterfaceForm` plus a read-only battlemap context preview (derived from `dc_campaign_rooms.layout_data`) and a suggested aspect ratio for faster prompt iteration.
- Fixed a Node regression harness crash by guarding `_syncMapAdminLinks` calls in `GameShell.setActiveRoom()` (optional chaining) so extracted setActiveRoom tests don’t require DOM/admin link wiring.
- Full Node regression loop remains green.

### 2026-06-02 — Compass label spacing (HUD)
- Fixed Map-tab compass rose labels so N/NE/SE/S/SW/NW text is equidistant from the compass center by centering PIXI.Text anchors and placing all labels at a constant radius.
- Verified Node regression loop remains green.

### 2026-06-02 — Map background visibility (floor alpha tuning)
- Root cause of “no background image on the map” (when data is present): room hex fill alphas were near-opaque (~0.9–1.0) while the background sprite default alpha was low (0.35), so the art was effectively hidden.
- Fix: when `room.map_background.image_url` exists, HexCanvas now clamps floor tile `fillAlpha` down (heuristic by `lineWidth`) so the art reads through.
- Fix: bumped backend default `room.map_background.alpha` to 0.9 for newly injected backgrounds.
- Verified: PHP lint clean and full Node regression loop remains green.

### 2026-06-03 — HexMap hover layout shift (map moves on hover) fixed
- Root cause: HUD overlays and server-unavailable banner were siblings of the PIXI canvas inside the same wrapper; when hover toggled HUD content, those elements could participate in normal-flow layout and push the canvas.
- Fix (client):
  - `HexCanvas` now inserts the PIXI canvas before the wrapper’s first child when present (so HUD nodes can never appear “above” the canvas in DOM order).
  - Added missing CSS to anchor the canvas (`position:absolute; inset:0`) and to pin HUD overlays + banner absolutely (no reflow on hover).
- Regression: added `tests/hex_canvas_dom_attachment_test.js` locking the DOM insertion contract.
- Landed in `dungeoncrawler-content` and pushed (commits: b8e021d, 691f533).

### 2026-06-03 — Crowded-hex top-only + hover expand selection
- Collapsed crowded hexes so only the top-ranked entity renders by default.
  - Priority: characters (PC/NPC/creature) > items > obstacles/terrain.
- Interaction contract:
  - Hover preserves the full entity list (so the existing hover “spread/expand” mode can expose sub-object selection).
  - Click emits only the top entity (so clicking a crowded hex selects the visible/top token by default).
- Regressions:
  - `tests/hex_token_renderer_stacking_test.js`
  - extended `tests/hexmap_v2_input_handler_test.js`
- Landed in `dungeoncrawler-content` and pushed (commit: b56d54e).

### 2026-06-03 — Room data object completeness (orientation + ids)
- Ensured canonical `map_visual_state.occupants.*[]` entries include:
  - `character_id` (when available)
  - `placement.orientation` (always present; defaults to `'n'`)
- Ensured canonical `map_visual_state.topology.rooms[].hexes[].objects[]` entries include:
  - `object_instance_id` (stable: `${room_id}:${q}:${r}:${object_id}:${index}`)
  - `orientation` (default `'n'`)
- Locked via `MapVisualStateProjectorTest`.
- Landed in `dungeoncrawler-content` and pushed (commits: a093cde, ad68e9b).

### 2026-06-03 — Room exits represented on the room object
- Backend: `MapVisualStateProjector` now attaches `topology.rooms[room_id].exits[]` derived from normalized connections.
  - Each exit includes `connection_id`, `type`, `target_room_id`, `origin_hex`, `target_hex`, and `is_passable/is_discovered/visibility_state`.
- UI: `GameShell` now derives the “Navigate” connections list from `room.exits` (not the global `topology.connections`).
- Regressions:
  - Added `tests/room_exits_contract_test.js` (Node)
  - Extended `MapVisualStateProjectorTest` to assert exits.
- Landed in `dungeoncrawler-content` and pushed (commits: 12bd3b7, 3cfdd36).
  - Note: 3cfdd36 wires `attachRoomExits()` in `project()` so exits are actually present for the UI.

### 2026-06-03 — Every hex represented in room visual-state
- Backend: `MapVisualStateProjector` now fills in missing room hexes within the room’s observed bounds so `topology.rooms[room_id].hexes[]` is complete (no sparse render gaps).
- Standardized per-hex terrain field as `terrain_type` in the canonical visual contract (consumed by HexCanvas styling).
- Nailed down hex object contract defaults:
  - `objects[].placement { room_id, hex_id, q, r }`
  - `passable/blocks_movement/movable/collectible` always present with deterministic defaults.
- Extended `MapVisualStateProjectorTest` to lock `subtitle`, `hex_bounds`, filled-hex behavior, and object placement.

### 2026-06-03 — Strict `terrain_type` standardization
- Removed remaining `tile_type` compatibility in `MapVisualStateProjector` input normalization (single-field contract).
- Updated functional controller fixtures to emit `terrain_type` (prevents `tile_type` drift reappearing).
- Landed in `dungeoncrawler-content` and pushed (commit: 38472b2).

### 2026-06-03 — HexCanvas terrain contract hardened
- Client: HexCanvas now uses `roomHex.terrain_type` as the sole per-hex terrain field (no `roomHex.terrain` fallback).
- Regression: added `tests/hex_canvas_terrain_contract_test.js` to forbid `tile_type` / `terrain` drift.
- Landed in `dungeoncrawler-content` and pushed (commit: 31d1a98).

### 2026-06-03 — Room id + object instance id strictness
- Removed `room.id` alias drift from V2 shell/renderer paths (room_id only).
- Preserved backend-provided `room.subtitle` (only derive a subtitle when the server didn’t provide one).
- Hex objects: V2 entity blueprints now prefer backend `object_instance_id` for stable addressing.
- Regressions:
  - `tests/room_metadata_merge_contract_test.js`
  - `tests/hex_object_instance_id_contract_test.js`
- Landed in `dungeoncrawler-content` and pushed (commit: 80f27bf).

### 2026-06-03 — Authored entry hex honored (origin)
- Backend: `MapVisualStateProjector` now selects exactly one entry hex deterministically:
  - if any hex is authored with `is_entry`/`entry`, use that (lexicographically first if multiple)
  - else use `(0,0)` if it lies within the derived room bounds
  - else fall back to the lexicographically-first authored hex
- Map-global grid origin (`map_meta.hex_grid.origin`) now prefers the room entry hex (instead of the first sorted hex).
- Regression: added `testProjectUsesAuthoredEntryHexForOrigin()` in `MapVisualStateProjectorTest`.
- Landed in `dungeoncrawler-content` and pushed (commit: 6e3543d).

### 2026-06-03 — Compass rose labels equidistant
- Client: HexCanvas compass labels now use centered anchors and a constant label radius so N/NE/SE/S/SW/NW are equidistant from the compass center.
- Regression: added `tests/hex_canvas_compass_contract_test.js`.
- Landed in `dungeoncrawler-content` and pushed (commit: ec36f75).

### 2026-06-03 — Room→map contract: per-hex lighting
- Backend: `MapVisualStateProjector` now includes `hexes[].lighting` on every room hex (authored + filled) so HexCanvas styling can reliably key off lighting without guessing/inheriting.
- Regression: extended `MapVisualStateProjectorTest` to assert `hexes[].lighting` matches room lighting.
- Landed in `dungeoncrawler-content` and pushed (commit: 11103f3).

### 2026-06-03 — Room metadata strictness (no multi-format fallbacks)
- Client: removed backward-compatible parsing of `room.lighting.level` and `room.terrain[]` in `GameShell` (single-shape contract: `room.lighting` is a string; `room.terrain.type` is a string).
- Regression: added `tests/room_terrain_lighting_contract_test.js`.
- Landed in `dungeoncrawler-content` and pushed (commit: ca7b1cb).

### 2026-06-03 — Room→map contract: per-hex elevation
- Backend: `MapVisualStateProjector` now includes `hexes[].elevation_ft` on every room hex (authored + filled) with a deterministic default (`0.0`).
- Regression: extended `MapVisualStateProjectorTest` to assert `elevation_ft` is always present.
- Landed in `dungeoncrawler-content` and pushed (commit: d99afa0).

### 2026-06-03 — Hover hex inspection badges (object-level cues)
- Client: hovering a hex now shows per-token attribute badges directly on the map for hex-objects (and any renderables carrying those metadata fields).
  - P = passable (green=true / red=false)
  - B = blocks_movement (red=true / green=false)
  - M = movable (gold=true / gray=false)
  - C = collectible (cyan=true / gray=false)
  - S = stackable (purple=true / gray=false)
- Projector bridge: hex-object blueprints now carry `state.metadata.stackable` (from definition) so the badge is always present.
- Regressions:
  - `tests/hex_token_renderer_inspection_badges_contract_test.js`
  - `tests/hex_object_stackable_metadata_contract_test.js`
- Landed in `dungeoncrawler-content` and pushed (commit: 22798b5).

### 2026-06-03 — Hover on every hex + facing indicator visible
- Client: HexCanvas now renders a world-space hover inspector for every hex (no token required) showing: `hex_id`, `q/r`, `terrain_type`, `lighting`, `elevation_ft`, `is_entry/is_visible/is_discovered`, and object count.
  - Uses `hex.eventMode = 'static'` (PIXI v7) for reliable pointer events on every hex.
- Client: HexTokenRenderer now draws an always-visible facing indicator based on `render.orientation`.
- Regressions:
  - `tests/hex_canvas_hover_hex_attributes_contract_test.js`
  - `tests/hex_token_renderer_facing_indicator_contract_test.js`
- Landed in `dungeoncrawler-content` and pushed (commit: 3e2e7a8).

### 2026-06-03 — Hex attribute indicators + legend panel
- Client: HexCanvas now draws on-map glyphs for key per-hex attributes (aligned with hover tooltip):
  - Entry marker (`is_entry`)
  - Not-visible marker (`is_visible=false`)
  - Undiscovered marker (`is_discovered=false`)
  - Object-count badge (`objects.length`)
  - Elevation label (`elevation_ft`)
- UI: added an always-on Map legend panel (HTML HUD) describing the glyphs.
- Regression: `tests/hex_canvas_hex_attribute_indicators_contract_test.js`.
- Landed in `dungeoncrawler-content` and pushed (commit: 073b74a).

### 2026-06-07 — Action Rail bus ownership hardening (Phase 6)
- Removed redundant visited-location preload ownership from `NavigationSystem`; it now owns only direct navigation execution (`user:navigate`) while `ActionRailPanel` remains the sole preloader for known destinations.
- Added end-to-end Action Rail bus flow regression coverage:
  - `tests/action_rail_bus_flow_contract_test.js` (panel route → bus event → system subscribers/handlers)
  - Updated `tests/action_rail_navigate_service_contract_test.js` to enforce panel-owned preload + system-owned execute boundary.
- Cache-bust/version updates:
  - `hexmap-v2` library version: `20260607-v2-action-bus-flow-2`
  - `js/hexmap-v2.js` import token and `GameShell` `NavigationSystem` import token aligned.
- Landed in `dungeoncrawler-content` and pushed (commit: `10dd827`).

### 2026-06-07 — Action Rail navigate renderer extraction (Phase 7)
- Extracted navigate-category rendering/preload orchestration from `ActionRailPanel` into a dedicated module:
  - `js/v2/services/action-rail-navigate-panel-service.js`
- `ActionRailPanel` now delegates navigate tab rendering to the new service (`buildNavigateActionRailPanel(this, context)`), reducing panel coupling and keeping category-specific behavior modular.
- Updated navigate contract coverage to lock this architecture boundary:
  - `tests/action_rail_navigate_service_contract_test.js` now asserts service-owned preload + panel delegation + navigation-system execute ownership.
- Cache-bust/version updates:
  - `hexmap-v2` library/version token: `20260607-v2-action-navigate-panel-service-2`
- Landed in `dungeoncrawler-content` and pushed (commit: `4c51ec7`).
