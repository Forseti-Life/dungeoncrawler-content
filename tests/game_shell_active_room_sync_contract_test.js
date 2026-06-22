/**
 * Contract test: GameShell must adopt the coordinator's authoritative room
 * after server state initialization.
 *
 * Run with:
 *   node tests/game_shell_active_room_sync_contract_test.js
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const source = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/GameShell.js'),
  'utf8'
);

assert(
  source.includes('const authoritativeRoomId = String(this.gameCoordinator?.phaseManager?.activeRoomId || \'\').trim();'),
  'GameShell should read the authoritative active room from the coordinator phase manager after init'
);

assert(
  source.includes("this._setStateValue('activeRoomId', authoritativeRoomId);") &&
  source.includes("this.bus.emit('room:changed', {") &&
  source.includes("this._loadChatHistory();") &&
  source.includes("this._loadRoomView({ force: true, preserveExisting: true });"),
  'GameShell should switch to the authoritative room and reload room-specific UI after coordinator init'
);

console.log('OK game shell active room sync contract');
