- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-controller-characterviewcontroller` with contract-focused decomposition planning plus an implemented refactor increment for `viewCharacter`.

## Delivered
- Audited `src/Controller/CharacterViewController.php` (1867 lines) and documented extraction boundaries for:
  1. request/access context resolution,
  2. state bucket normalization and view-model projection,
  3. render-model assembly,
  4. follower-view parity projection,
  5. archive/unarchive lifecycle endpoints.
- Recorded contract risks, hard-failure safeguards, and conformance gaps required for safe continued decomposition.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `resolveViewCharacterRequestContext(...)`,
  - extracted `splitCharacterStateForSheet(...)`,
  - rewired `viewCharacter` to consume extracted helpers.
- Added unit test coverage `CharacterViewControllerStateSliceTest` for state-slice normalization contracts.
- Pushed implementation commit in `dungeoncrawler-content`: `9690cd8f01`.

## Next Action
1. Proceed to next pending monolith controller item in queue (`20260626-dungeoncrawler-monolith-controller-hexmapcontroller`), unless redirected.
