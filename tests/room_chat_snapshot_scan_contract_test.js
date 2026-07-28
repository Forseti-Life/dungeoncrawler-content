/**
 * @file
 * Regression coverage for room-chat snapshot scanning across legacy dungeon rows.
 *
 * Run with:
 *   node tests/room_chat_snapshot_scan_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RoomChatServiceGmPipelineTrait.php'),
  'utf8'
);

console.log('\n=== Room chat snapshot scan contract ===');

assert(
  source.includes("$candidate_data = json_decode($candidate_record['dungeon_data'] ?? '{}', TRUE);"),
  'snapshot scanner decodes candidate dungeon rows before room matching'
);
assert(
  source.includes('if (!is_array($candidate_data)) {'),
  'snapshot scanner skips malformed legacy dungeon payloads instead of reading room offsets from scalars'
);
assert(
  source.includes('Room snapshot scan skipped malformed dungeon payload: campaign={campaign_id} requested_room={room_id} dungeon_id={dungeon_id} payload_bytes={payload_bytes} decoded_type={decoded_type}'),
  'snapshot scanner logs an explicit warning when it skips a malformed legacy dungeon payload'
);
assert(
  source.includes("throw new \\InvalidArgumentException(sprintf('Room %s not found in any dungeon', $room_id), 404);"),
  'snapshot scanner still fails with a 404 when no valid row contains the requested room'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
