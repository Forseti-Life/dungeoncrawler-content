/**
 * @file
 * Regression coverage for crowded-hex spread behavior in HexTokenRenderer.
 *
 * Run with:
 *   node tests/hex_token_renderer_spread_test.js
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

function extractMethodSource(source, methodSignature) {
  const start = source.indexOf(methodSignature);
  if (start === -1) {
    throw new Error(`Could not find method: ${methodSignature}`);
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

  throw new Error(`Could not extract method: ${methodSignature}`);
}

function toFunction(source, methodSignature, functionSignature) {
  return extractMethodSource(source, methodSignature).replace(methodSignature, functionSignature);
}

class FakeGraphics {
  constructor() {
    this.handlers = {};
    this.destroyed = false;
    this.x = 0;
    this.y = 0;
    this.zIndex = 0;
  }

  beginFill() {}
  drawCircle() {}
  endFill() {}

  on(event, handler) {
    this.handlers[event] = handler;
  }

  destroy() {
    this.destroyed = true;
  }
}

const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexTokenRenderer.js');
const source = fs.readFileSync(sourcePath, 'utf8');

const getEntitiesAtHexSource = toFunction(
  source,
  '  _getEntitiesAtHex(q, r) {',
  'function getEntitiesAtHex(q, r) {'
);
const getRenderedEntityCenterSource = toFunction(
  source,
  '  _getRenderedEntityCenter(entity) {',
  'function getRenderedEntityCenter(entity) {'
);
const clearSpreadInteractionTargetsSource = toFunction(
  source,
  '  _clearSpreadInteractionTargets() {',
  'function clearSpreadInteractionTargets() {'
);
const refreshSpreadInteractionTargetsSource = toFunction(
  source,
  '  _refreshSpreadInteractionTargets(q, r) {',
  'function refreshSpreadInteractionTargets(q, r) {'
);
const setEntitySpreadForHexSource = toFunction(
  source,
  '  _setEntitySpreadForHex(q, r, active) {',
  'function setEntitySpreadForHex(q, r, active) {'
);
const clearCrowdedHexHoverStateSource = toFunction(
  source,
  '  _clearCrowdedHexHoverState() {',
  'function clearCrowdedHexHoverState() {'
);

const factory = new Function(`
${getEntitiesAtHexSource}
${getRenderedEntityCenterSource}
${clearSpreadInteractionTargetsSource}
${refreshSpreadInteractionTargetsSource}
${setEntitySpreadForHexSource}
${clearCrowdedHexHoverStateSource}
return {
  getEntitiesAtHex,
  getRenderedEntityCenter,
  clearSpreadInteractionTargets,
  refreshSpreadInteractionTargets,
  setEntitySpreadForHex,
  clearCrowdedHexHoverState,
};
`);

const {
  getEntitiesAtHex,
  getRenderedEntityCenter,
  clearSpreadInteractionTargets,
  refreshSpreadInteractionTargets,
  setEntitySpreadForHex,
  clearCrowdedHexHoverState,
} = factory();

global.window = {
  PIXI: { Graphics: FakeGraphics },
  setTimeout(fn) {
    this._timer = fn;
    return 1;
  },
  clearTimeout() {},
};
global.PIXI = global.window.PIXI;

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

console.log('\n=== HexTokenRenderer crowded-hex spread ===');

{
  const emitted = [];
  const interactionContainer = {
    children: [],
    addChild(child) {
      this.children.push(child);
    },
    removeChildren() {
      const children = this.children.slice();
      this.children = [];
      return children;
    },
  };
  const entityA = makeEntity('a', 3, 4);
  const entityB = makeEntity('b', 3, 4);
  const renderer = {
    _tokens: new Map([
      ['a', { x: 100, y: 200, _baseX: 100, _baseY: 200, dcEntity: entityA }],
      ['b', { x: 100, y: 200, _baseX: 100, _baseY: 200, dcEntity: entityB }],
    ]),
    hexCanvas: {
      config: { hexSize: 30 },
      interactionContainer,
    },
    bus: {
      emit(name, payload) {
        emitted.push({ name, payload });
      },
    },
    _spreadExpandedHexKey: null,
    _spreadHoverAnchorKey: null,
    _spreadClearTimer: null,
    _getEntitiesAtHex: getEntitiesAtHex,
    _getRenderedEntityCenter: getRenderedEntityCenter,
    _clearSpreadInteractionTargets: clearSpreadInteractionTargets,
    _refreshSpreadInteractionTargets: refreshSpreadInteractionTargets,
    _setEntitySpreadForHex: setEntitySpreadForHex,
    _clearCrowdedHexHoverState: clearCrowdedHexHoverState,
  };

  setEntitySpreadForHex.call(renderer, 3, 4, true);

  const tokenA = renderer._tokens.get('a');
  const tokenB = renderer._tokens.get('b');
  assert(renderer._spreadExpandedHexKey === '3:4', 'spread tracks the expanded crowded hex key');
  assert(tokenA.x !== tokenA._baseX || tokenA.y !== tokenA._baseY, 'spread repositions the first crowded token');
  assert(tokenB.x !== tokenB._baseX || tokenB.y !== tokenB._baseY, 'spread repositions the second crowded token');
  assert(interactionContainer.children.length === 2, 'spread creates temporary interaction targets for each crowded token');

  interactionContainer.children[0].handlers.pointertap({
    stopPropagation() {},
  });
  assert(emitted.some((entry) => entry.name === 'hex:clicked' && entry.payload.entities?.length === 1), 'spread target click emits deterministic single-entity hex selection');

  emitted.length = 0;
  interactionContainer.children[0].handlers.pointerover();
  assert(renderer._spreadHoverAnchorKey === '3:4', 'spread target hover preserves the crowded-hex anchor key');
  assert(emitted.length === 0, 'spread target hover does not re-emit hex:hovered and thrash the spread state');

  clearCrowdedHexHoverState.call(renderer);
  assert(tokenA.x === tokenA._baseX && tokenA.y === tokenA._baseY, 'clearing crowded hover restores the first token base position');
  assert(tokenB.x === tokenB._baseX && tokenB.y === tokenB._baseY, 'clearing crowded hover restores the second token base position');
  assert(interactionContainer.children.length === 0, 'clearing crowded hover removes temporary interaction targets');
}

console.log(`\nPassed: ${passed}`);
if (failed > 0) {
  console.error(`Failed: ${failed}`);
  process.exit(1);
}
console.log('All crowded-hex spread tests passed.');
