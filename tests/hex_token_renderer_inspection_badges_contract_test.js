/**
 * @file
 * Contract test: hovering a hex must expose object-level attributes visually.
 *
 * We render small per-token attribute badges (P/B/M/C/S) on hover so the user can
 * audit hex objects directly on the map.
 *
 * Run with:
 *   node tests/hex_token_renderer_inspection_badges_contract_test.js
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

console.log('\n=== HexTokenRenderer hover inspection badges contract ===');

assert(source.includes("getChildByName?.('attrBadges')") || source.includes("getChildByName('attrBadges')"), 'Uses attrBadges container');
assert(source.includes("this.bus.on('hex:hovered'"), 'Subscribes to hex:hovered');
assert(source.includes('passable'), 'References passable');
assert(source.includes('blocks_movement'), 'References blocks_movement');
assert(source.includes('movable'), 'References movable');
assert(source.includes('collectible'), 'References collectible');
assert(source.includes('stackable'), 'References stackable');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
