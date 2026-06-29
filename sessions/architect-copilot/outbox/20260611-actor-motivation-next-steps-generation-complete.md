- Status: done
- Summary: Completed actor motivation follow-on by enforcing a canonical server-side motivation→intent contract, deterministic multi-action NPC turn planning, and machine-readable decision metadata across encounter outputs; added focused regressions for de-escalation, self-preservation, weakest-target continuity, and AI tactical-intent metadata propagation.

## What changed
- Implemented canonical intent contract + planner in `EncounterPhaseHandler`:
  - `buildNpcTacticalIntentContract(...)`
  - `buildNpcTurnPlan(...)`
  - `resolveNpcIntentActionType(...)`
  - `resolveNpcIntentTarget(...)`
- Updated `autoPlayNpcTurn(...)` to execute deterministic step plans for remaining actions (action 1/2/3 continuity under a single intent contract).
- Added encounter decision telemetry:
  - `decision_reason` and `decision_basis` on `npc_strike`, `npc_stride`, `npc_interact`, `npc_talk`, `npc_choose_not_to_act`.
  - Same metadata contract on room-scene pass turns.
- Propagated tactical intent through AI provider boundary:
  - `current_actor_tactical_intent` prompt context in `AiConversationEncounterAiProvider`.
  - recommendation schema normalization now includes `decision_reason` / `decision_basis`.
  - deterministic stub provider now returns the same decision metadata fields.
- Documented the contract in `dungeoncrawler-content/ARCHITECTURE.md`.

## Verification
- Passed focused motivation/intent and provider unit coverage:
  - `EncounterPhaseHandlerTest` (motivation-focused subset)
  - `AiConversationEncounterAiProviderTest` (full file)
- Broader two-file run still shows pre-existing unrelated failures in legacy `EncounterPhaseHandlerTest` cases:
  - `testGetAvailableActionsIncludesMinorColorShiftForChameleonGnome`
  - `testGetAvailableActionsDefaultsToCurrentTurnWhenActorIdMissing`
  - `testProcessIntentMinorColorShiftUpdatesColoration`
  - `testOnEnterAutoPlaysInitialNonPlayerTurn`

## Next actions
- If desired, open a separate hardening item to reconcile the four pre-existing legacy `EncounterPhaseHandlerTest` expectation mismatches.

## Blockers
- None for this work item.
