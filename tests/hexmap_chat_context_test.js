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
    throw new Error(`Could not find opening brace for: ${signature}`);
  }

  let depth = 0;
  const signatureMatch = signature.match(/^([A-Za-z0-9_]+)\(([^)]*)\)\s*\{$/);
  const methodName = signatureMatch ? signatureMatch[1] : null;
  const methodParams = signatureMatch ? signatureMatch[2] : null;
  if (!methodName || methodParams === null) {
    throw new Error(`Could not parse method signature: ${signature}`);
  }
  const asyncPrefix = source.slice(Math.max(0, start - 6), start) === 'async ' ? 'async ' : '';
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        const body = source.slice(braceStart + 1, index);
        return `${asyncPrefix}function ${methodName}(${methodParams}) {${body}}`;
      }
    }
  }

  throw new Error(`Could not find closing brace for: ${signature}`);
}

function extractFunctionExpressionSource(source, anchor, functionName) {
  const start = source.indexOf(anchor);
  if (start === -1) {
    throw new Error(`Could not find function anchor: ${anchor}`);
  }

  const functionStart = source.indexOf('function', start);
  if (functionStart === -1) {
    throw new Error(`Could not parse function expression: ${anchor}`);
  }

  let braceStart = -1;
  let parenDepth = 0;
  for (let index = functionStart; index < source.length; index++) {
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
    throw new Error(`Could not find function body for: ${anchor}`);
  }

  const header = source.slice(start, braceStart).trim();
  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        const body = source.slice(braceStart + 1, index);
        const normalizedHeader = header
          .replace(/^[^:]+:\s*/, '')
          .replace(/^async function\s*/, `async function ${functionName}`)
          .replace(/^function\s*/, `function ${functionName}`);
        return `${normalizedHeader} {${body}}`;
      }
    }
  }

  throw new Error(`Could not find closing brace for function expression: ${anchor}`);
}

