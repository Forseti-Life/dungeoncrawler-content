/**
 * @file
 * Regression coverage for UTF-8-safe room-chat JSON responses.
 *
 * Run with:
 *   node tests/room_chat_json_encoding_contract_test.js
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

console.log('\n=== Room chat JSON encoding contract ===');

assert(
  source.includes('JSON_INVALID_UTF8_SUBSTITUTE'),
  'room chat controller enables invalid UTF-8 substitution for JSON encoding'
);
assert(
  source.includes('json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)'),
  'streamed NDJSON payloads are emitted with UTF-8 substitution enabled'
);
assert(
  source.includes('$response = new JsonResponse(NULL);')
    && source.includes("$response->setEncodingOptions($response->getEncodingOptions() | JSON_INVALID_UTF8_SUBSTITUTE);")
    && source.includes('$response->setData(['),
  'JSON response wrappers set UTF-8 substitution before assigning transcript payload data'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
