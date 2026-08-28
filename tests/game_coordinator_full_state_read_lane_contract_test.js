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
  coordinatorSource.includes('public function getAuthoritativeLaunchState(int $campaign_id, ?string $actor_id = NULL, ?int $character_id = NULL): array'),
  'authoritative launch-state entrypoint exists for gameplay bootstrap callers'
);
assert(
  coordinatorSource.includes('return $this->buildFullStateResponse(')
    && coordinatorSource.includes('TRUE,')
    && coordinatorSource.includes('$resolved_actor_id !== \'\' ? $resolved_actor_id : NULL'),
  'authoritative launch-state path allows room-entry materialization when GET gameplay state is requested'
);
assert(
  coordinatorSource.includes('if (!$this->hasAuthoritativeLaunchState($dungeon_data, $game_state)) {')
    && coordinatorSource.includes("return $this->errorResponse('Failed to materialize authoritative launch state.', $game_state);"),
  'authoritative launch-state path fails closed when launch materialization still has not completed'
);
assert(
  coordinatorSource.includes('public function getMaterializedFullState(int $campaign_id, ?string $actor_id = NULL, ?int $character_id = NULL): array'),
  'read-only compatibility entrypoint still exists for scoped bootstrap/runtime callers'
);
assert(
  coordinatorSource.includes('protected function prepareScopedFullStateContext(int $campaign_id, ?string $actor_id = NULL, ?int $character_id = NULL): ?array'),
  'scoped full-state preparation is shared between launch and read-only coordinator paths'
);
assert(
  coordinatorSource.includes('protected function hasAuthoritativeLaunchState(array &$dungeon_data, array $game_state): bool'),
  'authoritative launch-state invariant helper exists'
);
assert(
  coordinatorSource.includes('FALSE,')
    && coordinatorSource.includes('mutation work are handled by explicit launch/write lanes, not this reader.'),
  'read-only compatibility path remains side-effect-free'
);
assert(
  controllerSource.includes('getAuthoritativeLaunchState('),
  'controller state endpoint uses the authoritative launch-state entrypoint'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
