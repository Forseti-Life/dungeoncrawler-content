/**
 * @file
 * Contract test: shove forced-movement path validates and emits canonical stride contracts.
 *
 * Run with:
 *   node tests/combat_shove_forced_movement_contract_test.js
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

console.log('\n=== Combat shove forced-movement contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("requireOptionalContractPayload(\n              $forced_result['execution_request'] ?? NULL,")
    && phaseHandlerSource.includes("requireOptionalContractPayload(\n              $forced_result['resolution_envelope'] ?? NULL,")
    && phaseHandlerSource.includes("requireOptionalContractPayload(\n              $forced_result['movement_packet'] ?? NULL,"),
  'Shove forced-movement validates execution, envelope, and movement packet contracts'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'shove'")
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope")
    && phaseHandlerSource.includes("'forced_execution_request' => $forced_execution_request")
    && phaseHandlerSource.includes("'movement_packet' => $forced_movement_packet")
    && phaseHandlerSource.includes("'forced_resolution_envelope' => $forced_resolution_envelope"),
  'Shove emits action-level canonical contracts plus validated forced-movement subcontracts'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
