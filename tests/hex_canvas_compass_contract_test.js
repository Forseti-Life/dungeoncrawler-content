/**
 * @file
 * Contract test: compass rose labels should be equidistant from the center.
 *
 * This is a source-level invariant test (no DOM/PIXI in Node).
 * Run with:
 *   node tests/hex_canvas_compass_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const srcPath = path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js');
const src = fs.readFileSync(srcPath, 'utf8');

function assert(cond, msg) {
  if (!cond) {
    console.error(`  ✗ ${msg}`);
    process.exit(1);
  }
  console.log(`  ✓ ${msg}`);
}

console.log('\n=== HexCanvas compass label contract ===');

// Ensure we center label placement (anchor) so text width doesn't skew radius.
assert(
  src.includes('label.anchor.set(0.5)') || src.includes('label.anchor.set(0.5,'),
  'Compass labels set anchor to center'
);

// Ensure we use a constant labelRadius, not per-label offsets.
assert(
  src.includes('const labelRadius') && src.includes('Math.cos(angle) * labelRadius') && src.includes('Math.sin(angle) * labelRadius'),
  'Compass labels use constant radius positioning'
);

console.log('OK compass label contract');
