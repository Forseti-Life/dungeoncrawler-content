# HexMap V2 — New UI Architecture

- **Status:** in_progress — core cutover complete; post-cutover hardening complete for current chat/detail parity wave, with broader legacy parity work continuing
- **Created:** 2026-05-31T02:37:00Z
- **Agent:** architect-copilot
- **Type:** architecture + implementation
- **Priority:** high

## Background

`hexmap.js` has grown to 17,701 lines. Rather than refactoring it, the Board approved building a clean replacement UI (`hexmap-v2`) alongside the old one. Old `hexmap.js` is preserved untouched as reference.

## Decisions (Board-approved)

- **Stack:** Vanilla JS + ES modules (no framework — best parse/load perf for a game shell)
- **Canvas:** PIXI.js v7.3.2 (same as existing, no upgrade)
- **Pattern:** Event Bus + Panels (GameEventBus pub/sub — eliminates direct hexmap↔UIManager coupling)
- **Location:** `js/v2/` inside existing `dungeoncrawler_content` module; new `hexmap-v2` Drupal library, old library untouched until Phase 10
- **Scope:** Full replacement — shell panels + PIXI canvas renderer

## Architecture

```
js/v2/
├── GameEventBus.js
├── GameShell.js
├── canvas/
│   ├── HexCanvas.js
│   ├── HexTokenRenderer.js
│   ├── HexFogOfWar.js
│   └── HexInputHandler.js
├── systems/
│   ├── EncounterSystem.js
│   ├── PlayerAutomation.js
│   ├── QuestSystem.js
│   └── NavigationSystem.js
├── panels/
│   ├── PortraitPanel.js
│   ├── MerchantPanel.js
│   ├── CombatPanel.js
│   ├── ActionRailPanel.js
│   ├── ChatPanel.js
│   ├── QuestPanel.js
│   ├── InventoryPanel.js
│   ├── CharacterPanel.js
│   ├── RoomViewPanel.js
│   ├── PartyRailPanel.js
│   └── StatusPanel.js
└── utils/
    ├── quest-utils.js
    ├── spell-utils.js
    └── dom-utils.js
```

Entry point: `js/hexmap-v2.js` → `Drupal.behaviors.hexMapV2`

## Phase Tracker

| Phase | Description | Status |
|---|---|---|
| 0 | Scaffolding — js/v2/ structure + Drupal library | ✅ done — commit `e28c437a2`, pushed 2026-05-31 |
| 1 | GameEventBus + GameShell skeleton | ✅ done — commit `ed385e411`, pushed 2026-05-31 |
| 2 | HexCanvas — PIXI rendering | ✅ done — commit `38afa3c66`, pushed 2026-05-31 |
| 3 | HexTokenRenderer + HexFogOfWar | ✅ done — commit `a6da98eed`, pushed 2026-05-31 |
| 4 | CombatPanel + EncounterSystem | ✅ done — commit `3b9cdfc5b`, pushed 2026-05-31 |
| 5 | ActionRailPanel | ✅ done — commit `5b6050e5e`, pushed 2026-05-31 |
| 6 | PortraitPanel + MerchantPanel | ✅ done — commit `eadc7311b`, pushed 2026-05-31 |
| 7 | ChatPanel | ✅ done — commit `6d1486a6f`, pushed 2026-05-31 |
| 8 | QuestPanel + InventoryPanel + CharacterPanel | ✅ done — commit `78b56bd31`, pushed 2026-05-31 |
| 9 | RoomViewPanel + PartyRailPanel + StatusPanel | ✅ done — commit `4ac3a6e0e`, pushed 2026-05-31 |
| 10 | Integration + cutover | ✅ done — commit `224a6dc53`, pushed 2026-05-31 |

## Acceptance Criteria

- All existing 89 JS + 3 PHP tests pass after each phase
- No browser console errors for new library
- Each panel independently renderable with mock data
- `vendor/bin/drush cr` + `apachectl graceful` after each phase
- Commit pushed before starting next phase

