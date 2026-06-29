# Portrait & Merchant Panel Canonical Visual-State Cutover

**Date:** 2026-05-31
**Seat:** architect-copilot
**Inbox item:** 20260527-map-tab-recreation (ARCHIVED)

## Summary

Extended the map visual-state canonical cutover to cover the portrait panel and merchant panel in hexmap.js. Both panels now source data exclusively from canonical `map_visual_state` occupants; no remaining `dungeonData.entities` reads exist in display surfaces.

## Work Done

### MapVisualStateProjector.php
- Added `is_merchant` (bool) to occupant `presentation`:
  - Keyword detection: checks display_name, name, role, occupation, description, content_id for merchant indicators
  - Explicit flag detection: `entity.state.merchant_enabled`, `entity.state.merchant.enabled`, `entity.state.merchant_stock`
  - NPC entities only; PC/party members always `false`
- Added `role` (string) to occupant `presentation`:
  - Sources from `entity.state.metadata.role` or `entity.state.metadata.occupation` (in that order)
  - Empty string if neither present

### hexmap.js
- `buildRoomPortraitEntries()` (~line 5290): Rewritten to use `getVisualOccupants()` + `isVisualOccupantVisible()` + room_id filter. Maps `occupant.presentation.portrait_url` and `occupant.presentation.role`. Sprite-service fallback preserved.
- `buildRoomMerchantEntries()` (~line 2687): Rewritten to filter canonical occupants by `presentation.is_merchant === true` for the active room. Removed all `dungeonData.entities` access and `entityLooksMerchant()` calls.
- `entityLooksMerchant()` (~line 2643): Still present but no longer called; dead code for cleanup pass.

### Tests
- PHP `testOccupantPresentationIncludesMerchantAndRole`: Covers keyword detection, explicit `merchant_enabled` flag, role from `metadata.role`, role fallback to `metadata.occupation`. 3 tests, 39 assertions — all passing.
- JS bootstrap tests: 9 new canonical-cutover cases (portrait entries, room filter, hidden occupant exclusion, merchant detection, merchant room filter, empty merchant state). Total: 89 tests, all passing.

## Verification

- PHP projector tests: 3/3 passing (39 assertions)
- JS bootstrap tests: 89/89 passing
- JS fullscreen layout tests: 15/15 passing
- JS chat context tests: 86/86 passing

## Commit

`00bced5ac` — feat(hexmap): cut portrait and merchant panels to canonical visual-state
Pushed to: `Forseti-Life/dungeoncrawler-content` main

## Remaining Deferred Items

- `entityLooksMerchant()` dead code cleanup
- ECS/gameplay mutation flows audit (not display surfaces; intentionally deferred)
- BrowserTest 403 environment blocker
- Legacy `hexmapDungeonData` bootstrap removal (final step)
