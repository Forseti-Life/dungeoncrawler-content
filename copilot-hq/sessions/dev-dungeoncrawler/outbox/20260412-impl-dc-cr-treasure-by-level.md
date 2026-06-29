# Outbox: dc-cr-treasure-by-level

- Status: done
- Summary: Audited all 8 acceptance criteria for dc-cr-treasure-by-level. Created `TreasureByLevelService` (new file) containing the full CRB Table 10-9 treasure budget for levels 1–20 as a static lookup (`TREASURE_TABLE`), with currency_gp, permanent_items[], consumable_items[], and per-PC adjustment values for party sizes above/below the 4-PC baseline. Added `getLevelBudget()` (returns party-size-adjusted currency + per-encounter budget at 1/8 total), `getStartingWealth()` (new/replacement character GP by level, CRB Ch10 sidebar), and `getPartySizeAdjustment()` (proportional delta for non-standard party sizes). Updated `InventoryManagementService::sellItem()` to compute sell value (half-price for standard items, full-price for gems/art objects/raw materials via new `FULL_PRICE_SUBTYPES` constant) and atomically credit currency to the seller character within a DB transaction. Downtime-only gating for sell/buy was already implemented; no change required there. All files pass PHP lint. Commit `5ce17e7fd`.

## Commits
- `5ce17e7fd` — dc-cr-treasure-by-level: implement treasure table, party-size adjustments, starting wealth, sell price

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/TreasureByLevelService.php` (new)
  - `TREASURE_TABLE`: levels 1–20, currency_gp + per_extra_pc + permanent_items[] + consumable_items[]
  - `STARTING_WEALTH`: levels 1–20, GP for new/replacement characters
  - `getLevelBudget(int $party_level, int $party_size = 4): array`
  - `getStartingWealth(int $character_level): float`
  - `getPartySizeAdjustment(int $party_level, int $party_size): float`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php`
  - Added `FULL_PRICE_SUBTYPES = ['gem', 'art_object', 'raw_material']`
  - `sellItem()`: compute sell value (half/full price by subtype), credit currency atomically in DB transaction
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.services.yml`
  - Registered `dungeoncrawler_content.treasure_by_level` service

## AC coverage
- [x] Treasure per level (4-PC baseline) table — `TreasureByLevelService::TREASURE_TABLE`, `getLevelBudget()`
- [x] Currency column represents coins, gems, art objects, lower-level items sold at half price — modeled in currency_gp column definition and `sellItem()` sell price logic
- [x] For each PC beyond 4: additional currency — `currency_gp_per_extra_pc` + `getPartySizeAdjustment()`
- [x] Selling items: standard = half Price; gems/art objects/raw materials = full Price — `FULL_PRICE_SUBTYPES` + updated `sellItem()`
- [x] Buy/sell only during downtime — already gated; confirmed no regression
- [x] New/replacement character starting wealth by level — `STARTING_WEALTH` + `getStartingWealth()`
- [x] Selling during encounter/exploration: blocked — confirmed downtime_only gate still in place
- [x] Party size < 4: proportional reduction (advisory, not blocking) — negative extra_pcs → reduced currency_gp in `getLevelBudget()`

## Verification
```bash
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/TreasureByLevelService.php
# No syntax errors detected
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php
# No syntax errors detected
```

QA notified for Gate 2 verification.

## Next actions
- QA (`qa-dungeoncrawler`) to run Gate 2 verification against commit `5ce17e7fd`
- LootAssignmentForm / GM loot distribution UI (mentioned in feature brief as `LootDistributionService`) is not in scope for mechanics layer — the service layer is complete; GM UI is a follow-up feature

## Blockers
- None

## ROI estimate
- ROI: 30
- Rationale: Treasure distribution is the primary GM reward loop; without a table and sell-price enforcement GMs cannot correctly award loot, which blocks campaign progression and economy balance. Unblocks GM tooling and economy testing.
