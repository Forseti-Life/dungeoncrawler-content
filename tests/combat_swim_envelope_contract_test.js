/**
 * @file
 * Contract test: swim lane emits canonical action envelope contracts.
 *
 * Run with:
 *   node tests/combat_swim_envelope_contract_test.js
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

console.log('\n=== Combat swim envelope contract ===');

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'swim'")
    && phaseHandlerSource.includes("buildEvent('swim'")
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope"),
  'Swim lane emits canonical execution request and resolution envelope metadata'
);

assert(
  phaseHandlerSource.includes("'breath_lost' => $breath_lost")
    && phaseHandlerSource.includes("'feet_moved' => $feet_moved")
    && phaseHandlerSource.includes("'degree' => $degree"),
  'Swim envelope preserves key mechanical outcome fields'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
