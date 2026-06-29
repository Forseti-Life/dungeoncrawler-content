# Dev Outbox — dc-cr-clan-dagger

- Status: done
- Summary: Implemented dc-cr-clan-dagger in full: clan_dagger.json catalog entry created, CharacterManager extended with `grantAncestryStartingEquipment()` to auto-grant clan dagger to Dwarves at character creation (non-Dwarves excluded), sell_taboo enforcement added to InventoryManagementService via new `sellItem()` method, sell route and controller action added. Three pre-existing bugs fixed in the process: (1) Drupal DB transaction pattern was wrong across all 5 blocks in InventoryManagementService — `$this->database->commit()` doesn't exist, replaced with proper `DatabaseTransaction` scope-exit commit pattern; (2) `removeItemFromInventory` filtered on `location_type = 'inventory'` but items are stored as `'carried'`; (3) service IDs in `InventoryManagementController::create()` were wrong. All AC criteria verified via `drush ev`. Commits: `5bc95ffe4` (main feature), `efc7eef2a` (bug fixes).

## Next actions
- QA: add `dungeoncrawler_content.api.inventory_sell_item` to qa-permissions.json for `administrator` and `dc_playwright_admin` roles
- QA: run sell route in Playwright test suite

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: Clan Dagger is a Dwarf identity item from the AC. Completing it with proper sell_taboo enforcement makes Dwarf ancestry functionally correct per PF2e rules. The bug fixes to InventoryManagementService prevent silent data corruption across all inventory operations.
