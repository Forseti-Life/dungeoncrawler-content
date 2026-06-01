const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const coordinator = fs.readFileSync(path.join(root, 'src/Service/GameCoordinatorService.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'src/Controller/GameCoordinatorController.php'), 'utf8');
const browserCoordinator = fs.readFileSync(path.join(root, 'js/game-coordinator/GameCoordinator.js'), 'utf8');
const phaseManager = fs.readFileSync(path.join(root, 'js/game-coordinator/PhaseManager.js'), 'utf8');
const coordinatorApi = fs.readFileSync(path.join(root, 'js/game-coordinator/GameCoordinatorApi.js'), 'utf8');
const encounterHandler = fs.readFileSync(path.join(root, 'src/Service/EncounterPhaseHandler.php'), 'utf8');
const campaignClock = fs.readFileSync(path.join(root, 'src/Service/CampaignClockService.php'), 'utf8');
const campaignTimeResolver = fs.readFileSync(path.join(root, 'src/Service/CampaignTimeResolverService.php'), 'utf8');
const explorationHandler = fs.readFileSync(path.join(root, 'js/game-coordinator/phases/ExplorationPhaseHandler.js'), 'utf8');
const navigationSystem = fs.readFileSync(path.join(root, 'js/v2/systems/NavigationSystem.js'), 'utf8');
const chatPanel = fs.readFileSync(path.join(root, 'js/v2/panels/ChatPanel.js'), 'utf8');
const install = fs.readFileSync(path.join(root, 'dungeoncrawler_content.install'), 'utf8');
const docs = fs.readFileSync(path.join(root, 'GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md'), 'utf8');
const readme = fs.readFileSync(path.join(root, 'README.md'), 'utf8');

let passed = 0;
let failed = 0;

function assert(condition, label) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${label}`);
  } else {
    failed++;
    console.log(`  ✗ FAIL: ${label}`);
  }
}

console.log('\n=== Deprecated exploration phase guardrails ===');

assert(
  coordinator.includes("protected const DEFAULT_ACTIVE_PHASE = 'encounter';")
    && coordinator.includes("'phase' => self::DEFAULT_ACTIVE_PHASE"),
  'new game state defaults to encounter, not exploration'
);

assert(
  coordinator.includes("protected const DEPRECATED_PHASES = ['exploration'];")
    && !coordinator.includes("$this->phaseHandlers['exploration'] = $exploration_handler;"),
  'ExplorationPhaseHandler remains available for reuse but is not registered as an active phase'
);

assert(
  coordinator.includes("Phase '$target_phase' is deprecated and disabled")
    && coordinator.includes("'encounter' => []")
    && !coordinator.includes("'encounter' => ['exploration']")
    && !coordinator.includes("'downtime' => ['encounter']"),
  'transition endpoint rejects exploration and the live runtime no longer advertises downtime transitions'
);

assert(
  coordinator.includes('enterRoomFramework(NULL, $room_id')
    && encounterHandler.includes("GameEventLogger::buildEvent('room_entered', 'encounter'")
    && !coordinator.includes("GameEventLogger::buildEvent('room_entered', 'exploration'"),
  'coordinator bootstraps initial room entry through encounter framework, not exploration events'
);

assert(
  !coordinator.includes("'phase' => $game_state['phase'] ?? 'exploration'")
    && !coordinator.includes("'exploration' => $game_state['exploration'] ?? NULL"),
  'client game-state payload no longer defaults or exposes an active exploration phase'
);

assert(
  controller.includes('Manually transition to a supported game phase.')
    && !controller.includes('"target_phase": "downtime",'),
  'transition endpoint documentation no longer presents downtime-era examples'
);

assert(
  browserCoordinator.includes('this.deprecatedExplorationActions = new ExplorationPhaseHandler(deps);')
    && !browserCoordinator.includes('this.phaseHandlers.exploration = new ExplorationPhaseHandler(deps);')
    && !browserCoordinator.includes('this.phaseHandlers.downtime = new DowntimePhaseHandler(deps);')
    && !phaseManager.includes("encounter: ['downtime']")
    && coordinatorApi.includes('Target phase (currently encounter only)'),
  'legacy browser coordinator also keeps exploration helper code out of active phase registration'
);

assert(
  docs.includes('`ExplorationPhaseHandler` is deprecated for active runtime routing')
    && docs.includes('Room entry immediately uses the encounter framework.')
    && readme.includes('The exploration phase is deprecated and disabled for active runtime routing;'),
  'documentation states exploration phase is deprecated and inactive'
);

assert(
  encounterHandler.includes("'treat_wounds'")
    && encounterHandler.includes("'refocus'")
    && encounterHandler.includes("'repair'")
    && encounterHandler.includes("'daily_preparations'")
    && encounterHandler.includes("safe_for_rest")
    && encounterHandler.includes('isSafeRestAvailable'),
  'encounter runtime exposes safe-room rest actions instead of a downtime phase'
);

assert(
  coordinator.includes("protected const DEFAULT_ACTIVE_PHASE = 'encounter';")
    && encounterHandler.includes("'transition' => [")
    && encounterHandler.includes("Room transition requires params.target_room_id."),
  'encounter phase owns canonical transition action contract'
);

assert(
  encounterHandler.includes("GameEventLogger::buildEvent('round_start'")
    && encounterHandler.includes("GameEventLogger::buildEvent('turn_start'")
    && encounterHandler.includes("'choose_not_to_act' => [")
    && encounterHandler.includes('buildRoomEncounterTurnOrder')
    && encounterHandler.includes('passRoomActorTurn'),
  'every room encounter has round/turn logging and explicit no-action choices'
);

assert(
  !explorationHandler.includes("'room_transition'")
    && !explorationHandler.includes('"room_transition"')
    && explorationHandler.includes("_notifyServer('transition'")
    && navigationSystem.includes("sendAction('transition'")
    && !navigationSystem.includes('tryTransitionAtHex?.(')
    && !navigationSystem.includes('navigateToVisitedRoom?.('),
  'reachable browser room navigation sends canonical transition without local authoritative room mutation'
);

assert(
  chatPanel.includes("window.addEventListener('dungeoncrawler:game-events'")
    && chatPanel.includes('formatEncounterChatMessage')
    && chatPanel.includes('Round ${context.round}: Actor ${context.actorName}:')
    && chatPanel.includes('renderPersistedEncounterEventHistory')
    && chatPanel.includes('/api/game/${encodeURIComponent(context.campaignId)}/events?since=0')
    && chatPanel.includes("type === 'round_start'")
    && chatPanel.includes("type === 'turn_start'")
    && chatPanel.includes("type === 'choose_not_to_act' || type === 'npc_choose_not_to_act' || type === 'end_turn'"),
  'chat transcript renders visible round/actor prefixes and persisted encounter turn events'
);

assert(
  encounterHandler.includes("'action_type' => 'encounter_round'")
    && encounterHandler.includes("'duration_seconds' => 6 * $round_advances")
    && encounterHandler.includes("'action_type' => 'room_transition'")
    && encounterHandler.includes('DEFAULT_ROOM_TRANSITION_SECONDS = 60')
    && campaignClock.includes("'second' => (int) $date_time->format('s')")
    && campaignTimeResolver.includes("'duration_seconds' => $total_seconds"),
  'campaign time advances by encounter rounds and server-side room travel duration'
);


console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
console.log('ALL TESTS PASSED');
