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
    source.includes('const bootstrapAvailableActions = Array.isArray(this.hexmap?.dungeonData?.available_actions)')
      && source.includes('const bootstrapActionContract = this.hexmap?.dungeonData?.action_contract || null;')
      && source.includes('const shouldFetchInitialState = !bootstrapState')
      && source.includes('|| bootstrapAvailableActions.length === 0')
      && source.includes('|| !bootstrapActionContract;'),
    'GameCoordinator should fetch initial server state when bootstrap lacks action availability or contract data',
  );

  assert(
    source.includes('_getBootstrapState() {'),
    'GameCoordinator should expose a helper that reads bootstrapped page state',
  );

  assert(
    source.includes('presentation?.status')
      && source.includes('?? serverState.status')
      && source.includes(').trim().toLowerCase();')
      && source.includes("status === 'active'"),
    'GameCoordinator should normalize encounter presentation status casing before active-encounter checks',
  );

  console.log('OK game coordinator bootstrap state contract');
})();
