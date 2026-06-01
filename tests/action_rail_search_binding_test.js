/**
 * @file
 * Focused regressions for action-rail Search bindings.
 *
 * Run with:
 *   node tests/action_rail_search_binding_test.js
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
const hexmapV2Source = fs.readFileSync(path.resolve(__dirname, '../js/hexmap-v2.js'), 'utf8');
const gameShellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');
const actionRailPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const encounterSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const statusPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/StatusPanel.js'), 'utf8');
const chatPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'), 'utf8');
const coordinatorApiSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/GameCoordinatorApi.js'), 'utf8');
const explorationPhaseSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'), 'utf8');
const encounterPhaseSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'), 'utf8');

console.log('\n=== Action rail Search bindings ===');
assert(
  hexmapSource.includes('handleActionRailDirectAction(actionKey, button = null)')
    && hexmapSource.includes("if (actionKey === 'search') {\n        this.executeDirectSearch(button);"),
  'legacy action rail Search direct action executes the shared search handler'
);
assert(
  hexmapSource.includes("./game-coordinator/GameCoordinator.js?v=20260601-search-framework-2")
    && fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/GameCoordinator.js'), 'utf8').includes("./GameCoordinatorApi.js?v=20260601-search-framework-2"),
  'legacy nested coordinator imports are cache-busted when Search contracts change'
);
assert(
  hexmapSource.includes("search_mode: 'explicit'")
    && hexmapSource.includes("params: {\n              search_mode: 'explicit',\n            }")
    && !hexmapSource.includes('explicit_search: true')
    && !hexmapSource.includes('perception_bonus: perceptionBonus')
    && !hexmapSource.includes('searches the room.'),
  'legacy explicit Search uses only the standardized search_mode contract without silent-failure chat'
);
assert(
  hexmapSource.includes("const actionSearchBtn = document.getElementById('action-search');")
    && hexmapSource.includes("addTrackedListener(actionSearchBtn, 'click', async function ()"),
  'standalone #action-search button has a click binding'
);
assert(
  hexmapSource.includes('const canSearchExplorationRoom = Boolean(')
    && hexmapSource.includes('const canInteract = canAct || canSearchExplorationRoom;'),
  'standalone Search remains enabled for active exploration-room actors without combat turns'
);
assert(
  hexmapSource.includes('runtimeContext?.characterId')
    && hexmapSource.includes('hexmap?.launchContext?.character_id'),
  'action rail resolves character id from launch/runtime context before character-sheet hydration'
);
assert(
  actionRailPanelSource.includes('handleActionRailDirectAction(actionKey, button = null)')
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey, button });"),
  'v2 action rail Search emits the clicked button to the action executor'
);
assert(
  gameShellSource.includes('resolveLaunchCharacterRuntimeContext: () => ({')
    && gameShellSource.includes('instanceId: resolveRuntimeInstanceId(),')
    && gameShellSource.includes('roomId: shell.activeRoomId || shell.launchContext?.room_id || null,'),
  'v2 hexmap shim exposes runtime actor context so exploration Search can unlock without ECS combat state'
);
assert(
  hexmapV2Source.includes("./v2/GameShell.js?v=20260601-v2-search-framework-4")
    && gameShellSource.includes("./systems/EncounterSystem.js?v=20260601-v2-search-framework-2")
    && gameShellSource.includes("./panels/ChatPanel.js?v=20260601-v2-search-framework-3"),
  'v2 entrypoint cache-busts GameShell imports when action-rail runtime contracts change'
);
assert(
  encounterSystemSource.includes("search_mode: 'explicit'")
    && encounterSystemSource.includes("coordinator.api.sendAction('search', actorRef, {\n        search_mode: 'explicit',\n      }")
    && !encounterSystemSource.includes('explicit_search: true')
    && !encounterSystemSource.includes('perception_bonus: perceptionBonus')
    && !encounterSystemSource.includes('searches the room.'),
  'v2 explicit Search uses only the standardized search_mode contract without leaking silent failures'
);
assert(
  coordinatorApiSource.includes("return this.sendAction('search', actor, { search_mode: 'explicit' }, { stateVersion });")
    && !coordinatorApiSource.includes("sendAction('search', actor, {}, { stateVersion })"),
  'shared coordinator Search wrapper also sends the explicit standardized contract'
);
assert(
  explorationPhaseSource.includes('protected const SEARCH_MODE_EXPLICIT')
    && explorationPhaseSource.includes('protected const SEARCH_EXPLICIT_BONUS = 2;')
    && explorationPhaseSource.includes('protected function resolveSearchPerceptionBonus(array $params): int')
    && explorationPhaseSource.includes('$perception_rank = self::SEARCH_PROFICIENCY_RANK;')
    && explorationPhaseSource.includes('You notice ')
    && !explorationPhaseSource.includes('yields no new clues')
    && !explorationPhaseSource.includes('trained senses immediately catch'),
  'server Search framework uses no passive modifiers, explicit +2, generic discovery narration, and silent misses'
);
assert(
  encounterPhaseSource.includes('$public_discoveries !== [] || (is_string($narration) && trim($narration) !== \'\')')
    && encounterPhaseSource.includes('$result = $this->buildPublicSearchResult($result);')
    && encounterPhaseSource.includes('protected function buildPublicSearchResult(array $result): array')
    && !encounterPhaseSource.includes("'roll' => $result['roll'] ?? NULL,\n          'dc' => $result['dc'] ?? NULL,\n          'degree' => $result['degree'] ?? NULL")
    && !encounterPhaseSource.includes('content\' => trim($narration)')
    && !explorationPhaseSource.includes('content\' => trim($narration)')
    && !encounterPhaseSource.includes('searches the area (Perception %d vs DC %d: %s).'),
  'Search emits only sanitized discovery events and does not queue duplicate session narration'
);
assert(
  chatPanelSource.includes("if (type === 'search' && typeof event.narration === 'string' && event.narration.trim())")
    && chatPanelSource.includes('message: event.narration.trim()'),
  'v2 chat renders successful Search discovery events while silent misses remain hidden'
);
assert(
  encounterSystemSource.includes("this.bus.on('combat:round-changed', (d) => this.announceRoundChange(d))")
    && encounterSystemSource.includes("this.bus.on('combat:turn-changed',  (d) => this.announceTurnChange(d))")
    && encounterSystemSource.includes("Round ${roundNumber} begins.")
    && encounterSystemSource.includes("Next actor: ${actorName}${turnLabel}.")
    && encounterSystemSource.includes('announceGameState(result?.game_state)'),
  'v2 encounter system narrates round and next-actor turn transitions'
);
assert(
  statusPanelSource.includes("this.bus.on('game:backend-request-start', (d) => this.showBackendWait(d))")
    && statusPanelSource.includes("this.bus.on('game:backend-request-end',   (d) => this.hideBackendWait(d))")
    && statusPanelSource.includes('Still waiting; the backend may be busy.'),
  'v2 status panel shows active and slow backend wait cues'
);
assert(
  actionRailPanelSource.includes("this.bus.emit('game:backend-request-start'")
    && actionRailPanelSource.includes("this.bus.emit('game:backend-request-end'")
    && chatPanelSource.includes("label: 'Waiting for narrator response...'")
    && gameShellSource.includes("source: 'chat-submit'")
    && gameShellSource.includes("source: 'room-view'"),
  'v2 action rail and chat requests emit backend wait lifecycle events'
);
assert(
  actionRailPanelSource.includes("this.bus.emit('user:end-turn', { button })")
    && encounterSystemSource.includes("this.bus.on('user:end-turn',     (d) => this.endCurrentTurn(d))")
    && encounterSystemSource.includes(": 'end_turn'")
    && encounterSystemSource.includes('async endCurrentTurn(data = {})'),
  'v2 End Turn uses the server game-action API instead of the legacy shim recursion path'
);

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
