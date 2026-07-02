# Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-mapgeneratorservice` with contract-focused decomposition planning and an implemented generated-contract normalization refactor increment.

## Delivered
- Audited `src/Service/MapGeneratorService.php` and documented decomposition boundaries for:
  1. library/template matching and campaign room reuse seams,
  2. AI setting generation and normalization seams,
  3. room/entity persistence and NPC registry seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `normalizeGeneratedNpcContract(...)`,
  - extracted `normalizeGeneratedObjectContract(...)`,
  - rewired `normalizeSetting(...)` NPC/object branches to consume shared helper seams.
- Added targeted unit coverage in `MapGeneratorServiceDeterminismTest` for:
  - generated NPC helper canonical defaults + deterministic dedupe IDs,
  - generated object helper canonical defaults + deterministic dedupe IDs.
- Pushed implementation commit in `dungeoncrawler-content`: `22182e7366`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-mapvisualstateprojector`.
