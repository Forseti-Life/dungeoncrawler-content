# Outbox: dc-cr-economy

- Status: done
- Summary: Implemented the full PF2e economy, services, and currency data layer for dc-cr-economy. Added `CURRENCY_EXCHANGE_RATES` constant and `convertCurrency()` public static method to CharacterManager (TC-ECO-01). Added `HIRELINGS` catalog with unskilled/skilled base rates and `hirelingDailyCost()` supporting in-danger doubling (TC-ECO-06/07). Added `SPELLCASTING_SERVICES` catalog with per-level table prices and `spellcastingServiceCost()` with uncommon and long-cast surcharges (TC-ECO-08). Added `SUBSIST_ACTION` constant with Survival/Society check result structure (TC-ECO-09). Added `ANIMAL_CATALOG` (Table 6-17) with price, rental_per_day, and combat_trained flag plus `ANIMAL_COMBAT_PANIC` constant (frightened 4 + fleeing) for TC-ECO-10/11/12. Added `BARDING_CATALOG` with four barding types, `armor_type`, `rune_slots=0`, and size-based price scaling plus `bardingPrice()` helper (TC-ECO-13). In InventoryManagementService, added `calculateCoinBulk()` static (floor division, TC-ECO-05), extended `sellItem()` with phase gate returning `downtime_only` for encounter/exploration (TC-ECO-14), and added `purchaseItem()` enforcing price "—" blocking (not_for_sale), free items at price 0, phase restriction, and server-side currency deduction with balance check (TC-ECO-02/03/04). Starting wealth 15 gp was already present in STARTING_EQUIPMENT and buildCharacterJson. PHP lint passes; site HTTP 200.

## Next actions
- None for this feature. Awaiting next dispatch.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 7
- Rationale: Economy foundation (currency conversion, bulk, purchase/sell phase gates, hireling and animal catalogs) unblocks all downstream downtime and inventory features. Covers all 14 TC-ECO test cases enabling QA Gate 2.

## Commits
- forseti.life: `288f96586` — dc-cr-economy: implement PF2e currency, bulk, hirelings, animals, barding, services
