/**
 * @file
 * Contract test: disarm lane emits canonical execution/envelope contracts.
 *
 * Run with:
 *   node tests/combat_disarm_envelope_contract_test.js
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

console.log('\n=== Combat disarm envelope contract ===');

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'disarm'")
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope")
    && phaseHandlerSource.includes("buildEvent('disarm'"),
  'Disarm lane emits canonical execution request and resolution envelope metadata'
);

assert(
  phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets")
    && phaseHandlerSource.includes("$this->unifiedStateEffectEngine->buildStateEffectChangePacket(")
    && phaseHandlerSource.includes("'flat_footed'"),
  'Disarm critical-failure state effects are emitted via unified state-effect packets'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
