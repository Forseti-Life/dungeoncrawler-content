/**
 * @file
 * Contract test: reaction/aid lanes emit canonical action envelopes.
 *
 * Run with:
 *   node tests/combat_reaction_aid_envelope_contract_test.js
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

console.log('\n=== Combat reaction/aid envelope contract ===');

const phaseHandlerSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'reaction'")
    && phaseHandlerSource.includes("buildEvent('reaction', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'reaction_packet' => $reaction_packet")
    && phaseHandlerSource.includes("$this->unifiedReactionEngine->buildReactionResolutionPacket("),
  'Reaction lane emits canonical execution/envelope metadata and unified reaction packet'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'aid_setup'")
    && phaseHandlerSource.includes("buildEvent('aid_setup', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'skill' => $aid_skill")
    && phaseHandlerSource.includes("'aid_prepared' => TRUE"),
  'Aid-setup lane emits canonical execution/envelope metadata with ready skill payload'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'aid'")
    && phaseHandlerSource.includes("buildEvent('aid', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'degree' => $result['degree'] ?? NULL")
    && phaseHandlerSource.includes("'aid_bonus' => $result['aid_bonus'] ?? 0"),
  'Aid lane emits canonical execution/envelope metadata with aid outcome payload'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
