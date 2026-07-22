/*
 * Contract test: GameCoordinator should use bootstrapped page state before
 * attempting an initial /api/game/{campaign_id}/state fetch.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const source = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/GameCoordinator.js'), 'utf8');

  assert(
    source.includes('const bootstrapState = this._getBootstrapState();'),
    'GameCoordinator init should look for bootstrapped game state first',
  );

  assert(
    source.includes('if (bootstrapState) {'),
    'GameCoordinator should short-circuit initial server fetch when bootstrap state exists',
  );

  assert(
    source.includes('_getBootstrapState() {'),
    'GameCoordinator should expose a helper that reads bootstrapped page state',
  );

  console.log('OK game coordinator bootstrap state contract');
})();
