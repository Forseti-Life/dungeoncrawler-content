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

const source = require('./helpers/js-source.js').readGameShellSource();

assert(
  source.includes('const authoritativeRoomId = String(this.gameCoordinator?.phaseManager?.activeRoomId || \'\').trim();'),
  'GameShell should read the authoritative active room from the coordinator phase manager after init'
);

// The room switch is no longer performed by ad-hoc _setStateValue/room:changed
// calls at the coordinator-init site. It is hydrated atomically from the
// canonical runtime bundle, so the contract is asserted at both ends.
assert(
  source.includes('await this.loadRuntimeStateBundle(') &&
  source.includes('this.buildRuntimeBundleQueryForRoom(authoritativeRoomId, {'),
  'GameShell should hydrate the authoritative room from the canonical runtime state bundle after coordinator init'
);

assert(
  source.includes('this._syncActiveRoomAuthorityFromRuntimePayload();') &&
  source.includes('this._emitInitialRoomState();') &&
  source.includes('this._loadChatHistory();') &&
  source.includes('this._loadRoomView({ force: true, preserveExisting: true });'),
  'GameShell should switch to the authoritative room and reload room-specific UI when the runtime bundle is applied'
);

assert(
  /_syncActiveRoomAuthorityFromRuntimePayload\(\)\s*\{[\s\S]*?this\.activeRoomId\s*=/.test(source),
  'Runtime payload sync should assign the authoritative activeRoomId'
);

assert(
  /_emitInitialRoomState\(\)\s*\{[\s\S]*?room:changed/.test(source),
  'Initial room state emission should broadcast room:changed'
);

console.log('OK game shell active room sync contract');
