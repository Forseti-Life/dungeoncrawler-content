/**
 * @file
 * Contract test: coordinator runtime snapshot assembly extraction.
 *
 * Run with:
 *   node tests/game_coordinator_runtime_read_model_assembler_contract_test.js
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
const assemblerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RuntimeStateReadModelAssembler.php'),
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

console.log('\n=== Coordinator runtime read-model assembler contract ===');

assert(
  servicesSource.includes('dungeoncrawler_content.runtime_state_read_model_assembler:'),
  'service container defines runtime state read-model assembler service'
);
assert(
  servicesSource.includes("- '@dungeoncrawler_content.runtime_state_read_model_assembler'"),
  'game coordinator wiring includes runtime state read-model assembler dependency'
);
assert(
  coordinatorSource.includes('protected RuntimeStateReadModelAssembler $runtimeStateReadModelAssembler;'),
  'game coordinator has runtime state read-model assembler dependency'
);
assert(
  coordinatorSource.includes('$this->runtimeStateReadModelAssembler->buildRuntimeSnapshotPayload('),
  'game coordinator delegates runtime snapshot assembly to read-model assembler'
);
assert(
  assemblerSource.includes('class RuntimeStateReadModelAssembler'),
  'runtime state read-model assembler implementation exists'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
