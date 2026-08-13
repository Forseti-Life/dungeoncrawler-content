/**
 * @file
 * Contract test: active direct-NPC conversation should remain focused
 * until explicit pivot/end signals.
 *
 * Run with:
 *   node tests/room_chat_active_conversation_focus_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8',
);
const classifierSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceNpcInterjectionTrait.php'),
  'utf8',
);

console.log('\n=== Room chat active-conversation focus contract ===');

assert(
  source.includes('protected function shouldContinueActiveRoomConversation(')
    && source.includes('if ($this->isExplicitRoomGmAddress($player_message)) {')
    && source.includes('if ($this->looksLikeActiveRoomConversationPivot($normalized_message)) {'),
  'Active conversation continuation halts on explicit GM address and room-action pivots'
);

assert(
  source.includes("'goodbye'")
    && source.includes("'move on'")
    && source.includes("'end conversation'")
    && source.includes('return FALSE;'),
  'Active conversation continuation halts on explicit end/move-on language'
);

assert(
  classifierSource.includes("return $this->finalizeRoomIntentDecision('direct_npc_dialogue', 'active_conversation_continue', $routing_context);"),
  'Classifier keeps direct NPC dialogue focus when active-conversation continuation passes'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
