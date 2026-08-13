/**
 * @file
 * Contract test: step/crawl/leap lanes emit canonical action + movement contracts.
 *
 * Run with:
 *   node tests/combat_step_crawl_leap_envelope_contract_test.js
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

console.log('\n=== Combat step/crawl/leap envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'step'")
    && phaseHandlerSource.includes("buildEvent('step'")
    && phaseHandlerSource.includes("'movement_execution_request' => $movement_execution_request")
    && phaseHandlerSource.includes("'movement_resolution_envelope' => $movement_resolution_envelope"),
  'Step lane emits action-level contract payloads plus validated movement subcontracts'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'crawl'")
    && phaseHandlerSource.includes("buildEvent('crawl'")
    && phaseHandlerSource.includes("'movement_execution_request' => $movement_execution_request")
    && phaseHandlerSource.includes("'movement_packet' => $movement_packet"),
  'Crawl lane emits action-level contract payloads plus validated movement packet'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'leap'")
    && phaseHandlerSource.includes("buildEvent('leap'")
    && phaseHandlerSource.includes("'max_leap_ft' => $max_leap_ft")
    && phaseHandlerSource.includes("'movement_resolution_envelope' => $movement_resolution_envelope"),
  'Leap lane emits action-level contract payloads plus validated movement envelope'
);

assert(
  phaseHandlerSource.includes("step.movement.execution_request")
    && phaseHandlerSource.includes("crawl.movement.execution_request")
    && phaseHandlerSource.includes("leap.movement.execution_request"),
  'Step/crawl/leap validate stride-derived movement subcontracts before emission'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
