- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-contentregistry` with contract-focused decomposition planning and an implemented ID-alias normalization refactor increment.

## Delivered
- Audited `src/Service/ContentRegistry.php` and documented decomposition boundaries for:
  1. import/source-filter orchestration seams,
  2. per-content-type normalization/validation seams,
  3. persistence/update contract seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `normalizeContentIdAliases(...)`,
  - rewired spell and feat branches of `normalizeContentData(...)` to reuse the shared alias-normalization seam.
- Added targeted unit coverage in `ContentRegistryTest` for:
  - configured-field alias normalization behavior,
  - empty/non-string alias pass-through behavior.
- Pushed implementation commit in `dungeoncrawler-content`: `0cbed66b05`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-contentseederservice`.
