/**
 * @file
 * Regression coverage for fail-soft encounter progress decoration.
 *
 * Run with:
 *   node tests/room_chat_progress_fallback_contract_test.js
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
  path.resolve(__dirname, '../src/Controller/RoomChatController.php'),
  'utf8'
);

console.log('\n=== Room chat progress fallback contract ===');

assert(
  source.includes("Encounter progress snapshot fallback: campaign={campaign_id} message={message}"),
  'progress snapshot lookup logs and falls back instead of throwing'
);
assert(
  source.includes("Encounter progress prefix fallback: campaign={campaign_id} message={message}"),
  'progress prefix lookup logs and falls back instead of throwing'
);
assert(
  source.includes('catch (\\Throwable $e) {'),
  'controller explicitly catches throwable progress lookup failures'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
