# Status

- status: done
- created_at: 2026-06-26T14:00:34+00:00
- current_phase: completed

## Notes

Auto-created from 1000+ line hotspot scan for Dungeoncrawler monolith review/refactor queue.

### 2026-06-29 — Service decomposition audit
- Audited `src/Service/MerchantTransactionService.php` (~1374 lines) as a mixed-responsibility commerce monolith spanning:
  1. panel/chat transaction orchestration,
  2. merchant stock/profile/search resolution,
  3. inventory sellable shaping and purchase/sale messaging.
- Coupling profile:
  - `searchMerchantCatalog(...)` duplicated result de-duplication logic across explicit-stock and wider-catalog loops,
  - duplicated loop guards increased drift risk for result ordering and uniqueness semantics.

### 2026-06-29 — Contract map and drift risks
- Core service contracts identified:
  - merchant search must keep first-hit ordering while de-duplicating by non-empty `item_id`,
  - explicit merchant stock results should remain preferred when duplicate ids exist,
  - item-id-less ad-hoc results should still be included.
- Drift risks:
  1. duplicated loop code can diverge on dedupe keying and append conditions,
  2. explicit-vs-catalog precedence can drift when future ranking updates touch only one loop.

### 2026-06-29 — Phased extraction strategy
1. **Search dedupe seam**
   - extract a shared append helper for search result uniqueness by non-empty `item_id`.
2. **Callsite convergence**
   - route explicit and catalog search loops through the shared helper.
3. **Coverage lock**
   - add focused unit assertions that lock first-hit retention and non-empty-id dedupe semantics.
4. **Service thinning continuation**
   - continue extracting buy/sell orchestration seams in subsequent increments.

### 2026-06-29 — Conformance safeguards
- Preserve hard-failure/no-swallow posture.
- Preserve explicit-result precedence and existing result ordering.
- Preserve inclusion of item-id-less results.

### 2026-06-29 — Test/conformance coverage gaps
- Existing merchant search tests validated explicit-stock inclusion but did not directly lock shared dedupe semantics across append paths.

### 2026-06-29 — Implementation increment I1 (executed)
- Implemented real refactor increment in `dungeoncrawler-content`:
  - extracted `appendUniqueMerchantSearchResult(...)`,
  - rewired `searchMerchantCatalog(...)` explicit/catalog loops to consume shared dedupe helper.
- Added targeted unit coverage in `MerchantTransactionServiceTest` for:
  - first-hit retention for duplicate non-empty `item_id`,
  - continued inclusion of item-id-less results.
- Pushed in `dungeoncrawler-content` commit: `c8d7a18b2c`.

### 2026-06-29 — Completion
- Delivered decomposition map, phased extraction plan, safeguards, coverage-gap inventory, and one concrete refactor increment implementation.
- Acceptance criteria met for this inbox item.
