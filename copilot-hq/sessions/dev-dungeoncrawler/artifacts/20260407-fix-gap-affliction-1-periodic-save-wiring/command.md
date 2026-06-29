# Fix: GAP-AFFLICTION-1 — Wire `processPeriodicSave()` into CombatEngine end-of-turn

- Release: 20260406-dungeoncrawler-release-b
- Priority: MEDIUM — required before release signoff (Gate 1b)
- Dispatched by: pm-dungeoncrawler
- Source: qa-dungeoncrawler QA outbox `20260406-unit-test-20260406-impl-afflictions`

## Gap

`CombatEngine::processEndOfTurnEffects()` never calls `AfflictionManager::processPeriodicSave()`. This means afflictions with per-turn saves (poison, disease, etc.) never trigger their save roll at end of turn — the affliction system is permanently incomplete at the combat integration layer.

Also fix DEF-AFFLICTION-2 (LOW) in the same PR: `handleReExposure()` uses undefined `$encounter_id` variable (defaults to 0 silently). Add `$encounter_id` as a parameter to `handleReExposure()` and pass it from all call sites.

## Required fix

1. In `CombatEngine::processEndOfTurnEffects()`: add call to `$this->afflictionManager->processPeriodicSave($character, $encounter_id)` for each character in the active encounter.
2. In `AfflictionManager::handleReExposure()`: add `int $encounter_id` parameter; update all callers.

## Acceptance criteria

1. `CombatEngine::processEndOfTurnEffects()` calls `processPeriodicSave()` for each combatant.
2. Live drush: simulate end-of-turn for a character with an active affliction; verify save roll fires and affliction stage advances or resolves.
3. `handleReExposure()` receives `$encounter_id` correctly; no undefined variable warnings.
4. No regression on existing affliction apply/remove tests.

## Verification

```bash
drush php-eval "
  \$ce = \Drupal::service('dungeoncrawler_combat.combat_engine');
  \$result = \$ce->processEndOfTurnEffects(['char_id' => 1, 'encounter_id' => 99]);
  print_r(\$result);
"
```
Must include `periodic_save_results` key in response with at least one save attempt if character has active afflictions.

## Deliverables

- Commit hash(es)
- Rollback: both changes are additive and can be reverted cleanly
