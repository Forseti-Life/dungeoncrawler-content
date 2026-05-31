# Implementation Notes (Dev-owned)
# Feature: dc-cr-clan-dagger

## Summary
EXTEND: CharacterManager already had ANCESTRIES data for Dwarf; ContentRegistry system already existed for items. Implementation adds: (1) clan_dagger.json catalog entry, (2) `grantAncestryStartingEquipment()` in CharacterManager, (3) `sellItem()` in InventoryManagementService with sell_taboo enforcement, (4) sell route and controller action.

Pre-existing bugs found and fixed: Drupal DB transaction pattern was wrong across all 5 blocks in InventoryManagementService (called `$this->database->commit()` which doesn't exist; `removeItemFromInventory` filtered on `location_type = 'inventory'` but items are stored as `'carried'`).

## Files / Components Touched

- `dungeoncrawler_content/content/items/clan_dagger.json` — NEW: clan dagger item catalog
- `dungeoncrawler_content/src/Service/CharacterManager.php` — added InventoryManagementService dependency + `grantAncestryStartingEquipment()` + called from `createCharacter()`
- `dungeoncrawler_content/src/Service/InventoryManagementService.php` — fixed all 5 DB transaction blocks; fixed location_type filter in `removeItemFromInventory()`; added `sellItem()` public method
- `dungeoncrawler_content/src/Controller/InventoryManagementController.php` — added `sellItem()` controller action; fixed service IDs in `create()`
- `dungeoncrawler_content/dungeoncrawler_content.routing.yml` — added `dungeoncrawler_content.api.inventory_sell_item` route
- `dungeoncrawler_content/dungeoncrawler_content.services.yml` — added `@dungeoncrawler_content.inventory_management` as 4th arg to `character_manager` service
- `dungeoncrawler_content/dungeoncrawler_content.install` — added `dungeoncrawler_content_update_10029` to import clan-dagger into ContentRegistry

## Data Model / Storage Changes
- Schema: none (uses existing `dc_inventory_items` table + ContentRegistry)
- Item stored with `sell_taboo: true` and `ancestry_granted: true` in state_data JSON
- No config exports required

## New routes introduced
- `POST /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/sell`
  - Route name: `dungeoncrawler_content.api.inventory_sell_item`
  - Auth: requires valid session / role with inventory access
  - Body: `{ "gm_override": true|false }`
  - Response sell_taboo blocked: `{ "success": false, "sell_taboo": true, "message": "..." }`
  - Response sell_taboo override: `{ "success": true }`
  - **QA note**: `qa-permissions.json` needs entries for this route for `administrator` and `dc_playwright_admin` roles

## Pre-existing bugs fixed
1. **DB transaction pattern** — `InventoryManagementService` was calling `$this->database->commit()` (not a valid Drupal API method) and `$this->database->rollBack()` (also wrong). Fixed all 5 transaction blocks to: `$transaction = $this->database->startTransaction(); ... $transaction->rollBack();`. DatabaseTransaction commits on scope exit automatically.
2. **location_type mismatch in removeItemFromInventory** — Filter was `location_type = 'inventory'` but `addItemToInventory()` stores with default `location_type = 'carried'`. Removed the condition; `item_instance_id` + `location_ref` uniquely identifies the item.
3. **Service IDs in InventoryManagementController::create()** — Was calling non-existent `dungeoncrawler_content.inventory_management_service`; fixed to `dungeoncrawler_content.inventory_management`.

## Owner ID gotcha
`grantAncestryStartingEquipment()` must pass the numeric `$character_id` (the DB row ID) as `owner_id` to `InventoryManagementService::addItemToInventory()`. The `validateOwner()` method calls `CharacterStateService::getState($owner_id)` which queries `dc_campaign_characters.id` (integer), not UUID.

## Verification
All AC verified via `drush ev`:
- Dwarf creation → clan-dagger in inventory with `ancestry_granted=true`, `sell_taboo=true` ✓
- Non-dwarf (Half-Elf) creation → no clan-dagger ✓
- Two separate Dwarves → separate item instances ✓
- Sell without GM override → `success=false`, `sell_taboo=true` ✓
- Sell with GM override → `success=true`, item removed from DB ✓
- Catalog: 1d4 piercing, agile + dwarf + versatile S traits, bulk L, level 0, price 0gp ✓

## Commits
- `5bc95ffe405ce780e856cc76c8456da424bcfca5` — clan_dagger.json, CharacterManager, controller, routing, services.yml, install hook
- `efc7eef2ae0b6fd4d2ba04dc7d337d15a8a16db0` — InventoryManagementService transaction fixes + sellItem()
