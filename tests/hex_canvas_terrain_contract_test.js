/**
 * @file
 * Contract test: HexCanvas must use terrain_type as the sole per-hex terrain field.
 *
 * We do not support multiple terrain field names (no tile_type, no roomHex.terrain).
 * This keeps the HexMap visual-state contract strict and prevents drift.
 *
 * Run with:
 *   node tests/hex_canvas_terrain_contract_test.js
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

console.log('\n=== HexCanvas terrain field contract ===');

assert(source.includes('roomHex?.terrain_type'), 'Uses roomHex.terrain_type');
assert(!source.includes('roomHex?.terrain ||'), 'Does not fallback to roomHex.terrain');
assert(!source.includes('tile_type'), 'Does not reference tile_type');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
