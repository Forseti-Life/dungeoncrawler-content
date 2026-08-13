/**
 * @file
 * Contract test: turn-control lanes emit canonical action envelopes.
 *
 * Run with:
 *   node tests/combat_turn_control_envelope_contract_test.js
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

console.log('\n=== Combat turn-control envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes('protected function routeEndTurnIntentExecution(')
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      $type")
    && phaseHandlerSource.includes("buildEvent($type, 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'resolution_envelope' => $resolution_envelope"),
  'End-turn lane emits canonical execution request and resolution envelope metadata'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'delay'")
    && phaseHandlerSource.includes("buildEvent('delay', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'delay_reenter'")
    && phaseHandlerSource.includes("buildEvent('delay_reenter', 'encounter', $actor_id, ["),
  'Delay and delay-reenter lanes emit canonical execution request and envelope metadata'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'ready'")
    && phaseHandlerSource.includes("buildEvent('ready', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'action' => $ready_action")
    && phaseHandlerSource.includes("'trigger' => $ready_trigger"),
  'Ready lane emits canonical execution request and envelope with ready payload'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
