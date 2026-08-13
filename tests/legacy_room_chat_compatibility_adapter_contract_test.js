/**
 * @file
 * Contract coverage for explicit legacy room-chat compatibility adapter seam.
 *
 * Run with:
 *   node tests/legacy_room_chat_compatibility_adapter_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmSubsystem/LegacyRoomChatCompatibilityAdapter.php'),
  'utf8'
);

console.log('\n=== Legacy room-chat compatibility adapter contract ===');

assert(
  source.includes('class LegacyRoomChatCompatibilityAdapter'),
  'LegacyRoomChatCompatibilityAdapter seam exists'
);
assert(
  source.includes("'dungeon_data'")
    && source.includes("'totalMessages'")
    && source.includes("'debug_trace'")
    && source.includes("'state_diff'")
    && source.includes("'canonical_actions'"),
  'Compatibility adapter carries explicit legacy/full-state overlay keys'
);
assert(
  source.includes('buildOverlay(array $chat_result): array'),
  'Compatibility adapter exposes explicit overlay builder'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

