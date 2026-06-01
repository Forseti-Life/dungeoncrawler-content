/**
 * @file
 * Regression coverage for HexFogOfWar lifecycle state.
 *
 * Run with:
 *   node tests/hexmap_v2_fog_state_test.js
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

class TestBus {
  constructor() {
    this.handlers = new Map();
  }

  on(name, handler) {
    if (!this.handlers.has(name)) {
      this.handlers.set(name, []);
    }
    this.handlers.get(name).push(handler);
    return () => {
      const next = (this.handlers.get(name) || []).filter((candidate) => candidate !== handler);
      this.handlers.set(name, next);
    };
  }

  emit(name, payload) {
    (this.handlers.get(name) || []).forEach((handler) => handler(payload));
  }
}

function loadHexFogOfWar() {
  const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexFogOfWar.js');
  const source = fs.readFileSync(sourcePath, 'utf8').replace('export class HexFogOfWar', 'class HexFogOfWar');
  return new Function(`${source}\nreturn HexFogOfWar;`)();
}

const HexFogOfWar = loadHexFogOfWar();

console.log('\n=== Hexmap V2 fog state ===');

{
  const bus = new TestBus();
  const fog = new HexFogOfWar({}, bus);
  let refreshEntity = null;
  let clearCount = 0;

  fog._refresh = (entity) => {
    refreshEntity = entity;
  };
  fog._clearFog = () => {
    clearCount += 1;
  };

  fog.init();
  bus.emit('entity:selected', { entity: { id: 'player-1' } });
  bus.emit('room:changed');
  bus.emit('canvas:fog-toggled', { enabled: true });

  assert(clearCount >= 1, 'room changes clear the fog overlay');
  assert(fog._selectedEntity === null, 'room changes clear the cached selected entity reference');
  assert(refreshEntity === null, 're-enabling fog after a room change does not refresh from a stale entity');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
