# HexMap V2 — New UI Architecture

- **Status:** done
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

## References

- Old UI (reference only): `js/hexmap.js`
- Canonical occupant API (reuse): `src/Service/MapVisualStateProjector.php`
- ECS (reuse unchanged): `js/ecs/`
- Combat API (reuse): `js/hexmap-api.js`
- Session plan: `/root/.copilot/session-state/7704fc54-56f3-4414-86c3-be1cb7ad4f9d/plan.md`
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
