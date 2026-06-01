/**
 * @file
 * Focused regressions for coordinator-backed action rail turn sync.
 *
 * Run with:
 *   node tests/action_rail_turn_sync_test.js
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

const hexmapSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');
const turnManagementSource = fs.readFileSync(path.resolve(__dirname, '../js/ecs/systems/TurnManagementSystem.js'), 'utf8');
const encounterPhaseSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/phases/EncounterPhaseHandler.js'), 'utf8');
const coordinatorSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/GameCoordinator.js'), 'utf8');

console.log('\n=== Action rail turn synchronization ===');
assert(
  hexmapSource.includes('const result = await coordinator.api?.endTurn?.(actorRef, coordinator.phaseManager?.stateVersion);')
    && hexmapSource.includes('coordinator.applyAuthoritativeUpdate?.(result);')
    && hexmapSource.includes('coordinator.getActiveHandler?.()?._syncTurnManagement?.(result);'),
  'End Turn uses the coordinator action path before falling back to raw combat API'
);
assert(
  hexmapSource.includes('hexmap.gameCoordinator?.applyAuthoritativeUpdate?.(data);')
    && hexmapSource.includes('hexmap.gameCoordinator?.getActiveHandler?.()?._syncTurnManagement?.(data);'),
  'Search applies returned coordinator game_state to clocks, actions, and turn UI'
);
assert(
  encounterPhaseSource.includes('const participants = initiativeOrder.map((entry, index) => {')
    && encounterPhaseSource.includes('actions_remaining: isCurrentTurn')
    && encounterPhaseSource.includes("status: gameState.phase === 'encounter' && gameState.encounter_id ? 'active' : 'ended'"),
  'Coordinator encounter sync converts game_state turn data into TurnManagementSystem participants'
);
assert(
  turnManagementSource.includes('const currentTurnResourcesChanged = Boolean(')
    && turnManagementSource.includes('|| currentTurnResourcesChanged;'),
  'TurnManagementSystem emits turn updates when same-turn resources refresh'
);
assert(
  coordinatorSource.includes("actions.push('strike', 'stride', 'interact', 'search');")
    && coordinatorSource.includes("search: { label: 'Search', cost: 1, category: 'perception', requires_turn: true, targeting: 'room' }"),
  'Projected combat action contract includes Search as a legal turn action'
);

console.log('\n========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
