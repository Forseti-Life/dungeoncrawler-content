/**
 * @file
 * Contract test: Action Rail must refresh when coordinator state/actions hydrate late.
 *
 * Run with:
 *   node tests/action_rail_state_refresh_contract_test.js
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

const gameShellSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/GameShell.js'),
  'utf8',
);
const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../js/game-coordinator/GameCoordinator.js'),
  'utf8',
);
const panelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'),
  'utf8',
);

console.log('\n=== Action rail late-state refresh contract ===');

assert(
  gameShellSource.includes('get bus() { return shell.bus; }'),
  'hexmap shim exposes the shell bus to game coordinator listeners'
);

assert(
  coordinatorSource.includes("this.phaseManager.on('actionsUpdate'")
    && coordinatorSource.includes("this.hexmap?.bus?.emit?.('game:state-refreshed'")
    && coordinatorSource.includes("this.phaseManager.on('stateUpdate'"),
  'GameCoordinator bridges PhaseManager action/state hydration onto the shared UI bus'
);

assert(
  panelSource.includes("this.bus.on('game:state-refreshed', () => this.invalidateActionRail([ACTION_RAIL_DOMAIN_ALL]))"),
  'ActionRailPanel fully refreshes when late coordinator state arrives'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
