/**
 * @file
 * Regression coverage for visible room-GM transcript identity.
 *
 * Run with:
 *   node tests/room_gm_narrator_contract_test.js
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
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);

console.log('\n=== Room GM narrator contract ===');

assert(
  source.includes("$this->buildEncounterPrefixForSpeaker($dungeon_data, 'Narrator')"),
  'visible room GM narration uses the Narrator encounter prefix instead of Game Master'
);
assert(
  source.includes("'speaker' => 'Narrator',\n      'message' => $visible_gm_narrative,\n      'type' => 'narrator'"),
  'legacy room chat stores visible GM replies as Narrator narrator-lines'
);
assert(
  source.includes("'Narrator',\n        'narrator',"),
  'normalized room session bridge writes visible GM replies as narrator/narrative'
);
assert(
  source.includes("'speaker' => 'Narrator'"),
  'GM room response payload advertises Narrator as the visible speaker'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