const sourcePath = path.resolve(__dirname, '../js/hexmap.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const normalizeInventoryStateSource = `
function normalizeInventoryState(rawInventory, fallbackCurrency = {}) {
  if (!rawInventory || typeof rawInventory !== 'object') {
    return {
      carried: [],
      worn: {},
      equipped: [],
      stashed: [],
      currency: fallbackCurrency,
      totalBulk: null,
      bodyShape: 'humanoid',
      slotFramework: {},
      slotState: {},
    };
  }
  return {
    carried: Array.isArray(rawInventory.carried) ? rawInventory.carried : [],
    worn: rawInventory.worn && typeof rawInventory.worn === 'object' ? rawInventory.worn : {},
    equipped: Array.isArray(rawInventory.equipped) ? rawInventory.equipped : [],
    stashed: Array.isArray(rawInventory.stashed) ? rawInventory.stashed : [],
    currency: rawInventory.currency && typeof rawInventory.currency === 'object' ? rawInventory.currency : fallbackCurrency,
    totalBulk: null,
    bodyShape: 'humanoid',
    slotFramework: {},
    slotState: {},
  };
}`;
const teamEnumSource = `
const Team = {
  PLAYER: 'player',
  ALLY: 'ally',
  ENEMY: 'enemy',
  NEUTRAL: 'neutral',
};`;
const resolvePinnedRoomSource = extractMethodSource(source, 'resolvePinnedChatRoomId() {');
const resolvePinnedRoomTargetSource = extractMethodSource(source, 'resolvePinnedChatRoomTarget(preferredRoomId = null, fallbackRoomId = null) {');
const getChatContextSource = extractMethodSource(source, 'getChatContext() {');
const requestPlayerAutomationStepSource = extractFunctionExpressionSource(source, 'requestPlayerAutomationStep: async function (campaignId, profile, runState = {}) {', 'requestPlayerAutomationStep');
const requestPlayerAutomationRoomSuggestionSource = extractFunctionExpressionSource(source, 'requestPlayerAutomationRoomSuggestion: async function (campaignId, roomId, profile, runState = {}) {', 'requestPlayerAutomationRoomSuggestion');
const resolveInventoryActionContextSource = extractMethodSource(source, 'resolveInventoryActionContext(button) {');
const resolveInventoryAssignSelectionSource = extractMethodSource(source, 'resolveInventoryAssignSelection(actionContext) {');
const logInventoryActionTraceSource = extractMethodSource(source, 'logInventoryActionTrace(stage, details = {}, level = \'info\') {');
const handleInventoryActionSource = extractMethodSource(source, 'handleInventoryAction(button) {');
const refreshCharacterInventoryFromApiSource = extractMethodSource(source, 'refreshCharacterInventoryFromApi(context) {');
const normalizeEncounterParticipantTeamSource = extractFunctionExpressionSource(source, 'normalizeEncounterParticipantTeam: function (team = \'\') {', 'normalizeEncounterParticipantTeam');
const resolveActiveRoomIdSource = extractFunctionExpressionSource(source, 'resolveActiveRoomId: function () {', 'resolveActiveRoomId');
const serializeCombatantsForApiSource = extractFunctionExpressionSource(source, 'serializeCombatantsForApi: function () {', 'serializeCombatantsForApi');
const buildRoomChatCacheKeySource = extractMethodSource(source, 'buildRoomChatCacheKey(context = null, channelKey = null) {');
const submitRoomChatMessageSource = extractMethodSource(source, 'submitRoomChatMessage(message, options = {}) {');
const flushDeferredRoomMessagesSource = extractMethodSource(source, 'flushDeferredRoomMessages(campaignId, roomId, characterId = null) {');
const rememberRoomTurnSequenceSource = extractMethodSource(source, 'rememberRoomTurnSequence(turnSequence = [], context = null, channelKey = null) {');
const getRememberedRoomTurnSequenceSource = extractMethodSource(source, 'getRememberedRoomTurnSequence(context = null, channelKey = null) {');
const buildActiveRoomNpcTurnOrderSource = extractMethodSource(source, 'buildActiveRoomNpcTurnOrder(roomId = null) {');
const getActiveRoomNpcResponderNamesSource = extractMethodSource(source, 'getActiveRoomNpcResponderNames(roomId = null) {');
const buildChatRoundOrderLinesSource = extractMethodSource(source, 'buildChatRoundOrderLines(pending = null) {');
const getPendingTurnDescriptorSource = extractMethodSource(source, 'getPendingTurnDescriptor(pending) {');
const buildPendingTurnMetaSource = extractMethodSource(source, 'buildPendingTurnMeta(pending, descriptor) {');
const buildIdleChatTurnStatusSource = extractMethodSource(source, 'buildIdleChatTurnStatus() {');
const setChatTurnStatusSource = extractMethodSource(source, 'setChatTurnStatus(status = null) {');
const syncChatTurnStatusSource = extractMethodSource(source, 'syncChatTurnStatus() {');
const factory = new Function(`${normalizeInventoryStateSource}\n${teamEnumSource}\n${resolvePinnedRoomSource}\n${resolvePinnedRoomTargetSource}\n${getChatContextSource}\n${requestPlayerAutomationStepSource}\n${requestPlayerAutomationRoomSuggestionSource}\n${resolveInventoryActionContextSource}\n${resolveInventoryAssignSelectionSource}\n${logInventoryActionTraceSource}\n${handleInventoryActionSource}\n${refreshCharacterInventoryFromApiSource}\n${normalizeEncounterParticipantTeamSource}\n${resolveActiveRoomIdSource}\n${serializeCombatantsForApiSource}\n${buildRoomChatCacheKeySource}\n${submitRoomChatMessageSource}\n${flushDeferredRoomMessagesSource}\n${rememberRoomTurnSequenceSource}\n${getRememberedRoomTurnSequenceSource}\n${buildActiveRoomNpcTurnOrderSource}\n${getActiveRoomNpcResponderNamesSource}\n${buildChatRoundOrderLinesSource}\n${getPendingTurnDescriptorSource}\n${buildPendingTurnMetaSource}\n${buildIdleChatTurnStatusSource}\n${setChatTurnStatusSource}\n${syncChatTurnStatusSource}\nreturn { normalizeInventoryState, resolvePinnedChatRoomId, resolvePinnedChatRoomTarget, getChatContext, requestPlayerAutomationStep, requestPlayerAutomationRoomSuggestion, resolveInventoryActionContext, resolveInventoryAssignSelection, logInventoryActionTrace, handleInventoryAction, refreshCharacterInventoryFromApi, normalizeEncounterParticipantTeam, resolveActiveRoomId, serializeCombatantsForApi, buildRoomChatCacheKey, submitRoomChatMessage, flushDeferredRoomMessages, rememberRoomTurnSequence, getRememberedRoomTurnSequence, buildActiveRoomNpcTurnOrder, getActiveRoomNpcResponderNames, buildChatRoundOrderLines, getPendingTurnDescriptor, buildPendingTurnMeta, buildIdleChatTurnStatus, setChatTurnStatus, syncChatTurnStatus };`);
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

{
  const infoLogs = [];
  const originalInfo = console.info;
  console.info = (...args) => infoLogs.push(args);
  const context = {
    activeSessionView: 'room',
    activeChannel: 'room',
    lastChatTurnStatusKey: '',
    roomTurnSequenceCache: new Map(),
    elements: {
      chatTurnStatus: { hidden: true },
      chatTurnRole: { textContent: '' },
      chatTurnName: { textContent: '' },
      chatTurnMeta: { textContent: '' },
      chatTurnCurrentRoundLabel: { textContent: '' },
      chatTurnCurrentRoundOrder: { textContent: '' },
      chatTurnNextRoundLabel: { textContent: '' },
      chatTurnNextRoundOrder: { textContent: '' },
    },
    getChatContext: () => ({ campaignId: 28, roomId: 'room-active', characterId: 122 }),
    getVisiblePendingChatRequest: () => null,
    buildRoomChatCacheKey: methods.buildRoomChatCacheKey,
    getRememberedRoomTurnSequence: methods.getRememberedRoomTurnSequence,
    buildActiveRoomNpcTurnOrder: () => [
      { role: 'npc', name: 'Eldric', initiative: 17 },
      { role: 'npc', name: 'Captain Hadrik', initiative: 14 },
    ],
    getActiveRoomNpcResponderNames: () => ['Eldric', 'Captain Hadrik'],
    buildChatRoundOrderLines: methods.buildChatRoundOrderLines,
    buildIdleChatTurnStatus: methods.buildIdleChatTurnStatus,
    setChatTurnStatus: methods.setChatTurnStatus,
    stateManager: {
      hexmap: {
        launchContext: { room_id: 'room-active' },
        resolveActiveRoomId: () => 'room-active',
        resolveCampaignId: () => 28,
        characterData: { id: 122 },
      },
    },
  };

  methods.syncChatTurnStatus.call(context);

  console.info = originalInfo;
  assert(context.elements.chatTurnStatus.hidden === false, 'Idle room chat still shows a turn banner');
  assert(context.elements.chatTurnRole.textContent === 'Player', 'Idle room chat identifies the player as the active turn owner');
  assert(context.elements.chatTurnName.textContent === 'You', 'Idle room chat names the current player explicitly');
  assert(context.elements.chatTurnMeta.textContent.includes('Player turn:'), 'Idle room chat explains that it is the player turn');
  assert(context.elements.chatTurnCurrentRoundOrder.textContent.includes('Turn 1: Narrator'), 'Idle room chat shows the response round starting with the narrator');
  assert(context.elements.chatTurnCurrentRoundOrder.textContent.includes('Turn 3: Eldric (initiative 17)'), 'Idle room chat lists explicit NPC initiative order');
  assert(context.elements.chatTurnNextRoundOrder.textContent.includes('Turn 1: Narrator'), 'Idle room chat previews the next response round starting with the narrator');
  assert(infoLogs.length === 1, 'Idle room chat logs the visible turn state once');
}

{
  const originalInfo = console.info;
  console.info = () => {};
  const pending = {
    requestId: 'req-1',
    target: { view: 'room' },
    placeholderSpeaker: 'Narrator',
    placeholderType: 'npc',
    progressPhase: 'drafting-response',
    progressSpeaker: 'Narrator',
    progressRole: 'Narrator',
  };
  const context = {
    activeSessionView: 'room',
    activeChannel: 'room',
    lastChatTurnStatusKey: '',
    roomTurnSequenceCache: new Map(),
    elements: {
      chatTurnStatus: { hidden: true },
      chatTurnRole: { textContent: '' },
      chatTurnName: { textContent: '' },
      chatTurnMeta: { textContent: '' },
      chatTurnCurrentRoundLabel: { textContent: '' },
      chatTurnCurrentRoundOrder: { textContent: '' },
      chatTurnNextRoundLabel: { textContent: '' },
      chatTurnNextRoundOrder: { textContent: '' },
    },
    getVisiblePendingChatRequest: () => pending,
    getChatContext: () => ({ campaignId: 28, roomId: 'room-active', characterId: 122 }),
    buildRoomChatCacheKey: methods.buildRoomChatCacheKey,
    getRememberedRoomTurnSequence: () => [
      { role: 'narrator', display_name: 'Narrator' },
      { role: 'gm', display_name: 'Game Master' },
      { role: 'npc', display_name: 'Eldric', initiative_total: 17 },
      { role: 'npc', display_name: 'Captain Hadrik', initiative_total: 14 },
    ],
    buildActiveRoomNpcTurnOrder: () => [
      { role: 'npc', name: 'Eldric', initiative: 17 },
      { role: 'npc', name: 'Captain Hadrik', initiative: 14 },
    ],
    getActiveRoomNpcResponderNames: () => ['Eldric', 'Captain Hadrik'],
    buildChatRoundOrderLines: methods.buildChatRoundOrderLines,
    getPendingTurnDescriptor: methods.getPendingTurnDescriptor,
    buildPendingTurnMeta: methods.buildPendingTurnMeta,
    setChatTurnStatus: methods.setChatTurnStatus,
  };

  methods.syncChatTurnStatus.call(context);

  console.info = originalInfo;
  assert(context.elements.chatTurnRole.textContent === 'Narrator', 'Pending room chat identifies the narrator as the first execution turn');
  assert(context.elements.chatTurnName.textContent === 'Narrator', 'Pending room chat names the narrator explicitly');
  assert(context.elements.chatTurnMeta.textContent.includes('preparing the scene'), 'Pending room chat explains the live narrator turn work');
  assert(context.elements.chatTurnCurrentRoundOrder.textContent.includes('Turn 1: Narrator - current'), 'Pending room chat marks the narrator as the active first turn of the current round');
  assert(context.elements.chatTurnCurrentRoundOrder.textContent.includes('Turn 3: Eldric (initiative 17)'), 'Pending room chat preserves initiative order detail for NPC turns');
  assert(context.elements.chatTurnNextRoundOrder.textContent.includes('Turn 1: Narrator'), 'Pending room chat previews the next response round starting with the narrator');
}

(async () => {
  {
    const makeEntity = ({ name, team, roomId, alive = true }) => ({
      id: `${name.toLowerCase().replace(/\s+/g, '-')}-${team}`,
      dcEntityRef: `${name.toLowerCase().replace(/\s+/g, '-')}-ref`,
      dcCharacterId: team === 'player' ? 501 : null,
      dcStatePayload: { placement: { room_id: roomId }, metadata: { team } },
      getComponent(component) {
        if (component === 'IdentityComponent') {
          return { name, isCreature: () => true };
        }
        if (component === 'CombatComponent') {
          return { team, getInitiative: () => (team === 'player' ? 12 : 10), initiativeBonus: 0 };
        }
        if (component === 'StatsComponent') {
          return { isAlive: () => alive, ac: 18, currentHp: 20, maxHp: 20, perception: 5 };
        }
        if (component === 'PositionComponent') {
          return { q: 0, r: 0 };
        }
        return null;
      },
    });

    const context = {
      activeRoomId: 'room-social',
      stateManager: {
        get(key) {
          if (key === 'activeRoomId') return 'room-social';
          return null;
        },
      },
      entityManager: {
        getEntitiesWith() {
          return [
            makeEntity({ name: 'Valeros', team: 'player', roomId: 'room-social' }),
            makeEntity({ name: 'Innkeeper', team: 'neutral', roomId: 'room-social' }),
            makeEntity({ name: 'Guard', team: 'ally', roomId: 'room-social' }),
          ];
        },
      },
      normalizeEncounterParticipantTeam: methods.normalizeEncounterParticipantTeam,
      resolveActiveRoomId: methods.resolveActiveRoomId,
    };

    const serialized = methods.serializeCombatantsForApi.call(context);
    assert(serialized.length === 3, 'Encounter serialization keeps neutral and ally room occupants');
    assert(serialized.some((entity) => entity.team === 'neutral' && entity.name === 'Innkeeper'), 'Encounter serialization preserves neutral NPC participants');
  }

  {
    const queuedCalls = [];
    const context = {
      roomChatBusy: true,
      roomChatQueueDraining: false,
      roomChatDeferredMessages: [],
      stateManager: {
        hexmap: {
          resolveCampaignId: () => 77,
          resolveActiveRoomId: () => 'room-queue',
          characterData: { id: 501, name: 'Scout' },
        },
      },
      resolvePendingResponder() {
        return { speaker: 'Narrator', type: 'npc' };
      },
      buildChatRenderTarget(target) {
        return target;
      },
      buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {
        return { requestId, speaker, message, roomId, target: options.target || null };
      },
      prefetchSessionViews() {},
      loadActiveRoomView() {},
      async postChatMessage() {
        queuedCalls.push(Array.from(arguments));
        return { success: true, data: {} };
      },
      settlePendingChatRequest() {
        throw new Error('Queued submit should not settle the pending request immediately.');
      },
      updateQueuedChatStatus(count) {
        this.lastQueuedCount = count;
      },
      flushDeferredRoomMessages: methods.flushDeferredRoomMessages,
    };

    const result = await methods.submitRoomChatMessage.call(context, 'Check the northern archway.', {});

    assert(result?.data?.queued === true, 'Queued room submit reports that the player turn was queued');
    assert(queuedCalls.length === 0, 'Queued room submit does not post an immediate batched GM continuation');
    assert(context.roomChatDeferredMessages.length === 1, 'Queued room submit stores exactly one deferred player turn');
    assert(context.roomChatDeferredMessages[0].speaker === 'Scout', 'Queued room submit preserves the original speaker');
    assert(context.roomChatDeferredMessages[0].message === 'Check the northern archway.', 'Queued room submit preserves the original player message');
    assert(context.lastQueuedCount === 1, 'Queued room submit updates the queued-turn status count');
  }

  {
    const postCalls = [];
    const queueCounts = [];
    const appendedLines = [];
    const context = {
      roomChatBusy: false,
      roomChatDeferredMessages: [
        {
          requestId: 'queued-1',
          speaker: 'Scout',
          message: 'First queued line',
          roomId: 'room-queue',
          campaignId: 77,
          characterId: 501,
          channel: 'room',
          pendingRequest: { requestId: 'queued-1', target: { view: 'room', channelKey: 'room', context: { campaignId: 77, roomId: 'room-queue', characterId: 501 } } },
          target: { view: 'room', channelKey: 'room', context: { campaignId: 77, roomId: 'room-queue', characterId: 501 } },
        },
        {
          requestId: 'queued-2',
          speaker: 'Scout',
          message: 'Second queued line',
          roomId: 'room-queue',
          campaignId: 77,
          characterId: 501,
          channel: 'room',
          pendingRequest: { requestId: 'queued-2', target: { view: 'room', channelKey: 'room', context: { campaignId: 77, roomId: 'room-queue', characterId: 501 } } },
          target: { view: 'room', channelKey: 'room', context: { campaignId: 77, roomId: 'room-queue', characterId: 501 } },
        },
      ],
      updateQueuedChatStatus(count) {
        queueCounts.push(count);
      },
      buildChatRenderTarget(target) {
        return target;
      },
      buildPendingChatRequest(requestId, speaker, message, roomId, options = {}) {
        return { requestId, speaker, message, roomId, target: options.target || null };
      },
      async postChatMessage(...args) {
        postCalls.push(args);
        return { success: true, data: { gm_response: { message: 'Acknowledged.' } } };
      },
      settlePendingChatRequest() {
        throw new Error('Successful queued turn replay should not settle as a failure.');
      },
      appendChatLine(speaker, message, type) {
        appendedLines.push({ speaker, message, type });
      },
      flushDeferredRoomMessages: methods.flushDeferredRoomMessages,
    };

    await methods.flushDeferredRoomMessages.call(context, 77, 'room-queue', 501);
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert(postCalls.length === 2, 'Queued room replay sends each deferred player turn individually');
    assert(postCalls[0][2] === 'Scout' && postCalls[0][3] === 'First queued line', 'First queued turn replays the original speaker and message');
    assert(postCalls[1][2] === 'Scout' && postCalls[1][3] === 'Second queued line', 'Second queued turn replays as its own separate turn');
    assert(queueCounts.includes(1) && queueCounts.includes(0), 'Queued room replay updates the remaining queued-turn count after each turn');
    assert(context.roomChatDeferredMessages.length === 0, 'Queued room replay drains the deferred turn queue completely');
    assert(appendedLines.length === 0, 'Queued room replay does not emit failure system lines on success');
  }

  global.fetch = async (url, options = {}) => {
    assert(url === '/api/campaign/69/room/room-explore/chat/player-suggestion', 'Exploration automation requests the player suggestion endpoint');
    const parsed = JSON.parse(options.body || '{}');
    assert(parsed.character_id === 268, 'Exploration automation sends the active character id');
    assert(parsed.channel === 'room', 'Exploration automation requests room-channel suggestions');
    return {
      ok: true,
      async json() {
        return {
          success: true,
          data: {
            message: 'We should check the archway before moving deeper.',
          },
        };
      },
    };
  };

  let postChatArgs = null;
  const context = {
    resolveActiveRoomId: () => 'room-explore',
    gameCoordinator: {
      phaseManager: {
        currentPhase: 'exploration',
      },
    },
    stateManager: {
      get: () => false,
    },
    uiManager: {
      buildChatRenderTarget({ context }) {
        return {
          channelKey: 'room',
          context,
        };
      },
      async postChatMessage(...args) {
        postChatArgs = args;
        return {
          data: {
            gm_response: { message: 'The archway yawns open before you.' },
            npc_interjections: [],
            quest_updates: [],
            events: [],
          },
        };
      },
    },
    requestPlayerAutomationRoomSuggestion: methods.requestPlayerAutomationRoomSuggestion,
    requestPlayerAutomationEncounterStep: async () => {
      throw new Error('Encounter branch should not run in exploration mode.');
    },
  };

  const result = await methods.requestPlayerAutomationStep.call(
    context,
    69,
    { character_id: 268, character_name: 'Burasco' },
    { step_count: 39 }
  );

  assert(Array.isArray(postChatArgs) && postChatArgs.length === 6, 'Exploration automation submits the suggested line through room chat');
  assert(postChatArgs[0] === 69 && postChatArgs[1] === 'room-explore', 'Exploration automation posts chat to the active campaign room');
  assert(postChatArgs[3] === 'We should check the archway before moving deeper.', 'Exploration automation forwards the suggested message text');
  assert(result.ui_already_rendered === true, 'Exploration automation marks UI rendering as already handled');
  assert(result.run_state.step_count === 40, 'Exploration automation advances the run-state step count');
  assert(result.response.result.talked === true, 'Exploration automation returns a talk result');

  let encounterDelegated = false;
  const encounterContext = {
    resolveActiveRoomId: () => 'room-encounter',
    gameCoordinator: {
      phaseManager: {
        currentPhase: 'encounter',
      },
    },
    stateManager: {
      get: () => false,
    },
    async requestPlayerAutomationEncounterStep(campaignId, profile, runState) {
      encounterDelegated = true;
      return { campaignId, profile, runState, branch: 'encounter' };
    },
  };

  const encounterResult = await methods.requestPlayerAutomationStep.call(
    encounterContext,
    70,
    { character_id: 301, character_name: 'Scout' },
    { step_count: 2 }
  );

  assert(encounterDelegated === true, 'Encounter automation still delegates to the encounter handler');
  assert(encounterResult.branch === 'encounter', 'Encounter automation preserves the encounter branch result');

  let inventoryFetchUrl = null;
  global.fetch = async (url, options = {}) => {
    inventoryFetchUrl = url;
    assert(options.credentials === 'same-origin', 'Inventory refresh uses same-origin credentials');
    assert(options.headers['X-Requested-With'] === 'XMLHttpRequest', 'Inventory refresh sends the Drupal XHR header');
    return {
      ok: true,
      async json() {
        return {
          success: true,
          inventory: {
            worn: { weapons: [], armor: null, shield: null, accessories: [] },
            carried: [
              {
                item_instance_id: '267_leather',
                item_id: 'leather',
                name: 'Leather Armor',
                quantity: 1,
                inventory_metadata: {
                  equippable: true,
                  equip_slot: 'armor',
                  worn_slot: null,
                  hand_slots_required: 0,
                  consumable: false,
                  consumes_on_use: false,
                  container: false,
                  stackable: false,
                },
              },
            ],
            equipped: [],
            stashed: [],
            currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
          },
        };
      },
    };
  };

  const renderCalls = [];
  const inventoryContext = {
    currentCharacterInventoryContext: {
      characterId: 267,
      campaignId: 70,
      inventoryActionFeedback: {
        tone: 'success',
        itemInstanceId: '267_leather',
        slotKey: 'armor',
        message: 'Leather Armor assigned to Armor.',
      },
      inventory: {
        worn: { weapons: [], armor: null, shield: null, accessories: [] },
        carried: [],
        equipped: [],
        stashed: [],
        currency: { gp: 0, sp: 0, cp: 0, pp: 0 },
      },
      currency: { gp: 0, sp: 0, cp: 0, pp: 0 },
    },
    renderInventoryPanel(nextContext) {
      renderCalls.push(nextContext);
    },
  };

  await methods.refreshCharacterInventoryFromApi.call(inventoryContext, inventoryContext.currentCharacterInventoryContext);
  assert(inventoryFetchUrl === '/api/inventory/character/267?campaign_id=70', 'Inventory refresh requests the live character inventory endpoint');
  assert(renderCalls.length === 1, 'Inventory refresh rerenders the inventory panel');
  assert(inventoryContext.currentCharacterInventoryContext.inventory.carried[0].name === 'Leather Armor', 'Inventory refresh replaces stale carried items with live inventory data');
  assert(renderCalls[0].inventoryActionFeedback.message === 'Leather Armor assigned to Armor.', 'Inventory refresh preserves the last inventory feedback across rerenders');

  let assignRequestBody = null;
  global.window = {};
  globalThis.dcInventoryActionLog = [];
  global.fetch = async (url, options = {}) => {
    assert(url === '/api/inventory/character/267/item/267_leather/location', 'Inventory assign targets the live item location endpoint');
    assert(options.credentials === 'same-origin', 'Inventory assign uses same-origin credentials');
    assert(options.headers['X-Requested-With'] === 'XMLHttpRequest', 'Inventory assign sends the Drupal XHR header');
    assignRequestBody = JSON.parse(options.body || '{}');
    return {
      ok: true,
      async json() {
        return {
          success: true,
          inventory: {
            worn: {
              weapons: [],
              armor: { item_id: 'leather', name: 'Leather Armor' },
              shield: null,
              accessories: [],
            },
            carried: [],
            equipped: [],
            stashed: [],
            currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
          },
        };
      },
    };
  };

  const assignRenderCalls = [];
  const assignContext = {
    currentCharacterInventoryContext: {
      characterId: 267,
      campaignId: 70,
      inventory: {
        worn: { weapons: [], armor: null, shield: null, accessories: [] },
        carried: [{ item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }],
        equipped: [],
        stashed: [],
        currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
      },
      currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
    },
    renderInventoryPanel(nextContext) {
      assignRenderCalls.push(nextContext);
    },
    resolveInventoryActionContext: methods.resolveInventoryActionContext,
    resolveInventoryAssignSelection: methods.resolveInventoryAssignSelection,
    logInventoryActionTrace: methods.logInventoryActionTrace,
  };
  const assignRow = {
    dataset: {
      itemInstanceId: '267_leather',
    },
    classList: {
      add() {},
      remove() {},
    },
    querySelector(selector) {
      if (selector === '[data-slot-select]') {
        return {
          value: 'armor::',
          disabled: false,
          options: [{ value: 'armor::', textContent: 'Armor' }],
          selectedIndex: 0,
          selectedOptions: [{ textContent: 'Armor' }],
        };
      }
      if (selector === '.inv-item__name') {
        return { textContent: 'Leather Armor' };
      }
      return null;
    },
    querySelectorAll(selector) {
      if (selector === '[data-inventory-action]') {
        return [assignButton];
      }
      return [];
    },
  };
  const assignButton = {
    dataset: {
      inventoryAction: 'assign',
      itemInstanceId: '267_leather',
      itemName: 'Leather Armor',
      slotKey: 'armor',
      slotLabel: 'Armor',
    },
    disabled: false,
    closest(selector) {
      return selector === '.inv-item' ? assignRow : null;
    },
  };

  await methods.handleInventoryAction.call(assignContext, assignButton);
  assert(assignRequestBody.location === 'worn', 'Inventory assign posts a worn location update');
  assert(assignRequestBody.equippedSlotKey === 'armor', 'Inventory assign posts the selected equipment slot');
  assert(assignRenderCalls.length === 2, 'Inventory assign rerenders the panel for pending and success states');
  assert(assignRenderCalls[0].inventoryActionFeedback.tone === 'pending', 'Inventory assign records a pending feedback state before the response returns');
  assert(assignRenderCalls[1].inventoryActionFeedback.tone === 'success', 'Inventory assign records a success feedback state after the response returns');
  assert(assignRenderCalls[1].inventoryActionFeedback.slotKey === 'armor', 'Inventory assign keeps the affected slot in the feedback state');
  assert(globalThis.dcInventoryActionLog.map((entry) => entry.stage).join(',') === 'click,request,success', 'Inventory assign emits click, request, and success trace logs');
  assert(globalThis.dcInventoryActionLog.length === 3, 'Inventory assign stores trace logs on the window for troubleshooting');
  assert(assignContext.currentCharacterInventoryContext.inventory.worn.armor.item_id === 'leather', 'Inventory assign updates the worn armor state after success');

  let fallbackAssignRequestBody = null;
  global.window = {};
  globalThis.dcInventoryActionLog = [];
  global.fetch = async (url, options = {}) => {
    fallbackAssignRequestBody = JSON.parse(options.body || '{}');
    return {
      ok: true,
      async json() {
        return {
          success: true,
          inventory: {
            worn: {
              weapons: [],
              armor: { item_id: 'leather', name: 'Leather Armor' },
              shield: null,
              accessories: [],
            },
            carried: [],
            equipped: [],
            stashed: [],
            currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
          },
        };
      },
    };
  };

  const fallbackAssignContext = {
    currentCharacterInventoryContext: {
      characterId: 267,
      campaignId: 70,
      inventory: {
        worn: { weapons: [], armor: null, shield: null, accessories: [] },
        carried: [{ item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }],
        equipped: [],
        stashed: [],
        currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
      },
      currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
    },
    renderInventoryPanel() {},
    resolveInventoryActionContext: methods.resolveInventoryActionContext,
    resolveInventoryAssignSelection: methods.resolveInventoryAssignSelection,
    logInventoryActionTrace: methods.logInventoryActionTrace,
  };
  const fallbackAssignRow = {
    dataset: {
      itemInstanceId: '267_leather',
    },
    querySelector(selector) {
      if (selector === '[data-slot-select]') {
        return {
          value: '',
          disabled: false,
          options: [{ value: 'armor::', textContent: 'Armor' }],
          selectedIndex: 0,
          selectedOptions: [],
        };
      }
      if (selector === '.inv-item__name') {
        return { textContent: 'Leather Armor' };
      }
      return null;
    },
  };
  const fallbackAssignButton = {
    dataset: {
      inventoryAction: 'assign',
      itemInstanceId: '267_leather',
      itemName: 'Leather Armor',
    },
    closest(selector) {
      return selector === '.inv-item' ? fallbackAssignRow : null;
    },
  };

  await methods.handleInventoryAction.call(fallbackAssignContext, fallbackAssignButton);
  assert(fallbackAssignRequestBody.equippedSlotKey === 'armor', 'Inventory assign falls back to the first slot option when the select value is empty');

  let unequipRequestBody = null;
  global.window = {};
  globalThis.dcInventoryActionLog = [];
  global.fetch = async (url, options = {}) => {
    assert(url === '/api/inventory/character/267/item/267_leather/location', 'Inventory unequip targets the live item location endpoint');
    assert(options.credentials === 'same-origin', 'Inventory unequip uses same-origin credentials');
    assert(options.headers['X-Requested-With'] === 'XMLHttpRequest', 'Inventory unequip sends the Drupal XHR header');
    unequipRequestBody = JSON.parse(options.body || '{}');
    return {
      ok: true,
      async json() {
        return {
          success: true,
          inventory: {
            worn: {
              weapons: [],
              armor: null,
              shield: null,
              accessories: [],
            },
            carried: [{ item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }],
            equipped: [],
            stashed: [],
            currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
          },
        };
      },
    };
  };

  const unequipRenderCalls = [];
  const unequipContext = {
    currentCharacterInventoryContext: {
      characterId: 267,
      campaignId: 70,
      inventory: {
        worn: { weapons: [], armor: { item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }, shield: null, accessories: [] },
        carried: [],
        equipped: [],
        stashed: [],
        currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
      },
      currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
    },
    renderInventoryPanel(nextContext) {
      unequipRenderCalls.push(nextContext);
    },
    resolveInventoryActionContext: methods.resolveInventoryActionContext,
    resolveInventoryAssignSelection: methods.resolveInventoryAssignSelection,
    logInventoryActionTrace: methods.logInventoryActionTrace,
  };
  const unequipRow = {
    dataset: {
      itemInstanceId: '267_leather',
    },
    classList: {
      add() {},
      remove() {},
    },
    querySelector(selector) {
      if (selector === '.inv-item__name') {
        return { textContent: 'Leather Armor' };
      }
      return null;
    },
    querySelectorAll(selector) {
      if (selector === '[data-inventory-action]') {
        return [unequipButton];
      }
      return [];
    },
  };
  const unequipButton = {
    dataset: {
      inventoryAction: 'unequip',
    },
    disabled: false,
    textContent: 'Unequip',
    closest(selector) {
      return selector === '.inv-item' ? unequipRow : null;
    },
  };

  await methods.handleInventoryAction.call(unequipContext, unequipButton);
  assert(unequipRequestBody.location === 'carried', 'Inventory unequip posts a carried location update');
  assert(unequipRenderCalls.length === 2, 'Inventory unequip rerenders the panel for pending and success states');
  assert(unequipRenderCalls[0].inventoryActionFeedback.tone === 'pending', 'Inventory unequip records a pending feedback state before the response returns');
  assert(unequipRenderCalls[1].inventoryActionFeedback.tone === 'success', 'Inventory unequip records a success feedback state after the response returns');
  assert(unequipRenderCalls[1].inventoryActionFeedback.message === 'Leather Armor moved back to carried inventory.', 'Inventory unequip keeps a durable success message after rerender');
  assert(globalThis.dcInventoryActionLog.map((entry) => entry.stage).join(',') === 'click,request,success', 'Inventory unequip emits click, request, and success trace logs');
  assert(unequipContext.currentCharacterInventoryContext.inventory.worn.armor === null, 'Inventory unequip clears the worn armor state after success');
  assert(unequipContext.currentCharacterInventoryContext.inventory.carried[0].item_id === 'leather', 'Inventory unequip restores the item to carried inventory');

  let slotUnequipRequestBody = null;
  global.window = {};
  globalThis.dcInventoryActionLog = [];
  global.fetch = async (url, options = {}) => {
    assert(url === '/api/inventory/character/267/item/267_leather/location', 'Slot unequip targets the live item location endpoint');
    slotUnequipRequestBody = JSON.parse(options.body || '{}');
    return {
      ok: true,
      async json() {
        return {
          success: true,
          inventory: {
            worn: {
              weapons: [],
              armor: null,
              shield: null,
              accessories: [],
            },
            carried: [{ item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }],
            equipped: [],
            stashed: [],
            currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
          },
        };
      },
    };
  };

  const slotUnequipRenderCalls = [];
  const slotUnequipContext = {
    currentCharacterInventoryContext: {
      characterId: 267,
      campaignId: 70,
      inventory: {
        worn: { weapons: [], armor: { item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }, shield: null, accessories: [] },
        carried: [],
        equipped: [],
        stashed: [],
        currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
        slotState: {
          armor: { item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' },
        },
      },
      currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
    },
    renderInventoryPanel(nextContext) {
      slotUnequipRenderCalls.push(nextContext);
    },
    resolveInventoryActionContext: methods.resolveInventoryActionContext,
    resolveInventoryAssignSelection: methods.resolveInventoryAssignSelection,
    logInventoryActionTrace: methods.logInventoryActionTrace,
  };
  const slotUnequipButton = {
    dataset: {
      inventoryAction: 'unequip',
      itemId: 'leather',
      itemName: 'Leather Armor',
      slotKey: 'armor',
    },
    closest() {
      return null;
    },
  };

  await methods.handleInventoryAction.call(slotUnequipContext, slotUnequipButton);
  assert(slotUnequipRequestBody.location === 'carried', 'Slot unequip posts a carried location update');
  assert(slotUnequipRenderCalls.length === 2, 'Slot unequip rerenders the panel for pending and success states');
  assert(slotUnequipRenderCalls[0].inventoryActionFeedback.slotKey === 'armor', 'Slot unequip keeps the affected slot highlighted during pending state');
  assert(globalThis.dcInventoryActionLog.map((entry) => entry.stage).join(',') === 'click,request,success', 'Slot unequip emits click, request, and success trace logs');

  global.window = {};
  globalThis.dcInventoryActionLog = [];
  global.fetch = async () => ({
    ok: false,
    async json() {
      return {
        success: false,
        error: 'Inventory update failed.',
      };
    },
  });
  const failedTraceCalls = [];
  const failedContext = {
    currentCharacterInventoryContext: {
      characterId: 267,
      campaignId: 70,
      inventory: {
        worn: { weapons: [], armor: null, shield: null, accessories: [] },
        carried: [{ item_instance_id: '267_leather', item_id: 'leather', name: 'Leather Armor' }],
        equipped: [],
        stashed: [],
        currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
      },
      currency: { gp: 4, sp: 0, cp: 0, pp: 0 },
    },
    renderInventoryPanel() {},
    resolveInventoryActionContext: methods.resolveInventoryActionContext,
    resolveInventoryAssignSelection: methods.resolveInventoryAssignSelection,
    logInventoryActionTrace: methods.logInventoryActionTrace,
  };

  await methods.handleInventoryAction.call(failedContext, assignButton).catch(() => {});
  globalThis.dcInventoryActionLog.forEach((entry) => {
    failedTraceCalls.push(entry);
  });
  assert(failedTraceCalls.map((entry) => entry.stage).join(',') === 'click,request,failure', 'Inventory failures emit click, request, and failure trace logs');
  assert(failedTraceCalls[2].error === 'Inventory update failed.', 'Inventory failure trace records the returned error message');

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
})().catch((error) => {
  failed++;
  console.error(`  ✗ Unexpected test harness failure: ${error.message}`);
  console.log('\n===================================');
  console.log(`Passed: ${passed}`);
  console.log(`Failed: ${failed}`);
  console.log('===================================');
  console.log('SOME TESTS FAILED');
  process.exitCode = 1;
});
