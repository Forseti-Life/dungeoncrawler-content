- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-downtimephasehandler` with contract-focused decomposition planning and an implemented subsist result-shaping refactor increment.

## Delivered
- Audited `src/Service/DowntimePhaseHandler.php` and documented decomposition boundaries for:
  1. downtime action routing/rules seams,
  2. action result-shaping seams,
  3. state mutation/persistence seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `buildSubsistResult(...)`,
  - rewired all `processSubsist(...)` degree branches to use the shared result-shaping seam.
- Added targeted unit coverage in `DowntimePhaseHandlerTest` for:
  - critical-failure fatigue flag and crit-fail day tracking.
- Pushed implementation commit in `dungeoncrawler-content`: `c9b32d0606`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-encounterphasehandler`.
