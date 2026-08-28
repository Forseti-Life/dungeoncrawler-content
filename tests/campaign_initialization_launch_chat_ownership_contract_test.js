/**
 * @file
 * Contract test: campaign initialization must not seed visible starter-room
 * narration ahead of authoritative coordinator launch bootstrap.
 *
 * Run with:
 *   node tests/campaign_initialization_launch_chat_ownership_contract_test.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../src/Service/CampaignInitializationService.php'),
    'utf8',
  );

  assert(
    source.includes('ensureRoomSession('),
    'campaign initialization should still provision the starter room chat session',
  );

  assert(
    !source.includes('seedStarterRoomChatHistory(')
      && !source.includes('buildStarterRoomSeedNarration(')
      && !source.includes("['event' => 'room_enter', 'room_id' => $room_id]"),
    'campaign initialization should no longer seed visible room-enter narration ahead of coordinator bootstrap',
  );

  console.log('OK campaign initialization launch chat ownership contract');
})();
