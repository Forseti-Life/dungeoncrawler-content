/**
 * @file
 * Contract test: broken encounter-phase shells must repair instead of persisting.
 *
 * Run with:
 *   node tests/encounter_phase_shell_repair_contract_test.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const coordinatorSource = fs.readFileSync(
    path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
    'utf8',
  );
  const bootstrapSource = fs.readFileSync(
    path.resolve(__dirname, '../src/Service/RuntimeBootstrapService.php'),
    'utf8',
  );
  const transitionSource = fs.readFileSync(
    path.resolve(__dirname, '../src/Service/EncounterNavigationTransitionCoordinatorTrait.php'),
    'utf8',
  );

  assert(
    coordinatorSource.includes('protected function hasBrokenEncounterPhaseShell(array $game_state, string $room_id): bool')
      && coordinatorSource.includes('Repairing broken encounter-phase shell before room bootstrap')
      && coordinatorSource.includes('protected function clearBrokenEncounterPhaseShell(array &$game_state, string $room_id): void')
      && coordinatorSource.includes('if ($event_log_cursor > 0 && !$repair_broken_encounter_shell')
      && coordinatorSource.includes('if ($latest_event_cursor > 0 && !$repair_broken_encounter_shell'),
    'GameCoordinator should detect broken encounter-phase shells and bypass cursor short-circuits during repair bootstrap',
  );

  assert(
    bootstrapSource.includes('if ($this->hasBrokenEncounterPhaseShell($state, $active_room_id)) {')
      && bootstrapSource.includes('Runtime bootstrap detected broken encounter-phase shell')
      && bootstrapSource.includes("$state['initial_room_entry_room_id'] = NULL;")
      && bootstrapSource.includes("$state['initial_room_entry_completed_at'] = NULL;")
      && bootstrapSource.includes('protected function hasBrokenEncounterPhaseShell(array $state, string $active_room_id): bool'),
    'Runtime bootstrap should surface broken encounter-phase shells for follow-up repair',
  );

  assert(
    transitionSource.includes('$lifecycle_snapshot = $this->captureEncounterLifecycleSnapshot($game_state);')
      && transitionSource.includes("throw new \\RuntimeException('Combat engine did not return an encounter id.');")
      && transitionSource.includes("if (($start_result['status'] ?? 'error') !== 'ok' || !is_array($start_result['encounter'] ?? NULL)) {")
      && transitionSource.includes('$this->restoreEncounterLifecycleSnapshot($game_state, $lifecycle_snapshot);')
      && transitionSource.includes('protected function captureEncounterLifecycleSnapshot(array $game_state): array')
      && transitionSource.includes('protected function restoreEncounterLifecycleSnapshot(array &$game_state, array $snapshot): void'),
    'Hostile combat startup should restore prior lifecycle state when encounter creation/start fails',
  );

  console.log('OK encounter phase shell repair contract');
})();
