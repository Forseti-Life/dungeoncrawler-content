# dev-dungeoncrawler outbox — 20260410-implement-dc-apg-equipment

- Status: done
- Summary: Implemented APG equipment catalog extension (dc-apg-equipment). The APG items were already populated in the CATALOG constant but used a `'source'` key instead of the required `'source_book'` discriminator. Renamed all 35 APG items' field to `'source_book' => 'apg'`, added `VALID_BOOKS` constant (`['crb', 'apg', 'gmg', 'all']`), and added `getBySourceBook()` and `getByCriteria()` methods to `EquipmentCatalogService`. Updated `EquipmentCatalogController::catalog()` to accept `?source_book=` query parameter with validation. CRB items are unchanged (source_book implicitly `crb` when absent). All API verifications pass: `source_book=apg` returns 35 items, `source_book=crb` returns 33 items, `source_book=all` returns 68, combined `type=weapon&source_book=apg` returns the 3 APG weapons, invalid source_book returns 400, and existing unfiltered endpoint is backward compatible. Committed at `b1d931906`.

## Next actions
- None — awaiting QA Gate 2 (TC-EQP-01 through TC-EQP-16)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 30
- Rationale: APG equipment extends character build options across all classes; required for APG character options to be usable in the equipment selector.
