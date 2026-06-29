Good. Now I have a full picture. Let me implement all the changes. Starting with the two files:

**1. DowntimePhaseHandler.php** - Fix processLongRest HP formula (REQ 2301), add downtime_rest (REQ 2306), implement retrain/advance_day (REQs 2307-2310)

**2. ExplorationPhaseHandler.php** - Add `calculateTravelSpeed()`, mechanical `set_activity` handling, `daily_prepare` intent (REQs 2290-2305)

Now update DowntimePhaseHandler processIntent to use the new methods:

Also add `downtime_rest` to getLegalIntents:

Now update ExplorationPhaseHandler. First add the new activity types to getLegalIntents and add `calculateTravelSpeed()` + `daily_prepare` intent:

Now add `set_activity` with validated exploration activities and `daily_prepare` to the switch in processIntent. The current `set_activity` case is at line 311. Let me update it and add `daily_prepare` after `cast_spell`:

Now add `daily_prepare` before the default case. Let me view the area around the end of the cast_spell case:

Now add `calculateTravelSpeed()` and `processDailyPrepare()` to ExplorationPhaseHandler before the helper methods section. Let me add them near the other protected helpers:

Now check for syntax errors and verify with drush:

All tests pass. Now mark the requirements as implemented and commit:

Done. Here is the outbox update:

---

- Status: done
- Summary: Implemented all 20 exploration and downtime activity requirements (2290–2310). ExplorationPhaseHandler gains validated `set_activity` (rejects unknown activities), `daily_prepare` intent with 1-hour time cost, and public `calculateTravelSpeed()` with terrain multipliers and hustle doubling. DowntimePhaseHandler fixes the long-rest HP formula from full-restore to Con mod × level (REQ 2301), adds sleeping-in-armor fatigue check (REQ 2302), sleep-deprivation state reset (REQ 2303), `downtime_rest` action with Con mod × 2 × level HP (REQ 2306), and functional `retrain`/`advance_day` with prohibited-type enforcement and 7/30-day duration tracking (REQs 2307–2310). All 20 reqs marked implemented in dc_requirements. Commit: `521d96119`.

## Next actions
- Await next dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Completes the exploration/downtime layer of the PF2e rules engine, enabling rest and retraining mechanics that are foundational to the campaign loop. 20 requirements closed in one pass.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-exploration-downtime-activities
- Generated: 2026-04-06T22:03:29+00:00
