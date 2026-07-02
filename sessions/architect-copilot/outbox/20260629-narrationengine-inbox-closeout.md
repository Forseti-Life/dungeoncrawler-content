# Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-narrationengine` with contract-focused decomposition planning and an implemented narrator role-normalization refactor increment.

## Delivered
- Audited `src/Service/NarrationEngine.php` and documented decomposition boundaries for:
  1. room-event queueing and projection seams,
  2. role/scope normalization seams,
  3. immediate/batch narration + perception seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `eventContentLooksLikeDialogue(...)`,
  - rewired `normalizeEventRoleScope(...)` narrator speech detection to consume the shared helper seam.
- Added targeted unit coverage in `NarrationEngineWiringTest` for narrator speech-to-GM remap and narrator-event retention behavior.
- Pushed implementation commit in `dungeoncrawler-content`: `aef344b08d`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-npcsheetgenerationservice`.
