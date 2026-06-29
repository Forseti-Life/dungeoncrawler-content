# dc-ui-hexmap-thin-client — Implementation Notes

**Feature:** dc-ui-hexmap-thin-client
**Commit:** `766c251f7`
**Date:** 2026-04-22

## What was done

### New file: `js/HexmapStateSync.js`

A dedicated ES module class that encapsulates all server-state polling and
reconciliation logic previously inlined in `Drupal.behaviors.hexMap`.

**Class interface:**
- `new HexmapStateSync(hexmap)` — pass the hexMap object as reference
- `start()` — begin interval polling (3s default, configurable via `config.serverStateSyncIntervalMs`)
- `stop()` — clear timer, reset in-flight state
- `isActive()` — boolean: is polling running?
- `sync({ force, silent })` — single poll cycle; calls `combatApi.getCurrentState()`
- `apply(serverState)` — reconcile authoritative server state onto ECS + stateManager

**JSDoc responsibility labels:**
- "State adapter: polls server for authoritative game state…"
- Explicitly notes: no UI code, no gameplay rules, no API calls for combat actions

### Changes to `hexmap.js`

**Removed** (5 methods, 3 properties):
- `startServerStateSync`, `stopServerStateSync`, `buildCurrentStatePayload`,
  `syncCurrentStateFromServer`, `applyServerCurrentState`
- Properties: `currentStateSyncTimer`, `currentStateSyncInFlight`, `currentStateSyncFailures`

**Added:**
- `import { HexmapStateSync } from './HexmapStateSync.js';`
- `stateSync: null` property on hexMap object (labelled `[THIN-CLIENT: state adapter]`)
- `attach()`: `this.stateSync = new HexmapStateSync(this); this.stateSync.start();`
- `detach()`: `this.stateSync?.stop(); this.stateSync = null;`

**Seam comments added:**
- `UIManager` class JSDoc: `[THIN-CLIENT: shell UI + chat adapter]` with explicit boundary list
- `handleNavigationResult()` JSDoc: `[THIN-CLIENT: server-authoritative]` — clarifies this
  reconciles server-returned room payload (nav.room/entities/connections), not client-originated state
- `applyWorldDelta()` JSDoc: `[THIN-CLIENT: server-authoritative]` — server-originated deltas only

## AC verification

| AC | Criterion | Status |
|----|-----------|--------|
| AC-001 | Client submits intent, renders server-confirmed results | ✅ HexmapStateSync.apply() is the single reconciliation path |
| AC-002 | Backend owns gameplay mutations | ✅ applyWorldDelta/hydrateFromServer only consume server-provided deltas |
| AC-003 | Client retains presentation-only responsibilities | ✅ UIManager labelled and bounded |
| AC-004 | Responsibilities clearly separated | ✅ State adapter extracted; seam comments throughout |

## What was NOT done (not in scope)

- Physical extraction of UIManager to its own file (would be a separate refactor task)
- Physical extraction of Pixi rendering to its own file
- Creating new backend endpoints (AC-002 already met by existing server-authoritative path)
- Moving `applyWorldDelta` out of hexMap — it needs direct access to `this.dungeonData`
  and `this.paintActiveRoom` / `this.refreshFogOfWar`. It's a presentation cache update,
  correctly annotated as `[THIN-CLIENT: server-authoritative]`.

## Syntax verification

```
node --check HexmapStateSync.js → OK
node --check hexmap.js          → OK
```
