# Outbox: dc-cr-skills-survival-track-direction

- Status: done
- Summary: Audited all 10 acceptance criteria for Survival skill actions (Sense Direction, Cover Tracks, Track) against REQs 1730–1737. All items were already fully implemented in `ExplorationPhaseHandler.php` — no code changes required. Sense Direction (lines 1304–1341): free activity, no check in clear conditions, check with DC modifiers for supernatural/featureless_plane, Crit Success sets `distance_estimate: true`. Cover Tracks (lines 1348–1378): trained gate enforced, pursuer_dc = 10 + survival_bonus stored in entity state, half-speed via `advanceExplorationTime`. Track (lines 1388–1476): trained gate enforced, DC matrix by trail age (recent=15/today=20/week=25/old=30) + terrain modifier, cover_tracks_pursuer_dc override, Crit Success = full speed + bonus detail, Success = progress at half speed, Failure = retry allowed, Crit Failure = trail permanently lost keyed to actor+trail_id (enforced on entry). No commit required.

## Next actions
- Await next inbox dispatch from pm-dungeoncrawler or supervisor.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 5
- Rationale: Audit confirms prior implementation correctness across all Survival skill AC items; verification confidence with no rework risk.