## Follow-on hardening tracker (2026-06-01)

**Status:** in progress

### Completed in this hardening wave

1. **Server-authoritative round/turn transcript alignment**
   - Removed misleading transient `Narrator Turn 3+:` progress copy.
   - Standardized room-turn progress wording on the server to `Initiative order is resolving nearby NPC turns...`.
   - Preserved `Initiative Order` as the authoritative speaker instead of rewriting it client-side to `Narrator`.
   - Stopped removing initiative-order progress messages from the chat transcript.

2. **Deterministic pending/transcript lifecycle**
   - Preserved durable progress lines after settle instead of deleting them.
   - Finalized preserved progress lines so they do not remain visually pending forever.
   - Preserved the player's submitted line on send/transport failures and appended error notices separately.
   - Removed the fake startup `...` placeholder and the dead placeholder-removal lifecycle flag.

3. **Canonical chat-line contract introduction**
   - Upgraded `ChatPanel` line normalization so rendered/remembered lines now carry explicit:
     - `source`
     - `authority`
     - `messageClass`
     - `channel`
     - `view`
     - `persistent`
     - `requestId`
     - `eventId`
   - Routed the main authoritative paths through that contract:
     - room history
     - streamed room progress / streamed room responses
     - encounter event narration
     - session-view messages
   - Completed the follow-on classification pass so non-stream room responses, session submit flows, local validation/travel notices, and empty-state helper lines now declare explicit local-vs-authoritative metadata instead of falling back to transcript-like defaults.
   - Added focused normalization-boundary regression coverage in `tests/chat_panel_line_contract_test.js`.

4. **Legacy crowded-hex parity restoration**
   - Ported the legacy crowded-hex hover spread behavior into `HexTokenRenderer`.
   - Co-located tokens now temporarily spread on hover and get deterministic temporary interaction targets.
   - Spread-target clicks route back through the existing `hex:clicked` selection flow with a single selected entity payload.
   - Added focused regression coverage in `tests/hex_token_renderer_spread_test.js`.

5. **Legacy hover/detail helper parity restoration**
   - Ported the legacy `GameShell` hover/detail helper cluster:
    - `resolveTerrainKey()`
    - `describePassability()`
    - `describeEntitiesAtHex()`
    - `getObjectLabelAtHex()`
    - `getObjectIdAtHex()`
    - `describeObjectsAtHex()`
    - `describeConnectionAtHex()`
    - `getHexDetail()`
   - Status/inspector surfaces now receive legacy-aligned terrain, passability, entity, object, and connection labels instead of the thinner interim V2 detail payload.
   - Extended `tests/hexmap_v2_interaction_state_test.js` to lock the richer detail contract and validated it alongside the focused chat and crowded-hex suites.

6. **Additional room-transition parity restoration**
   - Restored legacy-style room subtitle metadata in canonical room payloads so transition consumers receive terrain / lighting / size context instead of a thinner room shell.
   - Restored connected-room context prefetch during `setActiveRoom()` transitions so adjacent room chat/view warmup matches the old room-change flow more closely.
   - Extended `tests/hexmap_v2_interaction_state_test.js` to assert subtitle metadata and transition prefetch behavior, then validated it alongside the GameShell-focused bootstrap/chat regressions.

7. **Map artifact / sprite contract repair**
   - Reviewed the V2 map-tab room data contract end-to-end across canonical room topology, occupant projection, object definitions, and sprite resolution.
   - Fixed a render-contract gap where canonical visual occupants were only used as enrichment; occupant-only NPC/PC records can now become ECS render blueprints even without duplicate `dungeonData.entities` payload entries.
   - Fixed the sprite boundary between `GameShell`, `SpriteService`, and `HexTokenRenderer` so canonical presentation/object-definition sprite IDs are actually resolved/applied for V2 map tokens instead of stopping at the old `RenderSystem`-only path.
   - Added focused regression coverage in `tests/hexmap_v2_map_artifacts_test.js` for occupant-only canonical NPC render contracts and revalidated the bootstrap / interaction / map-artifact suites.

