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
const gameShellSource = require('./helpers/js-source.js').readGameShellSource();
const actionRailPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
// actorRef/turn resolution was extracted out of the panel into a shared service.
const actionRailContextServiceSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/services/action-rail-context-service.js'), 'utf8');
// Direct-action routing now lives in a declarative contract module.
const actionRailContractSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/contracts/action-rail-contract.js'), 'utf8');
const encounterSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'), 'utf8');
const statusPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/StatusPanel.js'), 'utf8');
const chatPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'), 'utf8');
const coordinatorApiSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/GameCoordinatorApi.js'), 'utf8');
const explorationPhaseSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'), 'utf8');
const encounterPhaseSource = require('./helpers/php-source.js').readEncounterPhaseHandlerSource();

console.log('\n=== Action rail Search bindings ===');
assert(
  hexmapSource.includes('handleActionRailDirectAction(actionKey, button = null)')
    && hexmapSource.includes("if (actionKey === 'search') {\n        this.executeDirectSearch(button);"),
  'legacy action rail Search direct action executes the shared search handler'
);
// Pinning literal tokens made this assertion break on every legitimate bump.
// The durable invariant is ordering: a browser that has GameCoordinator.js
// cached under a stale token never observes a newer nested import token, so the
// outer token must never be older than the nested one it carries.
{
  const coordinatorSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/GameCoordinator.js'), 'utf8');
  const outerToken = (hexmapSource.match(/\.\/game-coordinator\/GameCoordinator\.js\?v=([0-9a-z-]+)/) || [])[1] || '';
  const innerToken = (coordinatorSource.match(/\.\/GameCoordinatorApi\.js\?v=([0-9a-z-]+)/) || [])[1] || '';
  assert(
    outerToken !== '' && innerToken !== '' && outerToken >= innerToken,
    'legacy nested coordinator imports are cache-busted when Search contracts change'
  );
}
assert(
  hexmapSource.includes("search_mode: 'explicit'")
    && hexmapSource.includes('character_id: characterId')
    && hexmapSource.includes('runtimeContext.campaignId || hexmap?.resolveCampaignId?.() || null')
    && hexmapSource.includes('|| phaseSnapshot?.actionContract?.actor_id')
    && hexmapSource.includes('|| phaseSnapshot?.turn?.entity')
    && !hexmapSource.includes('await fetch(`/api/game/${campaignId}/state`')
    && !hexmapSource.includes('explicit_search: true')
    && !hexmapSource.includes('perception_bonus: perceptionBonus')
    && !hexmapSource.includes('searches the room.'),
  'legacy explicit Search sends standardized search params without client state fallback fetches'
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
  hexmapSource.includes('resolveLaunchCharacterRuntimeContext: function ()')
    && hexmapSource.includes('campaignId: this.resolveCampaignId()')
    && hexmapSource.includes('roomId: this.resolveActiveRoomId(),'),
  'legacy runtime context resolves campaign/room ids from canonical fallback sources'
);
assert(
  actionRailContextServiceSource.includes("const contractActorRef = String(phaseSnapshot?.actionContract?.actor_id || '').trim();")
    && actionRailContextServiceSource.includes('const hasTurnScopedAction = availableActions.some((entry) => [')
    && actionRailContextServiceSource.includes("|| ((turnEnvelope.hasServerTurn && actionState.hasTurnScopedAction && turnEnvelope.serverTurnEntity) ? turnEnvelope.serverTurnEntity : '')")
    && actionRailContextServiceSource.includes('|| (Boolean(actorRef) && turnEnvelope.serverTurnEntity === actorRef);')
    && !actionRailContextServiceSource.includes('|| !actorRef'),
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
    && gameShellSource.includes('const selectedIsLaunchActor = launchCharacterId > 0 && selectedCharacterId === launchCharacterId;')
    && gameShellSource.includes('instanceId: selectedIsLaunchActor')
    && gameShellSource.includes('roomId: this.resolveActiveRoomId(),'),
  'v2 hexmap shim exposes runtime actor context so exploration Search can unlock without ECS combat state'
);
assert(
  gameShellSource.includes("import { GameCoordinator } from '../game-coordinator/GameCoordinator.js?v=")
    && gameShellSource.includes('this._initGameCoordinator();')
    && gameShellSource.includes('_initGameCoordinator() {')
    && gameShellSource.includes('this.gameCoordinator = new GameCoordinator(campaignId, hexmapShim);')
    && gameShellSource.includes('this.gameCoordinator.init()')
    && gameShellSource.includes('this.gameCoordinator?.destroy?.();'),
  'v2 shell initializes and tears down GameCoordinator so action-rail Search can dispatch coordinator actions'
);
assert(
  gameShellSource.includes('applyQuestUpdates: (questUpdates = []) => shell.applyQuestUpdates(questUpdates),')
    && gameShellSource.includes('refreshQuestJournalFromApi: () => shell.refreshQuestJournalFromApi(),')
    && gameShellSource.includes('async refreshQuestJournalFromApi(context = {}) {')
    && gameShellSource.includes('async applyQuestUpdates(questUpdates = []) {')
    && gameShellSource.includes('await this.applyQuestUpdates(questUpdates);')
    && chatPanelSource.includes('await questHexmap?.applyQuestUpdates?.(completeResult.data.quest_updates);')
    && chatPanelSource.includes('await questHexmap.refreshQuestJournalFromApi();'),
  'v2 quest tab refresh flows through hexmap shim quest-journal methods for authoritative updates'
);
assert(
  hexmapV2Source.includes("./v2/GameShell.js?v=")
    && hexmapV2Source.includes('userId: Number(settings?.user?.uid || settings?.dungeoncrawlerContent?.userId || 0)')
    && /\.\/systems\/EncounterSystem\.js\?v=/.test(gameShellSource)
    && /\.\/panels\/ChatPanel\.js\?v=/.test(gameShellSource)
    && gameShellSource.includes('this.currentUserId = Number(rawSettings.userId || rawSettings.user?.uid || 0);'),
  'v2 entrypoint cache-busts imports and forwards authenticated user id so coordinator gating stays accurate'
);
assert(
  encounterSystemSource.includes("search_mode: 'explicit'")
    && encounterSystemSource.includes('character_id: characterId')
    && encounterSystemSource.includes("this._sendCoordinatorActionWithResync(coordinator, 'search', actorRef, {")
    && encounterSystemSource.includes('|| phaseSnapshot?.actionContract?.actor_id')
    && encounterSystemSource.includes('|| phaseSnapshot?.turn?.entity')
    && encounterSystemSource.includes('await hexmap.loadCharacterFromApi?.(context.characterId);')
    && encounterSystemSource.includes('await hexmap.refreshQuestJournalFromApi?.();')
    && !encounterSystemSource.includes('await coordinator.api.getState()')
    && !encounterSystemSource.includes('explicit_search: true')
    && !encounterSystemSource.includes('perception_bonus: perceptionBonus')
    && !encounterSystemSource.includes('searches the room.'),
  'v2 explicit Search sends standardized search params and waits for refreshed character and quest journal state'
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
    && encounterPhaseSource.includes('$public_result = $this->buildPublicSearchResult($search_result);')
    && encounterPhaseSource.includes('protected function buildPublicSearchResult(array $result): array')
    && !encounterPhaseSource.includes("'roll' => $result['roll'] ?? NULL,\n          'dc' => $result['dc'] ?? NULL,\n          'degree' => $result['degree'] ?? NULL")
    && !encounterPhaseSource.includes('content\' => trim($narration)')
    && !explorationPhaseSource.includes('content\' => trim($narration)')
    && !encounterPhaseSource.includes('searches the area (Perception %d vs DC %d: %s).'),
  'Search emits only sanitized discovery events and does not queue duplicate session narration'
);
// The search-specific narration branch was generalized: every encounter event
// (search included) renders server narration verbatim, falling back to a typed
// message only when the server supplied none. No-discovery feedback therefore
// still reaches chat, via the same path.
assert(
  chatPanelSource.includes("const narration = typeof event.narration === 'string' ? event.narration.trim() : '';")
    && chatPanelSource.includes('const message = narration || this.buildEncounterEventFallbackMessage(type, data, actorName, event);')
    && chatPanelSource.includes("if (eventType === 'search') {"),
  'v2 chat renders Search narration events, including explicit no-discovery feedback'
);
assert(
  chatPanelSource.includes("window.addEventListener('dungeoncrawler:game-events', this._handleGameEvents);")
    && chatPanelSource.includes("window.removeEventListener('dungeoncrawler:game-events', this._handleGameEvents);")
    && chatPanelSource.includes('this._roomHistoryHasEncounterTranscript')
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
    && statusPanelSource.includes('Still waiting; the backend may be busy.')
    && statusPanelSource.includes("this.container?.querySelector?.(`.map-initiative-status [data-status=\"${statusKey}\"]`)")
    && statusPanelSource.includes('this._dockBackendWaitIntoInitiativeStatus();')
    && statusPanelSource.includes("const existing = this.container?.querySelector?.('.map-initiative-status')")
    && statusPanelSource.includes("host.className = 'map-initiative-status';")
    && statusPanelSource.includes("const list = tracker.querySelector('.initiative-list');")
    && statusPanelSource.includes('tracker.insertBefore(host, list);'),
  'v2 status panel shows backend wait cues and docks the wait banner under the initiative status block'
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
    && actionRailContractSource.includes("end_turn: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } }),")
    && actionRailContractSource.includes("choose_not_to_act: (button, actionType) => ({ event: 'user:end-turn', payload: { button, actionType } }),")
    && actionRailPanelSource.includes('const directRoute = getActionRailDirectRoute(actionType, button);')
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
