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
  actionRailPanelSource.includes("const contractActorRef = String(phaseSnapshot?.actionContract?.actor_id || '').trim();")
    && actionRailPanelSource.includes('const hasTurnScopedAction = availableActions.some((entry) => [')
    && actionRailPanelSource.includes("|| ((hasServerTurn && hasTurnScopedAction && serverTurnEntity) ? serverTurnEntity : '')")
    && actionRailPanelSource.includes('|| (Boolean(actorRef) && serverTurnEntity === actorRef);')
    && !actionRailPanelSource.includes('|| !actorRef'),
  'v2 action rail resolves actorRef from canonical contract/turn context and does not mark missing actorRef as active turn'
);
assert(
  actionRailPanelSource.includes('handleActionRailPanelAction(button)')
    && actionRailPanelSource.includes("this.bus.emit('user:action-selected', { actionKey:")
    && actionRailPanelSource.includes(', button });'),
  'v2 action rail emits action-selected with the clicked button for Search and other category actions'
);
assert(
  gameShellSource.includes('resolveLaunchCharacterRuntimeContext: () => shell.resolveLaunchCharacterRuntimeContext(),')
    && gameShellSource.includes('resolveLaunchCharacterRuntimeContext() {')
    && gameShellSource.includes('instanceId: launchCharacterId > 0 && selectedCharacterId === launchCharacterId')
    && gameShellSource.includes('roomId: this.resolveActiveRoomId(),'),
  'v2 hexmap shim exposes runtime actor context so exploration Search can unlock without ECS combat state'
);
assert(
  gameShellSource.includes('applyQuestUpdates: (questUpdates = []) => shell.applyQuestUpdates(questUpdates),')
    && gameShellSource.includes('refreshQuestJournalFromApi: () => shell.refreshQuestJournalFromApi(),')
    && gameShellSource.includes('async refreshQuestJournalFromApi() {')
    && gameShellSource.includes('async applyQuestUpdates(questUpdates = []) {')
    && gameShellSource.includes('await this.applyQuestUpdates(questUpdates);')
    && chatPanelSource.includes('await questHexmap?.applyQuestUpdates?.(result.data.quest_updates);')
    && chatPanelSource.includes('await questHexmap.refreshQuestJournalFromApi();'),
  'v2 quest tab refresh flows through hexmap shim quest-journal methods for authoritative updates'
);
assert(
  hexmapV2Source.includes("./v2/GameShell.js?v=")
    && /\.\/systems\/EncounterSystem\.js\?v=/.test(gameShellSource)
    && /\.\/panels\/ChatPanel\.js\?v=/.test(gameShellSource),
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
    && explorationPhaseSource.includes('protected const SEARCH_MODE_AUTOMATIC')
    && explorationPhaseSource.includes('protected const SEARCH_EXPLICIT_BONUS = 1;')
    && explorationPhaseSource.includes('protected function resolveSearchPerceptionBonus(array $params, ?array $actor = NULL): int')
    && explorationPhaseSource.includes('protected function resolveEntityPerceptionBonus(array $actor): int')
    && explorationPhaseSource.includes('$perception_rank = $this->resolveSearchPerceptionRank($params, $actor);')
    && explorationPhaseSource.includes('You notice ')
    && explorationPhaseSource.includes('You search the area carefully but do not uncover anything new.')
    && !explorationPhaseSource.includes('yields no new clues')
    && !explorationPhaseSource.includes('trained senses immediately catch'),
  'server Search framework uses actor Perception with explicit-search bonus and always returns explicit search feedback'
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
  'v2 chat renders Search narration events, including explicit no-discovery feedback'
);
assert(
  chatPanelSource.includes("window.addEventListener('dungeoncrawler:game-events', onGameEvents)")
    && chatPanelSource.includes("window.removeEventListener('dungeoncrawler:game-events', onGameEvents)")
    && chatPanelSource.includes('this._encounterTranscriptRoomKey')
    && chatPanelSource.includes('this.renderPersistedEncounterEventHistory();'),
  'v2 chat consumes authoritative encounter event stream and seeds persisted transcript once per room'
);
assert(
  encounterSystemSource.includes("this.bus.on('combat:round-changed', (d) => this.announceRoundChange(d))")
    && encounterSystemSource.includes("this.bus.on('combat:turn-changed',  (d) => this.announceTurnChange(d))")
    && encounterSystemSource.includes("console.info('[EncounterFlow] round_start'")
    && encounterSystemSource.includes("console.info('[EncounterFlow] turn_start'")
    && encounterSystemSource.includes("console.warn('[EncounterFlow] missing authoritative turn events'")
    && encounterSystemSource.includes('announceGameState(result?.game_state)'),
  'v2 encounter system traces round and turn transitions in the console while treating server events as authoritative'
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
  actionRailPanelSource.includes('getActionRailDirectRoute(actionType, button)')
    && actionRailPanelSource.includes("return { event: 'user:end-turn', payload: { button, actionType } };")
    && actionRailPanelSource.includes('const directRoute = this.getActionRailDirectRoute(actionType, button);')
    && encounterSystemSource.includes("this.bus.on('user:end-turn',     (d) => this.endCurrentTurn(d))")
    && encounterSystemSource.includes("const requestedActionType = String(data?.actionType || '').trim().toLowerCase();")
    && encounterSystemSource.includes("actionType = availableActions.includes('choose_not_to_act') ? 'choose_not_to_act' : 'end_turn';")
    && encounterSystemSource.includes('async endCurrentTurn(data = {})'),
  'v2 End Turn uses the server game-action API and honors explicit turn action types from the action rail'
);

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
