# Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-merchanttransactionservice` with contract-focused decomposition planning and an implemented merchant-search dedupe refactor increment.

## Delivered
- Audited `src/Service/MerchantTransactionService.php` and documented decomposition boundaries for:
  1. panel/chat transaction orchestration seams,
  2. merchant stock/profile/search seams,
  3. inventory sellable shaping and pricing/message seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `appendUniqueMerchantSearchResult(...)`,
  - rewired `searchMerchantCatalog(...)` explicit/catalog loops to consume shared dedupe helper.
- Added targeted unit coverage in `MerchantTransactionServiceTest` for first-hit dedupe semantics and inclusion of item-id-less results.
- Pushed implementation commit in `dungeoncrawler-content`: `c8d7a18b2c`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-narrationengine`.
