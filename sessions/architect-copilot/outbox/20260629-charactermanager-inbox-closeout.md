- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-charactermanager` with contract-focused decomposition planning and an implemented shared spell-ID normalization refactor increment.

## Delivered
- Audited `src/Service/CharacterManager.php` and documented decomposition boundaries for:
  1. canonicalization and payload projection seams,
  2. compatibility mirror synchronization seams,
  3. spell/feature helper normalization seams,
  4. persistence/helper orchestration boundaries.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `normalizeSelectedSpellIds(...)`,
  - rewired canonical and legacy spell selection helper methods to use the shared normalizer.
- Added targeted unit coverage in `CharacterManagerCanonicalizationTest` for:
  - filtering empty and non-string spell selection IDs.
- Pushed implementation commit in `dungeoncrawler-content`: `bca34be210`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-characterstateservice`.
