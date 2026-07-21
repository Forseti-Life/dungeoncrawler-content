/**
 * @file
 * Regression coverage for player free-speech encounter room chat routing.
 *
 * Run with:
 *   node tests/player_free_chat_contract_test.js
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

const gmSubsystemSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameMasterSubsystemService.php'),
  'utf8'
);
const roomChatSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const roomChatCoreFlowSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'),
  'utf8'
);
const roomChatNpcInterjectionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcInterjectionTrait.php'),
  'utf8'
);
const gmActorRuntimeSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorRuntimeService.php'),
  'utf8'
);
const gmActorTransportSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorChatTransportService.php'),
  'utf8'
);
const gmActorHarnessSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmActorHarnessService.php'),
  'utf8'
);
const writeOrchestratorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatWriteEndpointOrchestrator.php'),
  'utf8'
);

console.log('\n=== Player free chat contract ===');

const roomPostStart = writeOrchestratorSource.indexOf('  public function postPlayerRoomChatViaEncounterTalk(');
const roomPostEnd = writeOrchestratorSource.indexOf('\n  public function continueQueuedRoomConversation(', roomPostStart + 1);
const roomPostSource = roomPostStart >= 0
  ? writeOrchestratorSource.slice(roomPostStart, roomPostEnd >= 0 ? roomPostEnd : writeOrchestratorSource.length)
  : '';

assert(
  gmSubsystemSource.includes("protected const ROUTE_FREE_PLAYER_ROOM_CHAT = 'free_player_room_chat';")
    && gmSubsystemSource.includes('self::ROUTE_FREE_PLAYER_ROOM_CHAT'),
  'GM subsystem exposes a distinct free-player-room-chat route'
);
assert(
  gmSubsystemSource.includes("'type' => 'room_chat'"),
  'ordinary player room messages are described as room_chat instead of canonical talk'
);
assert(
  gmSubsystemSource.includes("$this->gmActorHarness->handlePlayerRoomChat("),
  'GM subsystem delegates non-deterministic room chat through GM actor harness'
);
assert(
  gmActorRuntimeSource.includes('$this->chatTransport->postValidatedPlayerRoomChat(')
    && gmActorTransportSource.includes("$this->roomChatService->postMessage("),
  'GM actor runtime posts non-deterministic room chat through the dedicated GM transport adapter'
);
assert(
  gmActorRuntimeSource.includes("$this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id)"),
  'GM actor runtime resyncs actor-scoped action availability instead of unscoped encounter actions'
);
assert(
  gmActorTransportSource.includes("'_validated_encounter_room_chat' => TRUE"),
  'GM transport marks the internal validated encounter-room-chat bypass flag'
);
assert(
  gmActorTransportSource.includes('$defer_npc_interjections = FALSE;'),
  'GM transport resolves NPC replies immediately instead of deferring them behind turn order'
);
assert(
  gmActorHarnessSource.includes('HARNESS_CONTRACT_VERSION')
    && gmActorHarnessSource.includes('gm-actor-harness-v1'),
  'GM actor harness exposes a stable harness contract version'
);
assert(
  gmSubsystemSource.includes("protected const ROUTE_DETERMINISTIC_TURN_CONTROL = 'deterministic_turn_control';")
    && gmSubsystemSource.includes('self::ROUTE_DETERMINISTIC_TURN_CONTROL'),
  'deterministic turn-control chat routing remains available'
);
assert(
  roomChatNpcInterjectionSource.includes("'suppress_visible_gm_response' => TRUE"),
  'direct NPC-facing room chat suppresses the narrator placeholder and lets NPC replies carry the exchange'
);
assert(
  roomChatCoreFlowSource.includes('$validated_encounter_room_chat')
    && roomChatCoreFlowSource.includes('$skip_encounter_turn_validation = $validated_encounter_talk || $validated_encounter_room_chat;'),
  'RoomChatService bypasses the turn-lock gate for validated free encounter room chat'
);
assert(
  roomChatCoreFlowSource.includes("&& !$validated_encounter_room_chat"),
  'RoomChatService only enforces canonical encounter-talk transport when the free-chat bypass flag is absent'
);
assert(
  roomPostSource.includes('$suppress_gm,\n      $speaker')
    || roomPostSource.includes('$suppress_gm,\n      $speaker\n    );'),
  'write orchestrator forwards the speaker name into the GM subsystem for free player chat'
);
assert(
  !roomChatCoreFlowSource.includes('hears the question and holds the floor for their turn.'),
  'room chat no longer emits the narrator wait-your-turn placeholder for addressed NPC dialogue'
);
assert(
  !roomChatSource.includes("if (($game_state['phase'] ?? '') === 'encounter') {\n      // Hard encounter loops are server-authoritative in GameCoordinator/EncounterPhaseHandler.\n      // Do not inject out-of-turn room harness chatter during encounter turns."),
  'room turn harness no longer hard-disables NPC chat replies during encounter phase'
);
assert(
  roomPostSource !== ''
    && !roomPostSource.includes('mergeDeferredNpcTurnResult(')
    && !roomPostSource.includes('$this->chatService->completeDeferredNpcInterjections('),
  'non-stream room chat does not synchronously drain deferred NPC interjections'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