8. **Crowded-hover / player-token hardening**
   - Fixed crowded-hex hover jitter by removing re-entrant `hex:hovered` emission from temporary spread targets; spread-target hover now preserves the current crowded-hex anchor instead of retriggering the hover/spread loop under the pointer.
   - Repaired canonical party occupant token inference so `occupants.party` records without explicit `occupant_type` still resolve as `player_character` map tokens.
   - Wired occupant-only party/player tokens to inherit the launch-character portrait sprite contract when the canonical party record lacks its own sprite metadata, covering Eldric-style missing-player cases.
   - Extended focused regression coverage in `tests/hex_token_renderer_spread_test.js` and `tests/hexmap_v2_map_artifacts_test.js`, then rebuilt Drupal caches so the live V2 map tab picks up the updated JS.

9. **Canonical object-definition enforcement**
   - Removed the remaining V2 fallback to legacy `dungeonData.object_definitions`; `GameShell` object-definition lookup and render-blueprint construction now use canonical `mapVisualState.presentation.object_definitions` only.
   - Kept payload entities renderable when canonical definitions are absent, but stopped legacy payload definition metadata from driving sprite/definition behavior for rooms and NPCs.
   - Extended focused map-artifact regression coverage to lock the canonical-only contract, then rebuilt Drupal caches so the live V2 map tab picks up the updated JS.

### Current plan / remaining hardening work

1. Keep the turn-status lane and transcript lane separate in data shape while preserving wording parity.
2. Continue the broader HexMap V2 parity plan from the current session plan’s remaining legacy-behavior gaps now that chat-contract hardening, crowded-hex parity, hover/detail helper parity, room-transition metadata parity, the current map artifact/sprite repair slice, the hover/player-token hardening slice, and the canonical object-definition enforcement slice are back in place.
3. Prioritize the remaining automation/action-rail parity plus any still-thin map artifact surfaces or `setActiveRoom()` side effects around chat/session refresh, room-driven panels, or remaining connection/sprite fallback edges.

## References

- Old UI (reference only): `js/hexmap.js`
- Canonical occupant API (reuse): `src/Service/MapVisualStateProjector.php`
- ECS (reuse unchanged): `js/ecs/`
- Combat API (reuse): `js/hexmap-api.js`
- Current session plan: `/root/.copilot/session-state/664c9297-3028-4602-afa7-3b51e69d6d38/plan.md`
- Repo: `Forseti-Life/dungeoncrawler-content`

---

## Phase Update — Wave 5 Bus Event + Cross-Class Refactor (2026-05-31)

**Status:** complete — 4 commits pushed to `Forseti-Life/dungeoncrawler-content`

### Work completed

**Review Pass 1** (`refactor: fix bus event wiring... review pass 1`):
- QuestPanel: added `quest:progress-updated` listener
- InventoryPanel: fixed `game:init` key `inventoryContext` → `inventory`
- RoomViewPanel: fixed `room:changed` payload extraction
- GameShell: emits both `room:changed` and `room:view-loaded` after room load
- MerchantPanel: replaced dead `stateManager.hexmap.getVisualOccupants()` with bus-driven `_cachedOccupants`
- EncounterSystem: added combat bus listeners and stubs

**Review Pass 2** (`refactor: wire cross-system proxies... review pass 2`):
- GameShell: `_buildHexmapShim()` — stateManager.hexmap-compatible adapter using live JS accessors
- GameShell: `_initPanels()` passes `(dungeonData, stateManager)` to all panels — fixes `stateManager = {}` bug
- EncounterSystem/NavigationSystem/PlayerAutomation: added proxy helpers for ActionRail + Chat; replaced all misplaced UIManager-era `this.X` calls
- GameShell: `_onTabChanged` emits `character:sheet-requested`; `refreshCharacterInventoryFromApi` emits `inventory:changed`

