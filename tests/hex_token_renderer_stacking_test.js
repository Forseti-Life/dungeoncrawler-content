/**
 * @file
 * Regression coverage for HexTokenRenderer hex stacking rules.
 *
 * Run with:
 *   node tests/hex_token_renderer_stacking_test.js
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

function extractBlockSource(source, signature) {
  const start = source.indexOf(signature);
  if (start === -1) {
    throw new Error(`Could not find block: ${signature}`);
  }

  let braceStart = source.indexOf('{', start);
  if (braceStart === -1) {
    throw new Error(`Could not find opening brace for: ${signature}`);
  }

  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') depth++;
    if (char === '}') {
      depth--;
      if (depth === 0) {
        return source.slice(start, index + 1);
      }
    }
  }

  throw new Error(`Could not extract block: ${signature}`);
}

function toFunction(source, methodSignature, functionSignature) {
  return extractBlockSource(source, methodSignature).replace(methodSignature, functionSignature);
}

function makeEntity(id, q, r, entityType) {
  return {
    id,
    getComponent(name) {
      if (name === 'PositionComponent') {
        return { q, r };
      }
      if (name === 'IdentityComponent') {
        return { entityType };
      }
      if (name === 'RenderComponent') {
        return { objectCategory: entityType };
      }
      return null;
    },
  };
}

console.log('\n=== HexTokenRenderer stacking visibility ===');

const sourcePath = path.resolve(__dirname, '../js/v2/canvas/HexTokenRenderer.js');
const source = fs.readFileSync(sourcePath, 'utf8');

const getEntitiesAtHexSource = toFunction(source, '  _getEntitiesAtHex(q, r) {', 'function getEntitiesAtHex(q, r) {');
const applyStackingVisibilityForHexSource = toFunction(
  source,
  '  _applyStackingVisibilityForHex(q, r) {',
  'function applyStackingVisibilityForHex(q, r) {'
);

const factory = new Function(`
${getEntitiesAtHexSource}
${applyStackingVisibilityForHexSource}
return {
  getEntitiesAtHex,
  applyStackingVisibilityForHex,
};
`);

const { getEntitiesAtHex, applyStackingVisibilityForHex } = factory();

{
  const pc = makeEntity('pc', 3, 4, 'player_character');
  const item = makeEntity('item', 3, 4, 'item');
  const rock = makeEntity('rock', 3, 4, 'obstacle');

  const renderer = {
    _tokens: new Map([
      ['pc', { dcEntity: pc, visible: true }],
      ['item', { dcEntity: item, visible: true }],
      ['rock', { dcEntity: rock, visible: true }],
    ]),
    _spreadExpandedHexKey: null,
    _getEntitiesAtHex: getEntitiesAtHex,
    _applyStackingVisibilityForHex: applyStackingVisibilityForHex,
  };

  applyStackingVisibilityForHex.call(renderer, 3, 4);

  assert(renderer._tokens.get('pc').visible === true, 'top token (character) remains visible');
  assert(renderer._tokens.get('item').visible === false, 'item token hidden when stacked under character');
  assert(renderer._tokens.get('rock').visible === false, 'terrain/obstacle token hidden when stacked under character');

  renderer._spreadExpandedHexKey = '3:4';
  applyStackingVisibilityForHex.call(renderer, 3, 4);
  assert(renderer._tokens.get('pc').visible === true, 'spread hover: character visible');
  assert(renderer._tokens.get('item').visible === true, 'spread hover: item visible');
  assert(renderer._tokens.get('rock').visible === true, 'spread hover: terrain visible');
}

console.log(`\nPassed: ${passed}`);
if (failed > 0) {
  console.error(`Failed: ${failed}`);
  process.exit(1);
}
console.log('All stacking visibility tests passed.');
