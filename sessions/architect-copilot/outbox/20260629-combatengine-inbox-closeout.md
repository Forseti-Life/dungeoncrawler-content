- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-combatengine` with contract-focused decomposition planning and an implemented participant entity-ref decode refactor increment.

## Delivered
- Audited `src/Service/CombatEngine.php` and documented decomposition boundaries for:
  1. encounter lifecycle and turn-transition seams,
  2. attack/detection computation seams,
  3. participant entity-ref decode/persistence seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `decodeParticipantEntityRef(...)`,
  - rewired attack and detection paths (`resolveAttack`, `getDetectionState`, `setDetectionState`) to reuse the shared decode seam.
- Added targeted unit coverage in `CombatEngineTest` for:
  - valid decode behavior,
  - empty/unset decode behavior,
  - invalid JSON decode behavior.
- Pushed implementation commit in `dungeoncrawler-content`: `5c415129e5`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-contentregistry`.
