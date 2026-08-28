/**
 * @file
 * Contract test: coordinator runtime-read service extraction.
 *
 * Run with:
 *   node tests/game_coordinator_runtime_read_service_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);
const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);
const runtimeReadServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CoordinatorRuntimeReadService.php'),
  'utf8'
);
const gameCoordinatorServiceBlockMatch = servicesSource.match(
  /dungeoncrawler_content\.game_coordinator:\n([\s\S]*?)\n  dungeoncrawler_content\.runtime_state_read_model_assembler:/
);
const gameCoordinatorServiceBlock = gameCoordinatorServiceBlockMatch ? gameCoordinatorServiceBlockMatch[1] : '';

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

console.log('\n=== Coordinator runtime-read service contract ===');

assert(
  servicesSource.includes('dungeoncrawler_content.coordinator_runtime_read:'),
  'service container defines dungeoncrawler_content.coordinator_runtime_read'
);
assert(
  servicesSource.includes("- '@dungeoncrawler_content.coordinator_runtime_read'"),
  'game coordinator service wiring includes coordinator runtime-read dependency'
);
assert(
  gameCoordinatorServiceBlock.includes("arguments:\n      - '@database'"),
  'game coordinator service declares an explicit arguments list'
);
assert(
  gameCoordinatorServiceBlock.includes("- '@dungeoncrawler_content.coordinator_runtime_read'\n      - '@dungeoncrawler_content.runtime_state_read_model_assembler'"),
  'game coordinator service injects both runtime-read and read-model-assembler dependencies in order'
);
assert(
  (gameCoordinatorServiceBlock.match(/^\s+-\s'@/gm) || []).length === 23,
  'game coordinator service defines all 23 constructor arguments'
);
assert(
  coordinatorSource.includes('protected CoordinatorRuntimeReadService $coordinatorRuntimeReadService;'),
  'game coordinator has coordinator runtime-read service dependency'
);
assert(
  coordinatorSource.includes('$this->coordinatorRuntimeReadService->resolveActionAvailabilityContext(')
    && coordinatorSource.includes('      $campaign_id,')
    && coordinatorSource.includes('      $actor_id,')
    && coordinatorSource.includes('      $diagnostic_context,'),
  'game coordinator resolves action-availability read context through runtime-read service'
);
assert(
  coordinatorSource.includes('$this->coordinatorRuntimeReadService->resolveFullStateReadContext($campaign_id);'),
  'game coordinator resolves full-state read context through runtime-read service'
);
assert(
  coordinatorSource.includes('$this->coordinatorRuntimeReadService->resolveMutationExecutionContext('),
  'game coordinator resolves mutation execution context through specialized runtime-read hydration lane'
);
assert(
  coordinatorSource.includes('$this->coordinatorRuntimeReadService->resolveFullRuntimeProjection($campaign_id, $actor_id);'),
  'game coordinator resolves full runtime projection through runtime-read service'
);
assert(
  runtimeReadServiceSource.includes('public function resolveMutationExecutionContext('),
  'coordinator runtime-read service exposes specialized mutation execution context loader'
);
assert(
  runtimeReadServiceSource.includes('$requested_room_id !== \'\' ? $requested_room_id : NULL,\n      FALSE'),
  'mutation execution context defaults to narrow lane hydration before compatibility sync fallback'
);
assert(
  runtimeReadServiceSource.includes('public function resolveFullRuntimeProjection(int $campaign_id, ?string $actor_id = NULL): ?array {'),
  'coordinator runtime-read service exposes heavy full-runtime projection hydration lane'
);
assert(
  runtimeReadServiceSource.includes('protected function requiresActiveRoomPlayerSync(array $dungeon_data, ?string $preferred_actor_id = NULL): bool {'),
  'runtime-read service defines explicit compatibility sync fallback gate'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
