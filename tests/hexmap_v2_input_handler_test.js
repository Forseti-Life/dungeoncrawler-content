/**
 * @file
 * Regression coverage for HexInputHandler's event enrichment.
 *
 * Run with:
 *   node tests/hexmap_v2_input_handler_test.js
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
    this.events = [];
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
    this.events.push({ name, payload });
    (this.handlers.get(name) || []).forEach((handler) => handler(payload));
  }
}

function loadHexInputHandler() {
  const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexInputHandler.js');
  const source = fs.readFileSync(sourcePath, 'utf8').replace('export class HexInputHandler', 'class HexInputHandler');
  return new Function(`${source}\nreturn HexInputHandler;`)();
}

function makeEntity(id, q, r) {
  return {
    id,
    getComponent(name) {
      if (name === 'PositionComponent') {
        return { q, r };
      }
      return null;
    },
  };
}

const HexInputHandler = loadHexInputHandler();

console.log('\n=== Hexmap V2 input handler ===');

{
  const bus = new TestBus();
  const entity = makeEntity('player-1', 2, 3);
  const hexCanvas = {
    objectContainer: {
      children: [
        { dcEntity: entity },
        { dcEntity: makeEntity('npc-off-hex', 9, 9) },
      ],
    },
  };

  const handler = new HexInputHandler(hexCanvas, bus);
  handler.init();

  bus.emit('canvas:hex-hovered', { q: 2, r: 3 });
  bus.emit('canvas:hex-clicked', { q: 2, r: 3, button: 0 });
  bus.emit('canvas:hex-out', { q: 2, r: 3 });

  const hovered = bus.events.find((event) => event.name === 'hex:hovered');
  const clicked = bus.events.find((event) => event.name === 'hex:clicked');
  const out = bus.events.find((event) => event.name === 'hex:out');

  assert(Array.isArray(hovered?.payload?.entities) && hovered.payload.entities.length === 1, 'enriches hover events with entities at the target hex');
  assert(clicked?.payload?.entities?.[0] === entity && clicked?.payload?.button === 0, 'enriches click events with entity payloads and preserves button information');
  assert(out?.payload?.q === 2 && out?.payload?.r === 3, 're-emits hex-out events for higher-level consumers');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
