/**
 * @file
 * Regression coverage for durable streamed room-chat progress lines.
 *
 * Run with:
 *   node tests/chat_panel_progress_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/v2/panels/ChatPanel.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const buildPendingChatRequestSource = extractMethodSource(source, '  buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {')
  .replace('  buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {', 'function buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {');
const updatePendingChatProgressSource = extractMethodSource(source, '  updatePendingChatProgress(pending, text, phase = \'\', actor = {}) {')
  .replace('  updatePendingChatProgress(pending, text, phase = \'\', actor = {}) {', 'function updatePendingChatProgress(pending, text, phase = \'\', actor = {}) {');
const renderPendingGmResponseSource = extractMethodSource(source, '  renderPendingGmResponse(pending, response) {')
  .replace('  renderPendingGmResponse(pending, response) {', 'function renderPendingGmResponse(pending, response) {');
const settlePendingChatRequestSource = extractMethodSource(source, '  settlePendingChatRequest(pending, options = {}) {')
  .replace('  settlePendingChatRequest(pending, options = {}) {', 'function settlePendingChatRequest(pending, options = {}) {');

const factory = new Function(`
${buildPendingChatRequestSource}
${updatePendingChatProgressSource}
${renderPendingGmResponseSource}
${settlePendingChatRequestSource}
return { buildPendingChatRequest, updatePendingChatProgress, renderPendingGmResponse, settlePendingChatRequest };
`);

const {
  buildPendingChatRequest,
  updatePendingChatProgress,
  renderPendingGmResponse,
  settlePendingChatRequest,
} = factory();

console.log('\n=== ChatPanel progress chat contract ===');

assert(
  source.includes("this.settlePendingChatRequest(pendingRequest, {\n        removePlayer: false,\n      });"),
  'transport failures preserve the player transcript line instead of deleting it'
);
assert(
  source.includes("this.settlePendingChatRequest(pendingRequest, {\n        removePlayer: false,\n      });", source.indexOf('Failed to send queued room turn:')),
  'queued-room send failures also preserve the player transcript line'
);
assert(!source.includes('removePlaceholder:'), 'dead removePlaceholder lifecycle flags are removed from the chat pending flow');

{
  const appended = [];
  const pendingRequests = new Map();
  const panel = {
    pendingChatRequests: pendingRequests,
    buildChatRenderTarget(target) {
      return target;
    },
    appendChatLineToTarget(target, speaker, message, type, options = {}) {
      appended.push({ target, speaker, message, type, options });
    },
    syncChatTurnStatus() {},
  };

  const pending = buildPendingChatRequest.call(panel, 'req-1', 'Scout', 'Check the door.', 'room-1', {
    includePlaceholder: false,
    placeholderSpeaker: 'Narrator',
    placeholderType: 'npc',
    target: { view: 'room', channelKey: 'room', context: { roomId: 'room-1' } },
  });

  assert(appended.length === 1, 'pending request appends the player line without a filler placeholder');
  assert(Array.isArray(pending.progressLineIds) && pending.progressLineIds.length === 0, 'pending request starts without fake progress transcript lines');
}

{
  const appended = [];
  const foundLines = new Map();
  const panel = {
    appendChatLineToTarget(target, speaker, message, type, options = {}) {
      appended.push({ target, speaker, message, type, options });
      foundLines.set(options.lineId, { dataset: {} });
    },
    isChatTargetVisible() {
      return true;
    },
    findChatLineById(lineId) {
      return foundLines.get(lineId) || null;
    },
    syncChatTurnStatus() {},
  };

  const pending = {
    requestId: 'req-2',
    gmProgressLineId: '',
    target: { view: 'room', channelKey: 'room', context: { roomId: 'room-2' } },
    placeholderSpeaker: 'Narrator',
    placeholderType: 'npc',
    progressSpeaker: '',
    progressRole: '',
    progressLineIds: [],
    progressLineCounter: 0,
    lastProgressSignature: '',
  };

  updatePendingChatProgress.call(
    panel,
    pending,
    'Turn 1: Round 1: Actor Initiative Order: Resolving nearby NPC turns...',
    'npc-reactions',
    { speaker: 'Initiative Order', role: '' }
  );
  updatePendingChatProgress.call(
    panel,
    pending,
    'Turn 1: Round 1: Actor Initiative Order: Resolving nearby NPC turns...',
    'npc-reactions',
    { speaker: 'Initiative Order', role: '' }
  );

  assert(appended.length === 1, 'identical progress updates are not duplicated');
  assert(appended[0].speaker === 'Initiative Order', 'progress updates keep the server-provided Initiative Order speaker');
  assert(appended[0].message === 'Turn 1: Round 1: Actor Initiative Order: Resolving nearby NPC turns...', 'progress updates use the stable prefixed initiative-order wording');
  assert(appended[0].options.transient === false, 'progress updates are kept in chat instead of marked transient');
  assert(appended[0].options.lineId === 'chat-gm-progress-req-2', 'the first real progress update becomes the first transcript progress line');
  assert(pending.progressLineIds.length === 1, 'progress line history starts when a substantive progress update arrives');
}


{
  const appended = [];
  let removedLineId = null;
  const foundLines = new Map();
  const pendingRequests = new Map();
  const pending = {
    requestId: 'req-3',
    gmProgressLineId: 'chat-gm-progress-req-3',
    gmResponseLineId: 'chat-gm-req-3',
    target: { view: 'room', channelKey: 'room', context: { roomId: 'room-3' } },
    playerLineId: 'chat-player-req-3',
    progressLineIds: ['chat-gm-progress-req-3', 'chat-gm-progress-req-3-1'],
  };
  pendingRequests.set('req-3', pending);

  function buildLine() {
    return {
      dataset: { transient: '1' },
      classList: {
        removed: [],
        remove(name) {
          this.removed.push(name);
        },
      },
    };
  }

  foundLines.set('chat-player-req-3', buildLine());
  foundLines.set('chat-gm-progress-req-3', buildLine());
  foundLines.set('chat-gm-progress-req-3-1', buildLine());

  const panel = {
    appendChatLineToTarget(target, speaker, message, type, options = {}) {
      appended.push({ target, speaker, message, type, options });
    },
    resolveVisibleGmResponseMessage(response) {
      return response.message;
    },
    isChatTargetVisible() {
      return true;
    },
    findChatLineById(lineId) {
      return foundLines.get(lineId) || null;
    },
    removeChatLineById(lineId) {
      removedLineId = lineId;
    },
    pendingChatRequests: pendingRequests,
    syncChatTurnStatus() {},
  };

  renderPendingGmResponse.call(panel, pending, {
    speaker: 'Narrator',
    message: 'The nearby NPCs resolve their turns.',
    type: 'npc',
  });
  settlePendingChatRequest.call(panel, pending, { removePlayer: false });

  assert(removedLineId === null, 'progress transcript lines are not removed when the GM response arrives');
  assert(appended.length === 1 && appended[0].message === 'The nearby NPCs resolve their turns.', 'GM response is appended after the preserved progress lines');
  assert(foundLines.get('chat-player-req-3').classList.removed.includes('chat-line--pending'), 'player request line is finalized instead of being removed');
  assert(foundLines.get('chat-gm-progress-req-3').classList.removed.includes('chat-line--pending'), 'initial progress transcript line is finalized instead of being removed');
  assert(foundLines.get('chat-gm-progress-req-3-1').classList.removed.includes('chat-line--pending'), 'appended progress transcript lines are finalized instead of being removed');
  assert(foundLines.get('chat-gm-progress-req-3').dataset.transient === '0', 'preserved progress lines are marked non-transient after settle');
  assert(!pendingRequests.has('req-3'), 'pending request bookkeeping is cleared without deleting transcript lines');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
