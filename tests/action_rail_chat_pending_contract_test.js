/**
 * @file
 * Regression coverage for action-rail chat pending lifecycle wiring.
 *
 * Run with:
 *   node tests/action_rail_chat_pending_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js');
const source = fs.readFileSync(sourcePath, 'utf8');

const beginRequestSource = extractMethodSource(source, '  _beginActionRailRequest(button) {')
  .replace('  _beginActionRailRequest(button) {', 'function _beginActionRailRequest(button) {');
const endRequestSource = extractMethodSource(source, '  _endActionRailRequest(button) {')
  .replace('  _endActionRailRequest(button) {', 'function _endActionRailRequest(button) {');
const beginPendingSource = extractMethodSource(source, '  _beginActionRailPendingChatRequest(button) {')
  .replace('  _beginActionRailPendingChatRequest(button) {', 'function _beginActionRailPendingChatRequest(button) {');
const settlePendingSource = extractMethodSource(source, '  _settleActionRailPendingChatRequest(button) {')
  .replace('  _settleActionRailPendingChatRequest(button) {', 'function _settleActionRailPendingChatRequest(button) {');
const buildMessageSource = extractMethodSource(source, '  _buildActionRailPendingMessage(button) {')
  .replace('  _buildActionRailPendingMessage(button) {', 'function _buildActionRailPendingMessage(button) {');

const factory = new Function(`
${beginRequestSource}
${endRequestSource}
${beginPendingSource}
${settlePendingSource}
${buildMessageSource}
return {
  _beginActionRailRequest,
  _endActionRailRequest,
  _beginActionRailPendingChatRequest,
  _settleActionRailPendingChatRequest,
  _buildActionRailPendingMessage,
};
`);

const {
  _beginActionRailRequest,
  _endActionRailRequest,
  _beginActionRailPendingChatRequest,
  _settleActionRailPendingChatRequest,
  _buildActionRailPendingMessage,
} = factory();

console.log('\n=== Action rail chat pending contract ===');

{
  const pendingChatRequests = new Map();
  const calls = { build: [], settle: [] };
  const actionRailPanel = {
    ended: false,
    beginActionRailRequest(button) {
      button.dataset.backendRequestId = 'req-1';
      return true;
    },
    endActionRailRequest(button) {
      this.ended = true;
      delete button.dataset.backendRequestId;
    },
  };
  const chatPanel = {
    pendingChatRequests,
    buildChatRenderTarget(target) {
      return target;
    },
    buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {
      calls.build.push({ requestId, speaker, message, roomId, options });
      const pending = {
        requestId,
        target: options.target || null,
        playerLineId: `chat-player-${requestId}`,
      };
      pendingChatRequests.set(requestId, pending);
      return pending;
    },
    settlePendingChatRequest(pending, options = {}) {
      calls.settle.push({ pending, options });
      pendingChatRequests.delete(pending.requestId);
    },
  };

  const system = {
    shell: {
      panels: {
        actionRail: actionRailPanel,
        chat: chatPanel,
      },
    },
    _actionRailPendingRequests: new Map(),
    _getActionRailContext() {
      return {
        actorLabel: 'Scout',
        characterId: 804,
        runtimeContext: {
          campaignId: 219,
          roomId: 'room-12',
          characterId: 804,
        },
      };
    },
    _beginActionRailPendingChatRequest,
    _settleActionRailPendingChatRequest,
    _buildActionRailPendingMessage,
  };

  const button = {
    dataset: {
      actionRailExecute: 'search',
      actionLabel: 'Search',
    },
    textContent: 'Search',
  };

  const started = _beginActionRailRequest.call(system, button);
  assert(started === true, 'request lifecycle starts when action rail accepts the click');
  assert(calls.build.length === 1, 'starting an action rail request creates one chat pending request');
  assert(calls.build[0].options.includePlayer === true, 'pending request includes the player-side line');
  assert(calls.build[0].options.includePlaceholder === false, 'pending request does not append fake placeholder transcript lines');
  assert(String(calls.build[0].message || '').includes('Waiting for server response...'), 'pending request message includes server wait context');
  assert(system._actionRailPendingRequests.has('req-1'), 'pending request is tracked for settlement');

  _endActionRailRequest.call(system, button);
  assert(calls.settle.length === 1, 'ending an action rail request settles the chat pending request');
  assert(calls.settle[0].options.removePlayer === false, 'settled action pending request preserves the player wait line in transcript history');
  assert(!system._actionRailPendingRequests.has('req-1'), 'tracked pending request is cleared after settlement');
  assert(actionRailPanel.ended === true, 'action rail request lifecycle still closes backend wait state');
}

{
  const calls = { build: 0 };
  const system = {
    shell: {
      panels: {
        chat: {
          buildPendingChatRequest() {
            calls.build += 1;
            return null;
          },
          buildChatRenderTarget(target) {
            return target;
          },
        },
      },
    },
    _actionRailPendingRequests: new Map(),
    _getActionRailContext() {
      return {
        actorLabel: 'Scout',
        runtimeContext: {
          campaignId: 219,
          roomId: '',
        },
        hexmap: null,
      };
    },
    _buildActionRailPendingMessage,
  };

  _beginActionRailPendingChatRequest.call(system, { dataset: { backendRequestId: 'req-no-room', actionRailExecute: 'search' } });
  assert(calls.build === 0, 'pending chat request is skipped when there is no active room context');
}

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
