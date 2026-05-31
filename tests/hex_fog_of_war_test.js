/**
 * @file
 * Unit tests for HexFogOfWar pure math functions.
 *
 * Run with:
 *   node tests/hex_fog_of_war_test.js
 *
 * Tests: hexDistance helper, _getVisionRange, _axialLine, _hasLineOfSight
 * (without PIXI — uses minimal stubs).
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
  }
}

// Load HexFogOfWar source (ES module → CJS via Function wrapper).
const fs = require('fs');
const path = require('path');

let src = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/canvas/HexFogOfWar.js'),
  'utf8'
);
src = src.replace(/^\/\* global PIXI \*\//m, '');
src = src.replace(/^export\s+/gm, '');
const HexFogOfWar = new Function(src + '\nreturn HexFogOfWar;')();

/** Create a HexFogOfWar instance with no PIXI dependencies */
function makeFog({ obstacles = [] } = {}) {
  // Minimal HexCanvas stub — no PIXI needed for math tests
  const hexCanvas = {
    config: { hexSize: 30 },
    objectContainer: {
      children: obstacles.map(({ q, r }) => ({
        _entityType: 'obstacle',
        x: 45 * q,              // approximate pixel position (flat-top, size=30)
        y: 0,
      })),
    },
    fxContainer: null,
    hexContainer: null,
    axialToPixel(q, r, size = 30) {
      return { x: size * 1.5 * q, y: size * (Math.sqrt(3) / 2 * q + Math.sqrt(3) * r) };
    },
    pixelToAxial(x, y, size = 30) {
      const q = (2 / 3 * x) / size;
      const r = (-1 / 3 * x + Math.sqrt(3) / 3 * y) / size;
      return this._round(q, r);
    },
    _round(q, r) {
      const s = -q - r;
      let rq = Math.round(q); let rr = Math.round(r); const rs = Math.round(s);
      if (Math.abs(rq-q) > Math.abs(rr-r) && Math.abs(rq-q) > Math.abs(rs-s)) rq = -rr-rs;
      else if (Math.abs(rr-r) > Math.abs(rs-s)) rr = -rq-rs;
      return { q: rq, r: rr };
    },
  };

  const fog = Object.create(HexFogOfWar.prototype);
  fog.hexCanvas = hexCanvas;
  fog.bus = null;
  fog._fogOverlay = null;
  fog._unsubs = [];
  return fog;
}

// ---------------------------------------------------------------------------
// hexDistance (standalone function inside module)
// ---------------------------------------------------------------------------
console.log('\n=== _axialLine length === hex distance ===');
{
  const fog = makeFog();

  // Line from (0,0) to (3,0) should have 4 points (0…3 inclusive)
  const line = fog._axialLine(0, 0, 3, 0);
  assert(line.length === 4, 'axialLine (0,0)→(3,0) has 4 points');
  assert(line[0].q === 0 && line[0].r === 0, 'first point is origin');
  assert(line[3].q === 3 && line[3].r === 0, 'last point is target');

  // Line from (0,0) to (0,2)
  const line2 = fog._axialLine(0, 0, 0, 2);
  assert(line2.length === 3, 'axialLine (0,0)→(0,2) has 3 points');
  assert(line2[2].q === 0 && line2[2].r === 2, 'last point (0,2)');

  // Single point line
  const line3 = fog._axialLine(1, 1, 1, 1);
  assert(line3.length === 1, 'same-hex line has 1 point');
  assert(line3[0].q === 1 && line3[0].r === 1, 'single point is correct');
}

// ---------------------------------------------------------------------------
// _getVisionRange
// ---------------------------------------------------------------------------
console.log('\n=== _getVisionRange ===');
{
  const fog = makeFog();

  const makeEntity = (perception) => ({
    getComponent: (name) => name === 'StatsComponent' ? { perception } : null,
  });

  // Default perception=0 → range=8
  assert(fog._getVisionRange(makeEntity(0)) === 8, 'perception=0 → range=8');

  // perception=8 → floor(8/4)=2 → 8+2=10
  assert(fog._getVisionRange(makeEntity(8)) === 10, 'perception=8 → range=10');

  // perception=100 → clamped to +2 → range=10
  assert(fog._getVisionRange(makeEntity(100)) === 10, 'perception=100 clamped to range=10');

  // perception=-100 → clamped to -2 → range=6
  assert(fog._getVisionRange(makeEntity(-100)) === 6, 'perception=-100 clamped to range=6');

  // null entity → default range=8
  assert(fog._getVisionRange(null) === 8, 'null entity → range=8');
}

// ---------------------------------------------------------------------------
// _hasLineOfSight (no obstacles)
// ---------------------------------------------------------------------------
console.log('\n=== _hasLineOfSight (no obstacles) ===');
{
  const fog = makeFog();

  // Same hex
  assert(fog._hasLineOfSight(0, 0, 0, 0), 'same hex has LOS');

  // Direct adjacent
  assert(fog._hasLineOfSight(0, 0, 1, 0), 'adjacent hex has LOS');
  assert(fog._hasLineOfSight(0, 0, 0, 1), 'adjacent r+1 has LOS');

  // Longer range
  assert(fog._hasLineOfSight(0, 0, 5, 0), '(0,0)→(5,0) has LOS');
  assert(fog._hasLineOfSight(0, 0, 0, 8), '(0,0)→(0,8) has LOS');
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
