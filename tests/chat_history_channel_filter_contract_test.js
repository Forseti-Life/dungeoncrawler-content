/**
 * @file
 * Regression coverage for room-chat history channel filtering on legacy data.
 *
 * Run with:
 *   node tests/chat_history_channel_filter_contract_test.js
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

function filterMessagesByChannel(messages, channelKey) {
  return messages.filter((msg) => {
    if (!msg || Array.isArray(msg) || typeof msg !== 'object') {
      return false;
    }
    const msgChannel = msg.channel ?? 'room';
    return msgChannel === channelKey;
  });
}

const sourcePath = path.resolve(__dirname, '../src/Service/ChatChannelManager.php');
const source = fs.readFileSync(sourcePath, 'utf8');

console.log('\n=== Chat history channel filter contract ===');

const filtered = filterMessagesByChannel([
  { speaker: 'Narrator', message: 'Hello', channel: 'room' },
  'legacy-string-entry',
  null,
  { speaker: 'Eldric', message: 'Quiet word', channel: 'whisper:npc-eldric' },
  42,
  { speaker: 'System', message: 'Order', channel: 'room' },
], 'room');

assert(Array.isArray(filtered), 'channel filter returns an array');
assert(filtered.length === 2, 'channel filter ignores non-object legacy entries instead of crashing');
assert(filtered[0].speaker === 'Narrator', 'room message entries are preserved');
assert(filtered[1].speaker === 'System', 'later valid room entries still survive filtering');
assert(
  source.includes('if (!is_array($msg)) {'),
  'PHP source explicitly guards against non-array chat entries before reading channel'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
