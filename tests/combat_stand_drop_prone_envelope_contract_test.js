/**
 * @file
 * Contract test: stand/drop-prone lanes emit canonical envelopes and state-effect packets.
 *
 * Run with:
 *   node tests/combat_stand_drop_prone_envelope_contract_test.js
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

console.log('\n=== Combat stand/drop-prone envelope contract ===');

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'stand'")
    && phaseHandlerSource.includes("buildEvent('stand'")
    && phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets")
    && phaseHandlerSource.includes("'action' => 'stand'"),
  'Stand emits canonical execution/envelope payloads and state-effect packet metadata'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'drop_prone'")
    && phaseHandlerSource.includes("buildEvent('drop_prone'")
    && phaseHandlerSource.includes("'action' => 'drop_prone'")
    && phaseHandlerSource.includes("'prone' => TRUE"),
  'Drop-prone emits canonical execution/envelope payloads and prone outcome metadata'
);

assert(
  phaseHandlerSource.includes("$this->unifiedStateEffectEngine->buildStateEffectChangePacket(")
    && phaseHandlerSource.includes("'removed'")
    && phaseHandlerSource.includes("'applied'"),
  'Stand/drop-prone route prone condition changes through UnifiedStateEffectEngine'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
