/**
 * @file
 * Contract test: fourth 10-lane hazard/activation/casting batch.
 *
 * Run with:
 *   node tests/combat_batch10_hazard_activation_casting_envelope_contract_test.js
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

console.log('\n=== Combat batch-10 hazard/activation/casting envelope contract ===');

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'disable_hazard'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'attack_hazard'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'counteract_hazard'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'activate_item'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'sustain_activation'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'dismiss_activation'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'sustain_spell'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'dismiss_spell'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'cast_from_scroll'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'cast_from_staff'"),
  'All 10 hazard/activation/casting lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("buildEvent('disable_hazard', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('attack_hazard', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('counteract_hazard', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('activate_item', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('sustain_activation', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('dismiss_activation', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('sustain_spell', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('dismiss_spell', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('cast_from_scroll', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('cast_from_staff', 'encounter', $actor_id, ["),
  'All 10 hazard/activation/casting lanes emit canonical event metadata'
);

assert(
  phaseHandlerSource.includes("'phase_transition' => $phase_transition")
    && phaseHandlerSource.includes("'hazard_id' => $hazard_id")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope"),
  'Hazard lanes preserve phase transitions while carrying canonical contract envelopes'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
