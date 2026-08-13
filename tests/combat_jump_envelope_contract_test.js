/**
 * @file
 * Contract test: high-jump/long-jump lanes emit canonical action envelopes.
 *
 * Run with:
 *   node tests/combat_jump_envelope_contract_test.js
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

console.log('\n=== Combat jump envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'high_jump'")
    && phaseHandlerSource.includes("buildEvent('high_jump'")
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope"),
  'High jump lane emits canonical execution request and resolution envelope metadata'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'long_jump'")
    && phaseHandlerSource.includes("buildEvent('long_jump'")
    && phaseHandlerSource.includes("'execution_request' => $execution_request")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope"),
  'Long jump lane emits canonical execution request and resolution envelope metadata'
);

assert(
  phaseHandlerSource.includes("'action' => 'high_jump'")
    && phaseHandlerSource.includes("'action' => 'long_jump'")
    && phaseHandlerSource.includes("$this->unifiedStateEffectEngine->buildStateEffectChangePacket("),
  'Jump critical-failure prone effects are routed through unified state-effect packets'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
