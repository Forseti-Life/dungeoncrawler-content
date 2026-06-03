/**
 * @file
 * Contract test: HexCanvas hover must work on every hex and show hex attributes.
 *
 * Run with:
 *   node tests/hex_canvas_hover_hex_attributes_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js');
const source = fs.readFileSync(sourcePath, 'utf8');

console.log('\n=== HexCanvas hover hex attributes contract ===');

assert(source.includes("hex.eventMode = 'static'"), 'Hex graphics use eventMode=static for pointer events');
assert(source.includes('_showHexHoverInfo'), 'Has _showHexHoverInfo helper');
assert(source.includes('roomHex?.terrain_type'), 'Hover tooltip references terrain_type');
assert(source.includes('roomHex?.lighting'), 'Hover tooltip references lighting');
assert(source.includes('roomHex?.elevation_ft'), 'Hover tooltip references elevation_ft');
assert(source.includes('roomHex?.is_visible'), 'Hover tooltip references is_visible');
assert(source.includes('roomHex?.is_discovered'), 'Hover tooltip references is_discovered');
assert(source.includes('roomHex?.is_entry'), 'Hover tooltip references is_entry');
assert(source.includes('roomHex?.hex_id'), 'Hover tooltip references hex_id');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
