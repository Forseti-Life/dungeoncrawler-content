/**
 * @file
 * Lightweight regression tests for the hexmap room-chat context helpers.
 *
 * Run with:
 *   node tests/hexmap_chat_context_test.js
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

function extractMethodSource(source, signature) {
  const start = source.indexOf(signature);
  if (start === -1) {
    throw new Error(`Could not find method signature: ${signature}`);
  }

  const braceStart = source.indexOf('{', start);
  if (braceStart === -1) {
    throw new Error(`Could not find opening brace for: ${signature}`);
  }

  let depth = 0;
  const signatureMatch = signature.match(/^([A-Za-z0-9_]+)\(([^)]*)\)\s*\{$/);
  const methodName = signatureMatch ? signatureMatch[1] : null;
  const methodParams = signatureMatch ? signatureMatch[2] : null;
  if (!methodName || methodParams === null) {
    throw new Error(`Could not parse method signature: ${signature}`);
  }
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        const body = source.slice(braceStart + 1, index);
        return `function ${methodName}(${methodParams}) {${body}}`;
      }
    }
  }

  throw new Error(`Could not find closing brace for: ${signature}`);
}

const sourcePath = path.resolve(__dirname, '../js/hexmap.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const resolvePinnedRoomSource = extractMethodSource(source, 'resolvePinnedChatRoomId() {');
const resolvePinnedRoomTargetSource = extractMethodSource(source, 'resolvePinnedChatRoomTarget(preferredRoomId = null, fallbackRoomId = null) {');
const getChatContextSource = extractMethodSource(source, 'getChatContext() {');
const factory = new Function(`${resolvePinnedRoomSource}\n${resolvePinnedRoomTargetSource}\n${getChatContextSource}\nreturn { resolvePinnedChatRoomId, resolvePinnedChatRoomTarget, getChatContext };`);
const methods = factory();

console.log('\n=== Hexmap room chat context ===');

{
  global.window = {
    location: {
      search: '?campaign_id=28&room_id=room-pinned',
    },
  };
  const context = {
    stateManager: {
      hexmap: {
        launchContext: { room_id: 'room-launch' },
        resolveActiveRoomId: () => 'room-active',
        resolveCampaignId: () => 28,
        characterData: { id: 122 },
      },
    },
  };

  const roomId = methods.resolvePinnedChatRoomId.call(context);
  const roomTarget = methods.resolvePinnedChatRoomTarget.call({
    ...context,
    resolvePinnedChatRoomId: methods.resolvePinnedChatRoomId.bind(context),
  }, '', 'room-fallback');
  const chatContext = methods.getChatContext.call({
    ...context,
    resolvePinnedChatRoomId: methods.resolvePinnedChatRoomId.bind(context),
  });

  assert(roomId === 'room-pinned', 'Pinned URL room wins over active room');
  assert(roomTarget === 'room-pinned', 'Pinned room target helper prefers pinned room over fallback');
  assert(chatContext.roomId === 'room-pinned', 'Chat context uses pinned URL room');
  assert(chatContext.campaignId === 28, 'Chat context keeps campaign id');
  assert(chatContext.characterId === 122, 'Chat context keeps character id');
}

{
  global.window = {
    location: {
      search: '?campaign_id=28',
    },
  };
  const context = {
    stateManager: {
      hexmap: {
        launchContext: { room_id: 'room-launch' },
        resolveActiveRoomId: () => 'room-active',
      },
    },
  };

  const roomId = methods.resolvePinnedChatRoomId.call(context);
  assert(roomId === 'room-launch', 'Launch context room wins when URL room is absent');
}

{
  global.window = {
    location: {
      search: '',
    },
  };
  const context = {
    stateManager: {
      hexmap: {
        launchContext: {},
        resolveActiveRoomId: () => 'room-active',
      },
    },
  };

  const roomId = methods.resolvePinnedChatRoomId.call(context);
  const roomTarget = methods.resolvePinnedChatRoomTarget.call({
    ...context,
    resolvePinnedChatRoomId: methods.resolvePinnedChatRoomId.bind(context),
  }, 'room-explicit', 'room-fallback');
  assert(roomId === 'room-active', 'Active room remains the fallback when no pinned room exists');
  assert(roomTarget === 'room-explicit', 'Pinned room target helper honors explicit preferred room');
}

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
console.log('===================================');
if (failed === 0) {
  console.log('ALL TESTS PASSED');
} else {
  console.log('SOME TESTS FAILED');
  process.exitCode = 1;
}
