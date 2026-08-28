/**
 * @file
 * Regression coverage for V2 room-topology rendering helpers in HexCanvas.
 *
 * Run with:
 *   node tests/hexmap_v2_topology_canvas_test.js
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

function extractNamedFunctionSource(source, functionName) {
  const anchor = `function ${functionName}(`;
  const start = source.indexOf(anchor);
  if (start === -1) {
    throw new Error(`Could not find function: ${functionName}`);
  }

  let braceStart = -1;
  let parenDepth = 0;
  for (let index = start; index < source.length; index++) {
    const char = source[index];
    if (char === '(') {
      parenDepth++;
    } else if (char === ')') {
      parenDepth = Math.max(0, parenDepth - 1);
    } else if (char === '{' && parenDepth === 0) {
      braceStart = index;
      break;
    }
  }

  if (braceStart === -1) {
    throw new Error(`Could not find function body: ${functionName}`);
  }

  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        return source.slice(start, index + 1);
      }
    }
  }

  throw new Error(`Could not find closing brace for function: ${functionName}`);
}

const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js');
const source = fs.readFileSync(sourcePath, 'utf8');

const defaultStyleStart = source.indexOf('const DEFAULT_HEX_STYLE =');
const defaultStyleEnd = source.indexOf('};', defaultStyleStart);
const defaultStyleSource = source.slice(defaultStyleStart, defaultStyleEnd + 2);

const factory = new Function(`
${defaultStyleSource}
${extractNamedFunctionSource(source, '_getRoomHexes')}
${extractNamedFunctionSource(source, '_resolveRoomHexStyle')}
return {
  DEFAULT_HEX_STYLE,
  _getRoomHexes,
  _resolveRoomHexStyle,
};
`);

const { DEFAULT_HEX_STYLE, _getRoomHexes, _resolveRoomHexStyle } = factory();

console.log('\n=== Hexmap V2 topology canvas helpers ===');

{
  const room = {
    hexes: [
      { q: 0, r: 0, terrain: 'stone' },
      { q: 1, r: '1', terrain: 'water' },
      { q: 'bad', r: 2 },
    ],
  };

  const roomHexes = _getRoomHexes(room);
  assert(roomHexes.length === 2, 'filters canonical room hexes down to valid coordinate records');
  assert(roomHexes[1].terrain === 'water', 'preserves room-hex payloads for later topology styling');
}

{
  const wallStyle = _resolveRoomHexStyle({
    objects: [{ object_id: 'wall_segment', category: 'wall', blocks_movement: true }],
  });
  assert(wallStyle.lineWidth === 2 && wallStyle.fillColor === 0x1f2937, 'styles wall hexes as blocked topology');

  const doorStyle = _resolveRoomHexStyle({
    objects: [{ object_id: 'oak_door', category: 'door' }],
  });
  assert(doorStyle.lineColor === 0xfbbf24, 'styles door hexes as interaction seams');

  const waterStyle = _resolveRoomHexStyle({
    terrain_type: 'water',
    objects: [],
  });
  assert(waterStyle.fillColor === 0x1d4ed8, 'styles water terrain distinctly from floor terrain');

  const defaultStyle = _resolveRoomHexStyle({ terrain: 'stone', objects: [] });
  assert(defaultStyle.fillColor === DEFAULT_HEX_STYLE.fillColor, 'falls back to the default room-floor style for plain hexes');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
