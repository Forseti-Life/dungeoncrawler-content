/**
 * @file
 * Regression coverage for queued room-chat compatibility in the V2 chat panel.
 *
 * Run with:
 *   node tests/chat_panel_queue_contract_test.js
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

console.log('\n=== ChatPanel queued room chat contract ===');

assert(
  source.includes('this.roomChatDeferredMessages.push({'),
  'busy room chat now queues deferred messages instead of hard-failing'
);
assert(
  source.includes('this.updateQueuedChatStatus(this.roomChatDeferredMessages.length);'),
  'queued room chat updates the visible queue status'
);
assert(
  source.includes('queued: true'),
  'queued room chat returns an explicit queued response payload'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
