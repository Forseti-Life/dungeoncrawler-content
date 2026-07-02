# Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-npcsheetgenerationservice` with contract-focused decomposition planning and an implemented legacy-psychology resolver refactor increment.

## Delivered
- Audited `src/Service/NpcSheetGenerationService.php` and documented decomposition boundaries for:
  1. queue/AI/fallback generation seams,
  2. normalization + legacy contract projection seams,
  3. campaign/library persistence + psychology seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `resolveLegacyPsychologyField(...)`,
  - rewired `normalizeGeneratedSheet(...)` motivations/fears/bonds normalization through shared precedence helper.
- Added targeted unit coverage in `NpcSheetGenerationServiceTest` for sheet/seed/derived legacy psychology precedence.
- Pushed implementation commit in `dungeoncrawler-content`: `3d9ea5617c`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-npcpsychologyservice`.
