/**
 * @file
 * Contract test: third 10-lane reaction/defense/utility batch.
 *
 * Run with:
 *   node tests/combat_batch10_reaction_defense_utility_envelope_contract_test.js
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

console.log('\n=== Combat batch-10 reaction/defense/utility envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'arrest_fall'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'grab_edge'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'shield_block'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'attack_of_opportunity'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'raise_shield'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'avert_gaze'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'point_out'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'minor_color_shift'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'consume_item'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'declare_metamagic'"),
  'All 10 reaction/defense/utility lanes emit canonical execution requests'
);

assert(
  phaseHandlerSource.includes("$this->unifiedReactionEngine->buildReactionResolutionPacket(")
    && phaseHandlerSource.includes("'reaction_packet' => $reaction_packet")
    && phaseHandlerSource.includes("attack_of_opportunity.strike.execution_request")
    && phaseHandlerSource.includes("attack_of_opportunity.strike.resolution_envelope"),
  'Reaction lanes emit unified reaction packets and AoO validates nested strike contracts'
);

assert(
  phaseHandlerSource.includes("buildEvent('arrest_fall', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('grab_edge', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('shield_block', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('attack_of_opportunity', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('raise_shield', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('avert_gaze', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('point_out', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('minor_color_shift', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('consume_item', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("buildEvent('declare_metamagic', 'encounter', $actor_id, ["),
  'All 10 lanes emit events with canonical metadata'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
