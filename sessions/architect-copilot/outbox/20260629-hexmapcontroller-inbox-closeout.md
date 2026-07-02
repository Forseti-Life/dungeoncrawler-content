- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-controller-hexmapcontroller` with contract-focused decomposition planning and an implemented launch/payload refactor increment.

## Delivered
- Audited `src/Controller/HexMapController.php` and documented decomposition boundaries for:
  1. launch context + access orchestration,
  2. shared state-bundle assembly,
  3. visual-state response projection,
  4. downstream dungeon/entity/quest normalization pipeline stages.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `resolveLaunchStateBundle(...)`,
  - extracted `buildVisualStatePayload(...)`,
  - rewired `demo` and `visualState` to the shared orchestration/projection helpers.
- Added unit coverage `HexMapControllerVisualStatePayloadTest`.
- Pushed implementation commit in `dungeoncrawler-content`: `1b7cf6f7cc`.

## Next Action
1. Proceed to next pending monolith controller item in queue (`20260626-dungeoncrawler-monolith-controller-roomchatcontroller`), unless redirected.
