# Dungeoncrawler — Canonicalize food/water starvation-thirst resource authority

Date: 2026-06-06
Owner seat: ceo-copilot-2
Priority: Medium

## Context
Food/water handling is currently split:
- Consumable use updates canonical character state through `CharacterStateService::applyConsumableEffects()`.
- Starvation/thirst progression in downtime mutates dungeon entity runtime state (`days_without_food/water`, damage flags).

Canonical campaign character sheets should be the single authority for actor resource state.

## Requested review scope
1. Audit all food/water state transitions (consume, day advance, damage phase, recovery).
2. Consolidate starvation/thirst counters and damage-phase flags into canonical character-sheet resource/state structures.
3. Treat dungeon/runtime entity state as projection only, or remove duplicate fields where possible.
4. Add regression tests for multi-day progression and consume/recover transitions.

## Acceptance criteria
- Food/water survival counters and phase flags are authoritative in canonical character data.
- Downtime progression and consumable usage both read/write the same canonical state.
- Runtime projections remain synchronized and cannot drift.
- Regression tests cover day advancement, threshold transitions, and reset/recovery paths.

## Progress update (2026-06-12, phase 1)
- Status: in_progress
- Implementation:
  - Added canonical survival resource contract at `resources.survival` in `CharacterStateService`.
  - Rewired downtime starvation/thirst day advancement to canonical-first reads/writes with explicit projection sync.
  - Updated downtime/exploration healing gates to prioritize canonical starvation/thirst phase flags.
  - Added encounter consume-item canonical survival projection sync for nutrition/hydration consumables.
- Test coverage:
  - Added regression tests for canonical day advancement behavior, canonical-identity-required failure path, threshold transition damage, and consume-item reset/projection sync.
- Notes:
  - Full `EncounterPhaseHandlerTest` still has unrelated pre-existing failure (`encounter_started` vs `round_start`); focused encounter coverage for canonical spell/consume paths passes.

## Progress update (2026-06-12, phase 2 review/refactor)
- Status: in_progress
- Refactor adjustments:
  - Removed remaining canonical survival read fallbacks to legacy top-level mirror keys in:
    - `CharacterStateService::normalizeSurvivalResourceState()`
    - `DowntimePhaseHandler::readCanonicalSurvivalState()`
    - `ExplorationPhaseHandler::readCanonicalSurvivalState()`
    - `EncounterPhaseHandler::readCanonicalSurvivalStateFromCanonicalState()`
  - Canonical survival now reads strictly from `resources.survival`; legacy mirrors are treated as non-authoritative projection compatibility fields only.
- Regression coverage:
  - Added `CharacterStateServiceTest::testGetStateIgnoresLegacySurvivalMirrorFields`.
  - Added `DowntimePhaseHandlerTest::testAdvanceStarvationIgnoresLegacyCanonicalMirrorFields`.
- Verification:
  - Targeted suites for CharacterState, Downtime, Exploration (cast flow), and Encounter (cast + consume_item projection sync) passed.
