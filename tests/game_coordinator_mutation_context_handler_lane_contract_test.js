/**
 * @file
 * Contract test: coordinator typed mutation-context handler lane.
 *
 * Run with:
 *   node tests/game_coordinator_mutation_context_handler_lane_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8'
);
const encounterHandlerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8'
);
const explorationHandlerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'),
  'utf8'
);
const downtimeHandlerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/DowntimePhaseHandler.php'),
  'utf8'
);
const typedHandlerInterfaceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/MutationContextPhaseHandlerInterface.php'),
  'utf8'
);

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

console.log('\n=== Coordinator typed mutation-context handler lane contract ===');

assert(
  typedHandlerInterfaceSource.includes('interface MutationContextPhaseHandlerInterface extends PhaseHandlerInterface'),
  'typed mutation-context handler interface extends base phase handler contract'
);
assert(
  coordinatorSource.includes('$this->processHandlerIntent($handler, $intent, $game_state, $dungeon_data, $campaign_id)'),
  'coordinator action execution uses typed handler intent lane adapter'
);
assert(
  coordinatorSource.includes('$this->runHandlerOnExit($from_handler, $game_state, $dungeon_data, $campaign_id)'),
  'coordinator phase transitions use typed handler exit lane adapter'
);
assert(
  coordinatorSource.includes('$this->runHandlerOnEnter($to_handler, $context, $game_state, $dungeon_data, $campaign_id)'),
  'coordinator phase transitions use typed handler enter lane adapter'
);
assert(
  encounterHandlerSource.includes('implements EncounterMasterInterface, MutationContextPhaseHandlerInterface'),
  'encounter phase handler opts into typed mutation-context handler contract'
);
assert(
  explorationHandlerSource.includes('implements MutationContextPhaseHandlerInterface'),
  'exploration phase handler opts into typed mutation-context handler contract'
);
assert(
  downtimeHandlerSource.includes('implements MutationContextPhaseHandlerInterface'),
  'downtime phase handler opts into typed mutation-context handler contract'
);
assert(
  coordinatorSource.includes('new RuntimeMutationExecutionContext($game_state, $dungeon_data)'),
  'coordinator materializes typed mutation execution context when invoking typed handler lanes'
);
assert(
  !coordinatorSource.includes('protected function loadDungeonData('),
  'legacy coordinator-owned loadDungeonData helper is removed'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
