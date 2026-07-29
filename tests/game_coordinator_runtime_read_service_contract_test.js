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
  coordinatorSource.includes('protected CoordinatorRuntimeReadService $coordinatorRuntimeReadService;'),
  'game coordinator has coordinator runtime-read service dependency'
);
assert(
  coordinatorSource.includes('$this->coordinatorRuntimeReadService->resolveActionAvailabilityContext($campaign_id, $actor_id);'),
  'game coordinator resolves action-availability read context through runtime-read service'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