**Review Pass 3** (`refactor: fix ChatPanel cross-class calls... review pass 3`):
- ChatPanel: submit handler emits `user:chat-submitted` / `user:session-message-submitted` instead of calling GameShell directly
- ChatPanel: `loadSessionViewMessages` emits `user:chat-history-requested` / `user:session-view-requested`; added `session:view-data` listener
- ActionRailPanel: `handleActionRailPanelAction` emits `user:action-selected` instead of calling system methods directly
- GameShell: `_postSessionViewMessage()`, `character:inventory-refresh-requested` handler, 3 session-view bus handlers

**Review Pass 3b** (cross-class renderInventoryPanel fix):
- MerchantPanel: emits `inventory:changed` instead of `this.renderInventoryPanel()`
- CharacterPanel: emits `inventory:changed` + `character:inventory-refresh-requested` instead of 3 UIManager-era calls
- GameShell: handles `character:inventory-refresh-requested`

All 26 v2 files pass `node --check`. Cache cleared.

---

## Phase Update — Template Refactor (2026-05-31)

**Status:** template complete, 500 fixed, page loads (403 = auth-gated, not broken)

### Changes made

1. **Template rebuilt from old HTML/CSS** — `hexmap-v2.html.twig` is now the full `hexmap-demo.html.twig` content with all v2 `data-*` attributes mapped on top. Preserves all existing CSS classes and layout; no visual regression.

2. **All 11 panel bindings wired:**
   - `data-panel="combat"` → `#turn-hud`
   - `data-panel="chat"` → `#hexmap-chat`
   - `data-panel="action-rail"` → `#hexmap-action-rail`
   - `data-panel="portrait"` → `#npc-portraits-panel`
   - `data-panel="merchant"` → `#merchant-trade-panel`
   - `data-panel="room-view"` → `#room-view-panel`
   - `data-panel="character"` → `#sidebar-panel-character`
   - `data-panel="inventory"` → `#sidebar-panel-inventory`
   - `data-panel="quest"` → `#sidebar-panel-quests`
   - `data-panel="party-rail"` → new `<aside>` added to workspace
   - `data-panel="status"` → falls back to root container via GameShell `?? c`

3. **Sidebar tab collision fixed** — tab buttons renamed `data-sidebar-tab="*"` (were `data-panel="*"`) to prevent `querySelector` returning button instead of panel div.

4. **CombatPanel fixes:**
   - `tracker-wrap` moved to `#initiative-tracker` (was wrongly on `#combat-controls`)
   - `end-turn-btn` added inside `#turn-hud` (old `#end-turn` was outside the combat panel scope)

5. **New elements added** for panels with no old-template equivalent:
   - `data-status="unavail-banner"`, `data-status="zoom"`, `data-status="hex-info"` — canvas overlays
   - `data-room="scene-image"` img, `data-room="responders"` div — in room-view panel
   - `data-char="entity-info/name/stats"` — hidden block for canvas token selection
   - `data-quest="toast"` — hidden div for quest notifications
   - `data-party="rail"` + `data-party="empty"` — inside new party-rail aside

6. **Critical 500 fix** — `sites/` subdirectory inside the symlinked module caused Drupal's extension discovery to scan Drupal core `.info.yml` files lacking `core_version_requirement`. Added `'sites'` to `$settings['file_scan_ignore_directories']` in `/var/www/html/dungeoncrawler/web/sites/default/settings.php`. `drush cr` now succeeds cleanly.

### Commit
`7f9f3434f` — pushed to `Forseti-Life/dungeoncrawler-content` main

### Next
- Verify full game flow in browser (PIXI canvas renders, panels populate)
- CSS additions may be needed for new elements (`data-party`, `data-status` overlays, `entity-info-panel`)
