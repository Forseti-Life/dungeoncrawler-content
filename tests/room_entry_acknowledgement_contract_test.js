/**
 * @file
 * Contract test for deferred room-entry NPC acknowledgement wiring.
 *
 * Run with:
 *   node tests/room_entry_acknowledgement_contract_test.js
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

const roomChatCoreSource = fs.readFileSync(path.resolve(__dirname, '../src/Service/RoomChatServiceCoreFlowTrait.php'), 'utf8');
const controllerSource = fs.readFileSync(path.resolve(__dirname, '../src/Controller/RoomChatController.php'), 'utf8');
const routingSource = fs.readFileSync(path.resolve(__dirname, '../dungeoncrawler_content.routing.yml'), 'utf8');
const gameShellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');
const entrypointSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap-v2.js'), 'utf8');

console.log('\n=== Room entry acknowledgement contract ===');

assert(
  roomChatCoreSource.includes('public function generateRoomEntryAcknowledgement(')
    && roomChatCoreSource.includes('$room_npcs = $this->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);')
    && roomChatCoreSource.includes('$speaker_npc = $this->selectHighestCharismaNpc($room_npcs);')
    && roomChatCoreSource.includes('$messages = $this->buildNpcInterjectionMessage('),
  'RoomChatService routes room-entry acknowledgement through the canonical room NPC interjection pipeline'
);

assert(
  controllerSource.includes('public function postRoomEntryAcknowledgement(int $campaign_id, string $room_id, Request $request): JsonResponse')
    && controllerSource.includes('$result = $this->chatService->generateRoomEntryAcknowledgement(')
    && controllerSource.includes('return $this->responseMapper->buildSuccessDataResponse($result);'),
  'RoomChatController exposes a dedicated room-entry acknowledgement endpoint'
);

assert(
  routingSource.includes("dungeoncrawler_content.api.room_chat_entry_acknowledgement:")
    && routingSource.includes("path: '/api/campaign/{campaign_id}/room/{room_id}/chat/entry-acknowledgement'")
    && routingSource.includes("_controller: '\\Drupal\\dungeoncrawler_content\\Controller\\RoomChatController::postRoomEntryAcknowledgement'"),
  'routing registers the room-entry acknowledgement endpoint under the room chat API surface'
);

assert(
  gameShellSource.includes('this._pendingRoomEntryAcknowledgement = {')
    && gameShellSource.includes('this.queueRoomEntryAcknowledgement({')
    && gameShellSource.includes('void this.requestRoomEntryAcknowledgement({')
    && gameShellSource.includes("/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat/entry-acknowledgement")
    && gameShellSource.includes('void this._loadChatHistory();'),
  'GameShell triggers room-entry acknowledgement asynchronously after room chat history loads'
);

assert(
  entrypointSource.includes("import { GameShell } from './v2/GameShell.js?v="),
  'hexmap-v2 entrypoint cache-busts the room-entry acknowledgement shell update'
);

console.log('\n==========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
