/**
 * @file
 * Contract test: second 10-lane social/stealth batch emits canonical envelopes.
 *
 * Run with:
 *   node tests/combat_batch10_social_stealth_envelope_contract_test.js
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

console.log('\n=== Combat batch-10 social/stealth envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'feint'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'create_diversion'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'request'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'demoralize'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'command_animal'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'perform'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'escape'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'hide'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'sneak'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'conceal_object'"),
  'All 10 social/stealth lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("'action' => 'feint'")
    && phaseHandlerSource.includes("'action' => 'demoralize'")
    && phaseHandlerSource.includes("$this->unifiedStateEffectEngine->buildStateEffectChangePacket(")
    && phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets"),
  'Feint and demoralize route condition side-effects through unified state-effect packets'
);

assert(
  phaseHandlerSource.includes("buildEvent('feint', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('create_diversion', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('request', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('demoralize', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('command_animal', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('perform', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('escape', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('hide', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('sneak', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('conceal_object', 'encounter', $actor_id, ["),
  'All 10 social/stealth lanes emit events with canonical metadata'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
