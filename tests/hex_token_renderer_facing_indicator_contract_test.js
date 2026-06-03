/**
 * @file
 * Contract test: HexTokenRenderer must show a facing indicator based on render.orientation.
 *
 * Run with:
 *   node tests/hex_token_renderer_facing_indicator_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexTokenRenderer.js');
const source = fs.readFileSync(sourcePath, 'utf8');

console.log('\n=== HexTokenRenderer facing indicator contract ===');

assert(source.includes("name = 'facing'") || source.includes("name = 'facing';"), 'Adds facing graphics child');
assert(source.includes('_setFacingIndicator'), 'Has _setFacingIndicator helper');
assert(source.includes('render?.orientation') || source.includes('render.orientation'), 'Uses render.orientation');
assert(source.includes('orientationToRadians'), 'Maps orientation tokens to radians');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
