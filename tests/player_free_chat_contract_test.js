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
const controllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/RoomChatController.php'),
  'utf8'
);

console.log('\n=== Player free chat contract ===');

const roomPostStart = controllerSource.indexOf('  protected function postPlayerRoomChatViaEncounterTalk(');
const roomPostEnd = controllerSource.indexOf('\n  /**', roomPostStart + 1);
const roomPostSource = roomPostStart >= 0
  ? controllerSource.slice(roomPostStart, roomPostEnd >= 0 ? roomPostEnd : controllerSource.length)
  : '';

assert(
  gmSubsystemSource.includes("'route' => 'free_player_room_chat'"),
  'GM subsystem exposes a distinct free-player-room-chat route'
);
assert(
  gmSubsystemSource.includes("'type' => 'room_chat'"),
  'ordinary player room messages are described as room_chat instead of canonical talk'
);
assert(
  gmSubsystemSource.includes("$this->roomChatService->postMessage("),
  'non-deterministic player room chat is posted directly through RoomChatService'
);
assert(
  gmSubsystemSource.includes("$this->coordinator->getActionAvailabilityForActor($campaign_id, $actor_id)"),
  'free player room chat resyncs actor-scoped action availability instead of unscoped encounter actions'
);
assert(
  gmSubsystemSource.includes("'_validated_encounter_room_chat' => TRUE"),
  'free player room chat marks the internal validated encounter-room-chat bypass flag'
);
assert(
  gmSubsystemSource.includes("'defer_npc_interjections' => TRUE"),
  'free player room chat keeps NPC replies deferred so NPC dialogue remains turn-locked'
);
assert(
  gmSubsystemSource.includes("'route' => 'deterministic_turn_control'"),
  'deterministic turn-control chat routing remains available'
);
assert(
  roomChatSource.includes('$validated_encounter_room_chat')
    && roomChatSource.includes('$skip_encounter_turn_validation = $validated_encounter_talk || $validated_encounter_room_chat;'),
  'RoomChatService bypasses the turn-lock gate for validated free encounter room chat'
);
assert(
  roomChatSource.includes("&& !$validated_encounter_room_chat"),
  'RoomChatService only enforces canonical encounter-talk transport when the free-chat bypass flag is absent'
);
assert(
  controllerSource.includes('$suppress_gm,\n      $speaker'),
  'RoomChatController forwards the speaker name into the GM subsystem for free player chat'
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
