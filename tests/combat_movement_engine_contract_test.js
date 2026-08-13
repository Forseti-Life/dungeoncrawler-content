/**
 * @file
 * Contract test: movement packet emission routes through UnifiedMovementEngine.
 *
 * Run with:
 *   node tests/combat_movement_engine_contract_test.js
 */

const fs = require('fs');
const path = require('path');

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

function read(relPath) {
  return fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
}

console.log('\n=== Combat movement engine contract ===');

const movementEngineSource = read('../src/Service/UnifiedMovementEngine.php');
assert(
  movementEngineSource.includes('class UnifiedMovementEngine')
    && movementEngineSource.includes('buildMovementResolutionPacket(')
    && movementEngineSource.includes('CombatResolutionContractService'),
  'UnifiedMovementEngine defines the shared movement packet seam'
);

const executorSource = read('../src/Service/EncounterActionExecutor.php');
assert(
  executorSource.includes('protected UnifiedMovementEngine $unifiedMovementEngine;')
    && executorSource.includes('$this->unifiedMovementEngine = $unified_movement_engine ?? new UnifiedMovementEngine(')
    && executorSource.includes('$movement_packet = $this->unifiedMovementEngine->buildMovementResolutionPacket('),
  'EncounterActionExecutor routes movement packet emission through UnifiedMovementEngine'
);

const servicesSource = read('../dungeoncrawler_content.services.yml');
assert(
  servicesSource.includes('dungeoncrawler_content.unified_movement_engine:')
    && servicesSource.includes("class: Drupal\\dungeoncrawler_content\\Service\\UnifiedMovementEngine")
    && servicesSource.includes("'@dungeoncrawler_content.unified_movement_engine'"),
  'Service container wires UnifiedMovementEngine into encounter action executor'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
