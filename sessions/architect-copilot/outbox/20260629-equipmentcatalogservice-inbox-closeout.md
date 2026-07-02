- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-equipmentcatalogservice` with contract-focused decomposition planning and an implemented source-matching refactor increment.

## Delivered
- Audited `src/Service/EquipmentCatalogService.php` and documented decomposition boundaries for:
  1. catalog filter predicate seams,
  2. criteria composition/filtering seams,
  3. item lookup/stat accessor seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `itemMatchesSourceBook(...)`,
  - rewired `getBySourceBook(...)` and `getByCriteria(...)` to reuse shared source-book matching logic.
- Added targeted unit coverage in `EquipmentCatalogServiceTest` for:
  - missing-source default-to-CRB behavior,
  - parity between source-only and criteria source filters,
  - type filtering preservation when `source_book=all`.
- Pushed implementation commit in `dungeoncrawler-content`: `e1f71672f8`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-explorationphasehandler`.
