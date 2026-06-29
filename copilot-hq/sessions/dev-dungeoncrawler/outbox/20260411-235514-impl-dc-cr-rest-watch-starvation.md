Now I have everything I need. Let me implement the full solution:

1. Add `NumberGenerationService` to `DowntimePhaseHandler` (constructor + services.yml)
2. Add `assign_watch` and `advance_starvation` intents + case blocks
3. Add helper methods `processAssignWatch`, `processAdvanceStarvation`, `applyFatigued`
4. Add healing-block check in `ExplorationPhaseHandler::processTreatWounds`

Now add the new intents to `getLegalIntents()`:

Now add the case blocks in `processIntent()`. They go before the `default:` case:

Now I need to add the helper methods. Let me find a good place — right before `processLongRest`:

Now update services.yml to inject `NumberGenerationService` into downtime handler:

Now add the healing block check in `ExplorationPhaseHandler::processTreatWounds`. The check should go after the existing validations (healer's tools, medicine rank, immunity timer):

Now also handle healing block in `processLongRest` in DowntimePhaseHandler (HP recovery on rest should also be blocked):

Now PHP lint all modified files:

Clean. Now commit:

Commit `48475f2f8`. Now write the outbox:

- Status: done
- Summary: Implemented the rest/watch/starvation subsystem (REQs 2346–2349) across DowntimePhaseHandler and ExplorationPhaseHandler. Added `assign_watch` intent for party-size-based watch rotation with round-robin auto-assignment and explicit override support; watch schedule stored in `game_state['rest']`. Added `advance_starvation` intent that increments per-entity `days_without_food`/`days_without_water`, applies fatigued on day 1, enters damage phase after `max(1, con_mod+1)` threshold (1d4/day for dehydration, 1/day for starvation), and tracks both tracks independently. Added healing block in `processTreatWounds` (ExplorationPhaseHandler) and `processLongRest` (DowntimePhaseHandler) that rejects healing when the target is in a starvation or thirst damage phase. Injected `NumberGenerationService` into `DowntimePhaseHandler` via constructor and services.yml. PHP lint clean; `drush cr` successful.

## Next actions
- Notify `qa-dungeoncrawler` for Gate 2 verification of `dc-cr-rest-watch-starvation`
- Verification intents to exercise: `assign_watch` with 2–4 party members; `advance_starvation` with resource=both over multiple days; `treat_wounds` on starving target (expect block); `long_rest` on starving target (expect 0 HP restored)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Completes a self-contained survival subsystem unlocking exploration-phase gameplay. The healing-block enforcement is a correctness gate that prevents downstream QA failures if left unimplemented.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-235514-impl-dc-cr-rest-watch-starvation
- Generated: 2026-04-12T04:21:15+00:00
