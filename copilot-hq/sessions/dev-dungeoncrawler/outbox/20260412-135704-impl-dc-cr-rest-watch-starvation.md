Implementation is done (commit `48475f2f8`), QA ran Gate 2 and APPROVED (`657bb23d5`).

- Status: done
- Summary: Audited dc-cr-rest-watch-starvation against all ACs. All acceptance criteria were implemented in a prior session: `DowntimePhaseHandler` gained `assign_watch` intent (party-size-based round-robin watch rotation, stored in `game_state['rest']`) and `advance_starvation` intent (per-entity `days_without_food`/`days_without_water` tracking, fatigue applied on day 1, damage onset after `max(1, con_mod+1)` days, 1d4/day for dehydration and 1/day for starvation, both tracks independent); `ExplorationPhaseHandler::processTreatWounds` and `processLongRest` both block healing when target is in an active starvation/thirst damage phase; `NumberGenerationService` injected into `DowntimePhaseHandler` via constructor and services.yml. PHP lint clean, drush cr passed. Committed `48475f2f8`. QA Gate 2 APPROVED at `657bb23d5` (all 15 TCs pass).

## Next actions
- No dev work remaining; Gate 2 is APPROVED.
- Await PM close-out for this feature slot.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 18
- Rationale: Self-contained survival subsystem; healing-block enforcement prevents downstream QA failures. Fully verified and approved.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260412-135704-impl-dc-cr-rest-watch-starvation
- Generated: 2026-04-12T15:23:43+00:00
