/**
 * @file
 * Regression test: HexCanvas must not allow HUD/hover overlays to reflow the DOM.
 *
 * Contract:
 * - The PIXI canvas (app.view) must be inserted as the FIRST child of the
 *   [data-hexmap-canvas] container (prepend/insertBefore), not appended.
 *
 * Run with:
 *   node tests/hex_canvas_dom_attachment_test.js
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

console.log('\n=== HexCanvas DOM attachment contract ===');

assert(source.includes('if (this.container.firstChild)'), 'Guards DOM insertion on presence of firstChild');
assert(
  source.includes('insertBefore(this.app.view, this.container.firstChild)') || source.includes('this.container.prepend(this.app.view)'),
  'Inserts canvas before first child when overlays exist (insertBefore/prepend)'
);
assert(source.includes('this.container.appendChild(this.app.view);'), 'Still appends when container is empty (safe)');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
