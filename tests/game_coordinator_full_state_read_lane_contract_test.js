/**
 * @file
 * Contract test: GameCoordinator full-state read lane split.
 *
 * Run with:
 *   node tests/game_coordinator_full_state_read_lane_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);
const controllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/GameCoordinatorController.php'),
  'utf8'
);

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${message}`);
  } else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

console.log('\n=== GameCoordinator full-state read lane contract ===');

assert(
  coordinatorSource.includes('return $this->buildFullStateResponse($campaign_id, $dungeon_data, $game_state, FALSE);'),
  'getFullState uses the side-effect-free full-state response path'
);
assert(
  coordinatorSource.includes('$this->coordinatorRuntimeReadService->resolveFullStateReadContext($campaign_id);'),
  'full-state read paths resolve context through coordinator runtime-read service'
);
assert(
  coordinatorSource.includes('public function getMaterializedFullState(int $campaign_id, ?string $actor_id = NULL, ?int $character_id = NULL): array'),
  'materialized full-state entrypoint exists for bootstrap-compatible callers with actor/character scope'
);
assert(
  coordinatorSource.includes('return $this->buildFullStateResponse($campaign_id, $dungeon_data, $game_state, FALSE, $actor_id !== \'\' ? $actor_id : NULL);'),
  'materialized full-state path remains read-only and does not persist/bootstrap on GET state'
);
assert(
  controllerSource.includes('getMaterializedFullState('),
  'controller state endpoint uses the materialized compatibility entrypoint'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
