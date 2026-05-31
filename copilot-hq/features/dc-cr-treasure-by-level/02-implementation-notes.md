Implemented the remaining roadmap-visible rules for `dc-cr-treasure-by-level`.

Changes made:
- Extended `TreasureByLevelService` with explicit currency-column metadata via `getCurrencyComposition()`.
- Added `sellItem()` to enforce:
  - standard items sell at half price
  - gems, art objects, and raw materials sell at full price
  - invalid requested sale values are blocked with a corrected value
- Added `validateTradePhase()` so trading outside downtime is soft-flagged with GM override availability instead of silently succeeding.
- Exposed `currency_includes` from `getLevelBudget()` so treasure-table consumers can see what the currency column represents.
- Added unit coverage for treasure table completeness, party-size adjustments, sale pricing, downtime-only trade enforcement, and starting wealth by level.
