# Status

- status: done
- created_at: 2026-06-08T19:05:50+00:00
- current_phase: completed

## Notes

### 2026-06-11 — Psychology subsystem review/refactor pass
- Reviewed and refactored actor psychology tactical-contract flow for maintainability and safety:
  - Added deterministic axis normalization in `EncounterPhaseHandler` (`normalizeDecisionPersonalityAxes`) so tactical intent and context do not drift on malformed stored profile values.
  - Added `hasAdjacentAlivePlayer(...)` helper to centralize adjacency detection and remove duplicated tactical-state branching.
  - Hardened turn-plan generation so target-required actions (`strike`/`talk`) never emit targetless plan steps (fallback to stride for self-preserve, otherwise end-turn).
  - Tightened contract branching to avoid treasure-seek defaults when a combatant has no psychology profile (`profile_present` gate).
  - Normalized attitude + personality-axis hydration in `buildNpcDecisionProfile(...)` and `buildNpcPsychologyContext(...)` for a consistent actor-psychology surface.
- Added focused refactor regressions in `EncounterPhaseHandlerTest`:
  - malformed axis clamp behavior,
  - no-profile aggressive baseline behavior.
- Verification:
  - focused encounter psychology subset: pass,
  - AI provider suite: pass,
  - broader two-file run still shows the same 4 pre-existing unrelated legacy failures (color-shift/reaction/startup-event expectations).

### 2026-06-11 — Completion pass (architect-copilot)
- Finalized canonical motivation→intent contract wiring in `dungeoncrawler-content`:
  - `EncounterPhaseHandler` now enforces `buildNpcTacticalIntentContract(...)` + `buildNpcTurnPlan(...)` for deterministic multi-action NPC turn execution.
  - Fallback action selection now resolves through the same intent contract path (no divergent ad-hoc branch).
- Added machine-readable decision telemetry on encounter outputs:
  - `decision_reason` + `decision_basis` now emitted on `npc_strike`, `npc_stride`, `npc_interact`, `npc_talk`, and `npc_choose_not_to_act`.
  - Room-scene pass turns now emit the same decision metadata shape.
- Extended AI provider contract propagation:
  - `current_actor_tactical_intent` is included in recommendation prompts.
  - Recommendation schema + normalization now include `decision_reason` and `decision_basis`.
  - Stub provider now returns deterministic decision metadata parity.
- Added and passed focused regression coverage:
  - de-escalation continuity across action 1/2/3,
  - self-preservation continuity across action 1/2/3,
  - high-cunning weakest-target continuity,
  - encounter autoplay decision metadata emission,
  - AI provider tactical-intent prompt injection + decision metadata normalization.
- Verification notes:
  - Focused motivation/intent and provider suites passed.
  - Wider two-file run still reports pre-existing unrelated failures in legacy `EncounterPhaseHandlerTest` cases around color-shift/reaction/startup event expectations.

### 2026-06-09 — Motivation→intent contract + multi-action turn planning
- Implemented a canonical NPC tactical-intent contract in `EncounterPhaseHandler`:
  - `buildNpcTacticalIntentContract(...)` now derives explicit intents (`deescalate`, `self_preserve`, `finish_weakest`, `treasure_seek`, `aggressive_engage`, etc.) from motivation/attitude/goals/personality axes + tactical state.
- Extended fallback logic from single-action picks to deterministic **multi-action turn plans**:
  - `buildNpcTurnPlan(...)` and `resolveNpcIntentActionType(...)` now generate step-wise action plans for remaining actions while preserving a single intent across action 1/2/3.
  - `autoPlayNpcTurn(...)` now executes that plan (with optional AI first-step seeding) instead of one-off action execution.
- Added explicit turn telemetry fields for explainability:
  - `decision_reason` and `decision_basis` are now attached to NPC action events (`npc_strike`, `npc_stride`, `npc_interact`, `npc_talk`) and choose-not-to-act outputs (encounter + room-scene pass turns).
- Propagated tactical intent context into AI provider prompts and normalized recommendation envelopes:
  - Added `current_actor_tactical_intent` prompt context plus `decision_reason`/`decision_basis` schema fields in `AiConversationEncounterAiProvider`.
  - Stub provider now returns `decision_reason` + `decision_basis` for deterministic parity.
- Added focused unit coverage in `EncounterPhaseHandlerTest` for:
  - de-escalation plan continuity across actions 1/2/3,
  - self-preservation retreat/reposition continuity,
  - high-cunning weakest-target continuity,
  - room-scene choose-not-to-act decision telemetry.
- Added provider test assertions in `AiConversationEncounterAiProviderTest` for tactical-intent prompt injection and decision metadata normalization.

Completed actor motivation/actions contract hardening in `dungeoncrawler-content`:

- commit: `7c0a54d`
- pushed: `main`
- results:
  - all `dc_npc_psychology` rows now persist `personality_axes.motivation` and `character_sheet.goals` (including required defaults `Gain XP`, `Gain Treasure`)
  - all `dc_campaign_characters` rows now persist canonical `actions.threeActionEconomy` and `goals` defaults
  - Gribbles character library asset now explicitly includes motivations/goals/actions contract fields
  - targeted tests updated and passing (`CharacterManagerCanonicalizationTest`, `npc_psychology_test.php`)

Additional architecture alignment completed:

- commit: `32729be`
- added authoritative `content_generation` subsystem definition in `ARCHITECTURE.md`
- documented dynamic campaign object inventory and ownership mapping across generation services.
