/*
 * Contract test: room metadata merge must be strict.
 * - No room.id alias (room_id only)
 * - Preserve server-provided room.subtitle when present
 *
 * Run with:
 *   node tests/room_metadata_merge_contract_test.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

function extractFunctionSource(source, name) {
  const marker = `function ${name}`;
  const start = source.indexOf(marker);
  if (start < 0) {
    throw new Error(`Could not find ${marker}`);
  }

  // Find the opening brace for the function body, not default-param object literals.
  const openParen = source.indexOf('(', start);
  if (openParen < 0) {
    throw new Error(`Could not find opening paren for ${name}`);
  }

  let j = openParen + 1;
  let parenDepth = 1;
  while (j < source.length && parenDepth > 0) {
    const ch = source[j];
    if (ch === '(') parenDepth += 1;
    else if (ch === ')') parenDepth -= 1;
    j += 1;
  }
  if (parenDepth !== 0) {
    throw new Error(`Unbalanced parens while extracting ${name}`);
  }

  const openBrace = source.indexOf('{', j);
  if (openBrace < 0) {
    throw new Error(`Could not find opening brace for ${name}`);
  }

  let i = openBrace + 1;
  let depth = 1;
  while (i < source.length && depth > 0) {
    const ch = source[i];
    if (ch === '{') depth += 1;
    else if (ch === '}') depth -= 1;
    i += 1;
  }
  if (depth !== 0) {
    throw new Error(`Unbalanced braces while extracting ${name}`);
  }

  return source.slice(start, i);
}

(function run() {
  const srcPath = path.join(__dirname, '..', 'js', 'v2', 'GameShell.js');
  const src = fs.readFileSync(srcPath, 'utf8');

  const fnNames = ['_isPlainObject', '_hasMeaningfulValue', '_mergeRoomMetadata'];
  const extracted = fnNames.map((n) => extractFunctionSource(src, n)).join('\n\n');

  // NOTE: We stub _buildRoomSubtitle() here because the real implementation in GameShell
  // uses template literals (${...}), and this test intentionally keeps extraction simple.
  const bundle = `${extractFunctionSource(src, '_isPlainObject')}\n\n${extractFunctionSource(src, '_hasMeaningfulValue')}\n\nfunction _buildRoomSubtitle() { return 'derived-subtitle'; }\n\n${extractFunctionSource(src, '_mergeRoomMetadata')}`;

  // eslint-disable-next-line no-new-func
  const factory = new Function(`${bundle}\nreturn { _mergeRoomMetadata };`);
  const { _mergeRoomMetadata } = factory();

  const visualRoom = {
    room_id: 'room-a',
    subtitle: 'Server subtitle',
    terrain: ['stone_floor'],
    lighting: 'dark',
  };
  const merged = _mergeRoomMetadata(visualRoom, {}, 'room-a');

  assert.strictEqual(merged.room_id, 'room-a');
  assert.ok(!Object.prototype.hasOwnProperty.call(merged, 'id'), 'merged room should not contain id alias');
  assert.strictEqual(merged.subtitle, 'Server subtitle', 'server-provided subtitle must be preserved');

  const mergedDerived = _mergeRoomMetadata({ room_id: 'room-b', terrain: ['stone_floor'], lighting: 'dark' }, {}, 'room-b');
  assert.strictEqual(mergedDerived.subtitle, 'derived-subtitle', 'subtitle should be derived when missing');

  const mergedFromNull = _mergeRoomMetadata(null, null, 'room-c');
  assert.strictEqual(mergedFromNull.room_id, 'room-c', 'room merge should tolerate null inputs and preserve fallback room id');
  assert.strictEqual(mergedFromNull.subtitle, 'derived-subtitle', 'null room metadata should still derive a subtitle safely');

  console.log('OK room metadata merge contract');
})();
