/**
 * @file
 * Unit tests for HexCanvas coordinate math and hex grid logic.
 *
 * Run with:
 *   node tests/hex_canvas_test.js
 *
 * Does NOT test PIXI rendering (no DOM/PIXI available in Node).
 * Tests the pure math functions: axialToPixel, pixelToAxial, roundAxial.
 */

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${msg}`);
  } else {
    failed++;
    console.error(`  ✗ ${msg}`);
    console.error(`    condition: ${condition}`);
  }
}

function assertClose(a, b, msg, tolerance = 0.001) {
  assert(Math.abs(a - b) < tolerance, `${msg} (got ${a}, expected ~${b})`);
}

// Load HexCanvas source (ES module → CJS via Function wrapper).
const fs = require('fs');
const path = require('path');

let src = fs.readFileSync(path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js'), 'utf8');
// Strip global PIXI comment and export keyword
src = src.replace(/^\/\* global PIXI \*\//m, '');
src = src.replace(/^export\s+/gm, '');

// We need a minimal stub class that exposes the math methods without PIXI.
// Extract just HexCanvas class and instantiate with a dummy container/bus/config.
const HexCanvas = new Function(src + '\nreturn HexCanvas;')();

/** Factory for a math-only instance (no DOM, no PIXI calls) */
function makeCanvas(hexSize = 30) {
  const instance = Object.create(HexCanvas.prototype);
  instance.config = { hexSize, gridWidth: 20, gridHeight: 20, minZoom: 0.5, maxZoom: 3.0, backgroundColor: 0 };
  instance.container = null;
  instance.bus = null;
  instance.app = null;
  instance.hexContainer = null;
  instance.hudContainer = null;
  instance._roomBanner = null;
  instance._unsubs = [];
  return instance;
}

// ---------------------------------------------------------------------------
// axialToPixel — flat-top hexagons
// ---------------------------------------------------------------------------
console.log('\n=== axialToPixel ===');
{
  const c = makeCanvas(30);

  // Origin hex
  const origin = c.axialToPixel(0, 0);
  assert(origin.x === 0 && origin.y === 0, 'origin (0,0) maps to pixel (0,0)');

  // q=1, r=0 — flat-top: x = size * 1.5, y = size * (sqrt(3)/2)
  const q1 = c.axialToPixel(1, 0);
  assertClose(q1.x, 45, 'q=1,r=0 x = hexSize * 1.5 = 45');
  assertClose(q1.y, 30 * (Math.sqrt(3) / 2), 'q=1,r=0 y = hexSize * sqrt(3)/2');

  // q=0, r=1 — x=0, y = hexSize * sqrt(3)
  const r1 = c.axialToPixel(0, 1);
  assertClose(r1.x, 0, 'q=0,r=1 x=0');
  assertClose(r1.y, 30 * Math.sqrt(3), 'q=0,r=1 y = hexSize * sqrt(3)');

  // Negative coordinates are symmetric
  const neg = c.axialToPixel(-1, 0);
  assertClose(neg.x, -45, 'q=-1,r=0 x = -45');
}

// ---------------------------------------------------------------------------
// roundAxial
// ---------------------------------------------------------------------------
console.log('\n=== roundAxial ===');
{
  const c = makeCanvas(30);

  // Exact integer coordinates round to themselves
  const exact = c.roundAxial(2, -1);
  assert(exact.q === 2 && exact.r === -1, 'exact integer axial (2,-1) unchanged');

  // Fractional very close to (0,0)
  const near = c.roundAxial(0.1, 0.1);
  assert(near.q === 0 && near.r === 0, 'fractional (0.1,0.1) rounds to (0,0)');

  // Fractional very close to (1,0)
  const near1 = c.roundAxial(0.9, 0.05);
  assert(near1.q === 1 && near1.r === 0, 'fractional (0.9,0.05) rounds to (1,0)');
}

// ---------------------------------------------------------------------------
// pixelToAxial round-trip: axialToPixel → pixelToAxial should return original
// ---------------------------------------------------------------------------
console.log('\n=== pixelToAxial round-trip ===');
{
  const c = makeCanvas(30);

  const coords = [
    { q: 0, r: 0 }, { q: 1, r: 0 }, { q: 0, r: 1 }, { q: -2, r: 3 },
    { q: 3, r: -2 }, { q: -1, r: -1 }, { q: 5, r: 5 },
  ];

  for (const { q, r } of coords) {
    const { x, y } = c.axialToPixel(q, r);
    const back = c.pixelToAxial(x, y);
    assert(back.q === q && back.r === r, `round-trip (${q},${r})`);
  }
}

// ---------------------------------------------------------------------------
// axialToPixel with custom hexSize
// ---------------------------------------------------------------------------
console.log('\n=== axialToPixel with hexSize=50 ===');
{
  const c = makeCanvas(50);
  const p = c.axialToPixel(1, 0, 50);
  assertClose(p.x, 75, 'hexSize=50, q=1 → x=75');
}

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------
console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
} else {
  console.log('ALL TESTS PASSED');
}
