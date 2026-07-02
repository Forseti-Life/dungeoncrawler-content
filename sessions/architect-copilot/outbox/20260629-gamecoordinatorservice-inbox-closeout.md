- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-gamecoordinatorservice` with contract-focused decomposition planning and an implemented action-availability refactor increment.

## Delivered
- Audited `src/Service/GameCoordinatorService.php` and documented decomposition boundaries for:
  1. coordinator orchestration/state transitions,
  2. actor-scoped action-availability resolution,
  3. controller-facing contract/payload shaping.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `resolveActionAvailabilityContext(...)`,
  - extracted `emptyActionAvailabilityPayload(...)`,
  - rewired `getAvailableActionsForActor(...)` and `getActionAvailabilityForActor(...)` to consume shared helpers.
- Added targeted unit coverage in `GameCoordinatorServiceTest` for:
  - shared action-availability context resolution,
  - empty action-availability payload shape contract.
- Pushed implementation commit in `dungeoncrawler-content`: `8aec9e6b26`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-gameplayactionprocessor`.
