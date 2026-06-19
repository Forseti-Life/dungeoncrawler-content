/**
 * @file
 * Regression coverage for room-chat history diagnostics on failures.
 *
 * Run with:
 *   node tests/room_chat_history_error_diagnostics_contract_test.js
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
  path.resolve(__dirname, '../src/Controller/RoomChatController.php'),
  'utf8'
);
const shellSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/GameShell.js'),
  'utf8'
);

console.log('\n=== Room chat history diagnostics contract ===');

assert(
  controllerSource.includes("Room chat history request failed: campaign={campaign_id} room={room_id} channel={channel} character_id={character_id} exception={exception} message={message}"),
  'server-side room chat history failures are logged with request context and exception details'
);
assert(
  controllerSource.includes("Room chat history request rejected: campaign={campaign_id} room={room_id} channel={channel} character_id={character_id} status={status} message={message}"),
  'server-side invalid room chat history requests are logged with status and message context'
);
assert(
  shellSource.includes("console.error('[GameShell] _loadChatHistory failed'"),
  'GameShell logs the failed room-chat history response body for browser-side diagnosis'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
