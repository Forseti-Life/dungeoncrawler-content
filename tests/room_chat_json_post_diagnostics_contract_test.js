/**
 * @file
 * Regression coverage for non-stream room chat POST diagnostics.
 *
 * Run with:
 *   node tests/room_chat_json_post_diagnostics_contract_test.js
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
const panelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'),
  'utf8'
);

console.log('\n=== Room chat JSON POST diagnostics contract ===');

assert(
  controllerSource.includes('Room chat POST failed [{debug_id}]')
    && controllerSource.includes('Room chat POST rejected [{debug_id}]'),
  'room chat POST controller logs both rejected and unexpected JSON-path failures with debug ids'
);
assert(
  controllerSource.includes("'stream_mode' => 'json_post'"),
  'room chat POST debug payload explicitly marks the JSON transport mode'
);
assert(
  controllerSource.includes("'exception_class' => get_class($e),")
    && controllerSource.includes("'message' => $e->getMessage(),"),
  'room chat POST debug payload includes the server exception class and message'
);
assert(
  panelSource.includes("const debugId = String(result?.debug?.debug_id || '').trim();"),
  'ChatPanel reads JSON POST debug ids from the response payload'
);
assert(
  panelSource.includes("console.error('[RoomChat] JSON POST debug'"),
  'ChatPanel logs the JSON POST debug payload when server diagnostics are returned'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
