/**
 * @file
 * Contract coverage for mode-aware room-chat response schema.
 *
 * Run with:
 *   node tests/room_chat_response_schema_mode_contract_test.js
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

const schemaPath = path.resolve(__dirname, '../config/schemas/room_chat_response.schema.json');
const schema = JSON.parse(fs.readFileSync(schemaPath, 'utf8'));
const required = Array.isArray(schema.required) ? schema.required : [];
const responseMode = schema.properties?.response_mode || {};

console.log('\n=== Room chat response schema mode contract ===');

assert(
  required.includes('response_mode') && !required.includes('dungeon_data'),
  'Schema requires response_mode and does not require dungeon_data for non-legacy payloads'
);
assert(
  responseMode.type === 'string'
    && Array.isArray(responseMode.enum)
    && responseMode.enum.includes('legacy')
    && responseMode.enum.includes('dual_transition')
    && responseMode.enum.includes('actor_scoped'),
  'Schema constrains response_mode to explicit legacy/dual_transition/actor_scoped values'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

