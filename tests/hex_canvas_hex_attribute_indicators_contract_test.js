/**
 * @file
 * Contract test: HexCanvas should render visual indicators for key roomHex attributes.
 *
 * Run with:
 *   node tests/hex_canvas_hex_attribute_indicators_contract_test.js
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

console.log('\n=== HexCanvas hex attribute indicator contract ===');

assert(source.includes('_renderHexAttributeIndicators'), 'Has _renderHexAttributeIndicators helper');
assert(source.includes('this.propsContainer.addChild'), 'Adds indicators into propsContainer layer');
assert(source.includes('roomHex?.is_entry'), 'References is_entry');
assert(source.includes('roomHex?.is_visible'), 'References is_visible');
assert(source.includes('roomHex?.is_discovered'), 'References is_discovered');
assert(source.includes('roomHex?.objects') || source.includes('roomHex.objects'), 'References objects for object count');
assert(source.includes('roomHex?.elevation_ft'), 'References elevation_ft');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
