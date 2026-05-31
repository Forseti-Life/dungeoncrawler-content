# Acceptance Criteria (PM-owned)
# Feature: dc-cr-encounter-rules

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/CombatEngine.php`, `CombatCalculator.php`, `CombatEncounterStore.php`, `HPManager.php`.

Coverage findings:
- Encounter lifecycle (create, start, active, end) — **Full** (`CombatEngine::createEncounter`, `startEncounter`)
- Round management and turn order — **Full** (`startRound`, `turnIndex` tracking in store)
- Initiative rolling (Perception check seeding) — **Partial** (custom_initiatives can be injected but no Perception-based auto-roll)
- Attack roll calculation (d20 + bonus vs. AC → degree of success) — **None** (no `resolveAttack()` found in CombatEngine)
- Multiple Attack Penalty (MAP) — **Full** (`CombatCalculator::calculateMultipleAttackPenalty`)
- Damage application — **Partial** (`HPManager` exists; full damage type/resistance handling unclear)
- End-of-turn condition processing — **Partial** (`ConditionManager` exists; auto-decrement on turn end not confirmed)

Feature type: **enhancement** (encounter lifecycle complete; add attack roll resolution, initiative auto-roll, damage application with types)

## Happy Path
- [ ] `[EXTEND]` `startEncounter()` can auto-roll initiative for participants who have no custom initiative supplied (Perception check = d20 + Perception modifier).
- [ ] `[EXTEND]` Participants are sorted in descending initiative order at round start; ties broken by Perception modifier, then arbitrarily.
- [ ] `[NEW]` `resolveAttack(participant_id, target_id, weapon_id, encounter_id)` rolls d20 + attack bonus, applies MAP for attack number > 1, compares vs. target AC → returns degree of success.
- [ ] `[NEW]` `applyDamage(participant_id, damage_amount, damage_type, encounter_id)` applies damage to HP, accounting for resistances/weaknesses recorded on the participant entity (if present).
- [ ] `[EXTEND]` End-of-turn processing decrements timed conditions (e.g., frightened value, duration_remaining) for the current participant and removes expired conditions.
- [ ] `[TEST-ONLY]` A complete round can be simulated: initiative → attacker turn → attack → damage → condition tick → next turn.

## Edge Cases
- [ ] `[EXTEND]` Attacker with agile weapon uses −4/−8 MAP instead of −5/−10.
- [ ] `[NEW]` Natural 20 on attack bumps degree of success one step up (per PF2E rules); natural 1 bumps one step down.
- [ ] `[EXTEND]` Participant with 0 HP receives the `dying 1` condition automatically (not just HP=0 state).
- [ ] `[EXTEND]` Participant who reaches negative HP equal to their max HP is immediately dead (no dying condition needed).

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` `resolveAttack` with invalid participant/target IDs returns a structured error (not a PHP exception propagated to API).

## Permissions / Access Control
- [ ] Anonymous user behavior: encounter state is readable by anonymous users in same game session.
- [ ] Authenticated user behavior: only the character's controlling user (or GM) may submit attack actions.
- [ ] Admin behavior: admin can force-advance turn or modify HP for moderation purposes.

## Data Integrity
- [ ] HP changes are transactional (no partial updates where participant is in inconsistent state).
- [ ] Rollback path: encounter state is stored in DB; a failed encounter can be deleted and re-created from the same campaign state.

## Knowledgebase check
- Related lessons/playbooks: `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md` referenced in CombatEngine — read before implementing resolveAttack.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| Initiative sort and auto-roll | `tests/src/Unit/Service/CombatEngineTest.php` |
| `resolveAttack` (all degrees) | `tests/src/Unit/Service/CombatEngineTest.php` (new method) |
| MAP (normal and agile) | `tests/src/Unit/Service/CombatCalculatorTest.php` |
| `applyDamage` with types | `tests/src/Unit/Service/HPManagerTest.php` (extend) |
| End-of-turn condition tick | `tests/src/Unit/Service/ConditionManagerTest.php` (extend) |
| Full-round simulation | `tests/src/Functional/CombatEncounterFlowTest.php` (new) |
