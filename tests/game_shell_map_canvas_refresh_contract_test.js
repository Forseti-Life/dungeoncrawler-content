/**
 * @file
 * Contract test: map-tab activation must refresh V2 canvas viewport geometry.
 *
 * Run with:
 *   node tests/game_shell_map_canvas_refresh_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const gameShellSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/GameShell.js'),
  'utf8'
);
const hexCanvasSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js'),
  'utf8'
);

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

console.log('\n=== GameShell map canvas refresh contract ===');

assert(
  gameShellSource.includes("if (tabId === 'map') this._refreshMapCanvasViewport();"),
  'tab-change handler refreshes canvas viewport when map tab activates'
);
assert(
  gameShellSource.includes('_refreshMapCanvasViewport()'),
  'GameShell exposes a dedicated map viewport refresh helper'
);
assert(
  gameShellSource.includes('window.requestAnimationFrame(() => {\n        window.requestAnimationFrame(applyResize);'),
  'viewport refresh waits for map-panel visibility before resizing canvas'
);
assert(
  gameShellSource.includes('canvas.resizeToContainer();'),
  'GameShell delegates viewport recompute to HexCanvas.resizeToContainer()'
);
assert(
  hexCanvasSource.includes('resizeToContainer()'),
  'HexCanvas exposes resizeToContainer()'
);
assert(
  hexCanvasSource.includes('this.app.renderer.resize(width, height);'),
  'HexCanvas resize path updates PIXI renderer dimensions'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
