/**
 * @file
 * Regression coverage for normalized chat session access control contracts.
 *
 * Run with:
 *   node tests/chat_session_contract_test.js
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

const controllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/ChatSessionController.php'),
  'utf8'
);
const roomChatSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceChannelAndSessionTrait.php'),
  'utf8'
);
const roomChatAccessGuardSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChat/RoomChatAccessGuard.php'),
  'utf8'
);

console.log('\n=== Chat session contract ===');

assert(
  controllerSource.includes("!$this->chatService->hasCampaignAccess($campaign_id) || !$this->chatService->hasCharacterAccess($campaign_id, $character_id)"),
  'character-scoped transcript endpoints require both campaign access and character ownership'
);
assert(
  controllerSource.includes("if (!in_array($session_type, ['party', 'gm_private'], TRUE)) {"),
  'normalized session writes are limited to explicitly writable session types'
);
assert(
  controllerSource.includes("Direct writes are not allowed for this session type."),
  'session write bypasses now fail with an explicit rejection message'
);
assert(
  controllerSource.includes('buildSystemLogDedupeKey(')
    && controllerSource.includes("$source_id = (int) ($message['source_message_id'] ?? 0);")
    && controllerSource.includes("return 'source:' . $source_id;"),
  'system-log collection dedupes feed-copy rows by canonical source_message_id'
);
assert(
  roomChatSource.includes('public function hasCharacterAccess(int $campaign_id, int $character_id): bool'),
  'RoomChatService exposes a reusable character-ownership helper'
);
assert(
  roomChatAccessGuardSource.includes("->condition('id', $character_id)")
    && roomChatAccessGuardSource.includes("->condition('character_id', $character_id)"),
  'character ownership matches both campaign-character row ids and canonical character ids'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
