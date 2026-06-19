/**
 * @file
 * Regression coverage for room chat transport mode selection.
 *
 * Run with:
 *   node tests/room_chat_transport_contract_test.js
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
  path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'),
  'utf8'
);

console.log('\n=== Room chat transport contract ===');

assert(
  source.includes("const roomChannel = (chatTarget.channelKey || 'room') === 'room';"),
  'room chat transport explicitly detects the main room channel'
);
assert(
  source.includes('const useStreamTransport = shouldStream && !roomChannel;'),
  'main room chat is kept on the non-stream JSON transport while other channels may still stream'
);
assert(
  source.includes("if (useStreamTransport && contentType.includes('application/x-ndjson') && response.body?.getReader) {"),
  'NDJSON consumption only activates when stream transport is actually enabled'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
