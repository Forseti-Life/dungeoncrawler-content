/**
 * @file
 * Contract test: legacy hexmap (map tab) must render always-on per-hex indicators.
 *
 * Run with:
 *   node tests/hexmap_legacy_hex_attribute_indicators_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/hexmap.js');
const source = fs.readFileSync(sourcePath, 'utf8');

console.log('\n=== Legacy HexMap hex indicator contract ===');

assert(source.includes('renderHexAttributeIndicatorsForActiveRoom'), 'Has renderHexAttributeIndicatorsForActiveRoom');
assert(source.includes('_renderHexAttributeIndicatorsAt'), 'Has _renderHexAttributeIndicatorsAt helper');
assert(source.includes('paintActiveRoom') && source.includes('this.renderHexAttributeIndicatorsForActiveRoom'), 'paintActiveRoom triggers indicator render');

assert(source.includes('roomHex?.is_entry'), 'References is_entry');
assert(source.includes('roomHex?.is_visible'), 'References is_visible');
assert(source.includes('roomHex?.is_discovered'), 'References is_discovered');
assert(source.includes('roomHex?.objects') || source.includes('roomHex.objects'), 'References objects for object count');
assert(source.includes('roomHex?.elevation_ft'), 'References elevation_ft');

assert(source.includes('showHexIndicatorLegend'), 'Has HUD legend helper');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
