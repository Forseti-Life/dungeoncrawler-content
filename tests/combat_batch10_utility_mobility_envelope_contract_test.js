/**
 * @file
 * Contract test: 10-lane utility/mobility batch emits canonical envelopes.
 *
 * Run with:
 *   node tests/combat_batch10_utility_mobility_envelope_contract_test.js
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

console.log('\n=== Combat batch-10 utility/mobility envelope contract ===');

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'seek'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'search'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'sense_motive'"),
  'Seek/search/sense-motive lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'balance'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'tumble_through'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'maneuver_in_flight'"),
  'Balance/tumble-through/maneuver-in-flight lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'take_cover'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'release'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'climb'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'force_open'"),
  'Take-cover/release/climb/force-open lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets")
    && phaseHandlerSource.includes("'action' => 'balance'")
    && phaseHandlerSource.includes("'action' => 'climb'"),
  'Balance and climb route condition side-effects through unified state-effect packets'
);

assert(
  phaseHandlerSource.includes("buildEvent('seek', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('search', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('sense_motive', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('balance', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('tumble_through', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('maneuver_in_flight', 'encounter', $actor_id,")
    && phaseHandlerSource.includes("buildEvent('take_cover', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('release', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('climb', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('force_open', 'encounter', $actor_id, ["),
  'All 10 batch lanes include event payload emission with canonical metadata'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
