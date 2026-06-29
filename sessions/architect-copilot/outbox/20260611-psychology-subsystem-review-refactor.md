- Status: done
- Summary: Completed a review/refactor pass on the actor psychology subsystem tactical path by normalizing intent inputs, tightening no-profile behavior defaults, and hardening turn-plan generation against invalid target-required actions while preserving deterministic multi-action intent continuity.

## What changed
- Refactored `EncounterPhaseHandler` psychology/tactical contract internals:
  - Added `normalizeDecisionPersonalityAxes(...)` to clamp and default personality-axis values before tactical intent resolution.
  - Added `hasAdjacentAlivePlayer(...)` to centralize adjacency checks.
  - Updated `buildNpcTacticalIntentContract(...)` to:
    - use normalized attitude + axes,
    - include `profile_present` in decision basis,
    - avoid treasure-seek defaulting when no psychology profile exists.
  - Updated `buildNpcTurnPlan(...)` to avoid target-required targetless plan steps (self-preserve falls back to stride; otherwise end-turn).
  - Normalized profile-derived attitude/axes in `buildNpcDecisionProfile(...)` and `buildNpcPsychologyContext(...)`.
- Added refactor regression coverage in `EncounterPhaseHandlerTest`:
  - axis normalization clamp behavior,
  - no-profile aggressive baseline behavior.

## Verification
- Focused encounter psychology subset: pass.
- AI provider suite: pass.
- Broader two-file run remains at the same 4 pre-existing unrelated failures in legacy `EncounterPhaseHandlerTest` (color-shift/reaction/startup-event expectation drift).

## Next actions
- If requested, separate follow-on cleanup can reconcile the 4 pre-existing legacy test expectation mismatches.

## Blockers
- None for this refactor scope.
