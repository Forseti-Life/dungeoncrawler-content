/**
 * @file
 * Regression coverage for canonical chat-line normalization metadata.
 *
 * Run with:
 *   node tests/chat_panel_line_contract_test.js
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

const sourcePath = path.resolve(__dirname, '../js/v2/panels/ChatPanel.js');
const source = fs.readFileSync(sourcePath, 'utf8');

const resolveChatChannelKeySource = toFunction(
  source,
  '  resolveChatChannelKey(view = this.activeSessionView, channelKey = null) {',
  'function resolveChatChannelKey(view = this.activeSessionView, channelKey = null) {'
);
const normalizeChatLineRecordSource = toFunction(
  source,
  '  normalizeChatLineRecord(line = {}) {',
  'function normalizeChatLineRecord(line = {}) {'
);
const normalizeChatLineRecordsSource = toFunction(
  source,
  '  normalizeChatLineRecords(lines = [], options = {}) {',
  'function normalizeChatLineRecords(lines = [], options = {}) {'
);
const buildEncounterEventChatLineSource = toFunction(
  source,
  '  buildEncounterEventChatLine(event = {}) {',
  'function buildEncounterEventChatLine(event = {}) {'
);
const renderChatLineRecordsSource = toFunction(
  source,
  '  renderChatLineRecords(lines = [], view = this.activeSessionView, options = {}) {',
  'function renderChatLineRecords(lines = [], view = this.activeSessionView, options = {}) {'
);
const renderRoomChatHistorySource = toFunction(
  source,
  '  renderRoomChatHistory(result) {',
  'function renderRoomChatHistory(result) {'
);
const renderSessionViewDataSource = toFunction(
  source,
  '  renderSessionViewData(view, data) {',
  'function renderSessionViewData(view, data) {'
);
const handleGameEventsSource = toFunction(
  source,
  '  handleGameEvents(event) {',
  'function handleGameEvents(event) {'
);
const postSessionViewMessageSource = extractMethodSource(
  source,
  '  async postSessionViewMessage(speaker, message, characterId) {'
);
const loadSessionViewMessagesSource = toFunction(
  source,
  '  async loadSessionViewMessages(view, options = {}) {',
  'async function loadSessionViewMessages(view, options = {}) {'
);

const factory = new Function(`
${resolveChatChannelKeySource}
${normalizeChatLineRecordSource}
${normalizeChatLineRecordsSource}
${buildEncounterEventChatLineSource}
${renderChatLineRecordsSource}
${renderRoomChatHistorySource}
${renderSessionViewDataSource}
${handleGameEventsSource}
${loadSessionViewMessagesSource}
return {
  resolveChatChannelKey,
  normalizeChatLineRecord,
  normalizeChatLineRecords,
  buildEncounterEventChatLine,
  renderChatLineRecords,
  renderRoomChatHistory,
  renderSessionViewData,
  handleGameEvents,
  loadSessionViewMessages,
};
`);

const {
  resolveChatChannelKey,
  normalizeChatLineRecord,
  normalizeChatLineRecords,
  buildEncounterEventChatLine,
  renderChatLineRecords,
  renderRoomChatHistory,
  renderSessionViewData,
  handleGameEvents,
  loadSessionViewMessages,
} = factory();

console.log('\n=== ChatPanel canonical line contract ===');

{
  const panel = {
    activeSessionView: 'room',
    activeChannel: 'room',
    resolveChatChannelKey,
  };
  const normalized = normalizeChatLineRecord.call(panel, {
    speaker: 'System',
    message: 'Queued locally.',
    type: 'system',
    transient: true,
  });

  assert(normalized.source === 'local-ui', 'default normalized lines are classified as local UI');
  assert(normalized.authority === 'local', 'default normalized lines are marked local-authority');
  assert(normalized.messageClass === 'local_ui_notice', 'default normalized lines use the local notice category');
  assert(normalized.channel === 'room' && normalized.view === 'room', 'default normalized lines inherit the active room view/channel');
  assert(normalized.persistent === false, 'transient normalized lines are not marked persistent');
}

{
  const panel = {
    activeSessionView: 'room',
    activeChannel: 'room',
    resolveChatChannelKey,
  };
  const normalized = normalizeChatLineRecord.call(panel, {
    speaker: 'System',
    message: 'Round 0: Turn 1: Actor System: Actions 3/3: Current turn: Burasco.',
    type: 'system',
    transient: true,
    turn_prompt: true,
    internal_log: true,
  });

  assert(normalized.turnPrompt === true, 'turn prompts preserve turn_prompt metadata');
  assert(normalized.transient === false, 'turn prompts are never treated as transient');
  assert(normalized.persistent === true, 'turn prompts are always persistent by default');
  assert(normalized.internalLog === true, 'turn prompts preserve internal_log metadata');
}

{
  const panel = {
    resolveChatChannelKey,
    normalizeChatLineRecord,
    activeSessionView: 'room',
    activeChannel: 'room',
  };
  const normalized = normalizeChatLineRecords.call(panel, [
    { speaker: 'Narrator', message: 'Round 2 begins.', type: 'gm', lineId: 'a' },
    { speaker: 'Initiative Order', message: 'Initiative order is resolving nearby NPC turns...', type: 'npc', lineId: 'b' },
  ], {
    source: 'room-stream',
    authority: 'authoritative',
    messageClass: 'authoritative_progress',
    view: 'room',
    channel: 'whisper:npc-4',
  });

  assert(normalized.length === 2, 'batch normalization preserves the full line set');
  assert(normalized.every((line) => line.source === 'room-stream'), 'batch normalization applies the canonical source override');
  assert(normalized.every((line) => line.authority === 'authoritative'), 'batch normalization applies authoritative ownership');
  assert(normalized.every((line) => line.messageClass === 'authoritative_progress'), 'batch normalization applies the progress category');
  assert(normalized.every((line) => line.channel === 'whisper:npc-4' && line.view === 'room'), 'batch normalization applies the target view/channel');
}

{
  const panel = {
    resolveEncounterActorName() {
      return '';
    },
    extractActorNameFromNarration() {
      return '';
    },
  };
  const chatLine = buildEncounterEventChatLine.call(panel, {
    id: 42,
    type: 'round_start',
    narration: 'Round 4 begins.',
    data: { round: 4 },
  });

  assert(chatLine.source === 'encounter-event', 'encounter chat lines carry the encounter-event source');
  assert(chatLine.authority === 'authoritative', 'encounter chat lines are authoritative');
  assert(chatLine.messageClass === 'authoritative_transcript', 'encounter chat lines are categorized as authoritative transcript');
  assert(chatLine.eventId === '42', 'encounter chat lines preserve the originating event id');
}

{
  const appended = [];
  const panel = {
    stateManager: {
      hexmap: {
        characterData: { name: 'Burasco' },
      },
    },
    activeChannel: 'room',
    getChatContext() {
      return { campaignId: 1, roomId: 'room-1', characterId: 7 };
    },
    buildEncounterEventChatLine(event) {
      const actorName = String(event?.data?.actor_name || '').trim() || 'Narrator';
      return {
        speaker: 'Narrator',
        message: `${actorName}'s turn begins.`,
        type: 'gm',
        lineId: `encounter-event-${event.id}`,
        created: 0,
        round: event?.data?.round,
        actorId: 'actor-1',
        actorName,
        source: 'encounter-event',
        authority: 'authoritative',
        messageClass: 'authoritative_transcript',
        eventId: String(event.id || ''),
      };
    },
    appendChatLineToTarget(target, speaker, message, type, options = {}) {
      appended.push({ target, speaker, message, type, options });
    },
  };

  handleGameEvents.call(panel, {
    detail: {
      events: [
        { id: 5, type: 'turn_start', data: { round: 1, actor_name: 'Burasco' } },
      ],
    },
  });

  assert(appended.length === 2, 'turn_start emits a transcript line plus a player turn prompt');
  assert(appended[1].speaker === 'System', 'player turn prompt uses the System speaker');
  assert(appended[1].options.turn_prompt === true, 'player turn prompt carries turn_prompt metadata');
  assert(appended[1].target?.channelKey === 'room', 'player turn prompt is always routed to the room channel');
}

{
  const appended = [];
  const panel = {
    stateManager: {
      hexmap: {
        characterData: { name: 'Burasco' },
        resolveActiveRoomId() {
          return 'room-1';
        },
      },
    },
    activeChannel: 'room',
    getChatContext() {
      return { campaignId: 1, roomId: 'room-1', characterId: 7 };
    },
    buildEncounterEventChatLine(event) {
      const actorName = String(event?.data?.actor_name || '').trim() || 'Narrator';
      return {
        speaker: 'Narrator',
        message: `${actorName}'s turn begins.`,
        type: 'gm',
        lineId: `encounter-event-${event.id}`,
        created: 0,
        round: event?.data?.round,
        actorId: 'actor-1',
        actorName,
        source: 'encounter-event',
        authority: 'authoritative',
        messageClass: 'authoritative_transcript',
        eventId: String(event.id || ''),
      };
    },
    appendChatLineToTarget(target, speaker, message, type, options = {}) {
      appended.push({ target, speaker, message, type, options });
    },
  };

  handleGameEvents.call(panel, {
    detail: {
      events: [
        { id: 9, type: 'turn_start', data: { round: 1, room_id: 'room-2', actor_name: 'Burasco' } },
        { id: 10, type: 'turn_start', data: { round: 1, room_id: 'room-1', actor_name: 'Burasco' } },
      ],
    },
  });

  assert(appended.length === 4, 'encounter transcript rendering appends all authoritative turn_start events provided');
}

{
  const appended = [];
  let remembered = null;
  const panel = {
    _el: { chatLog: { innerHTML: '' } },
    activeSessionView: 'room',
    activeChannel: 'room',
    resolveChatChannelKey,
    normalizeChatLineRecord,
    normalizeChatLineRecords,
    appendChatLine(speaker, message, type, options) {
      appended.push({ speaker, message, type, options });
    },
    rememberChatLines(view, lines, options) {
      remembered = { view, lines, options };
    },
  };

  renderChatLineRecords.call(panel, [{
    speaker: 'Narrator',
    message: 'Round 1 begins.',
    type: 'gm',
    lineId: 'encounter-event-1',
    source: 'encounter-event',
    authority: 'authoritative',
    messageClass: 'authoritative_transcript',
    eventId: '1',
  }], 'room', {
    context: { roomId: 'room-1' },
    channelKey: 'room',
  });

  assert(appended.length === 1, 'renderChatLineRecords appends normalized records');
  assert(appended[0].options.source === 'encounter-event', 'renderChatLineRecords forwards canonical source metadata to the renderer');
  assert(appended[0].options.authority === 'authoritative', 'renderChatLineRecords forwards authority metadata to the renderer');
  assert(appended[0].options.messageClass === 'authoritative_transcript', 'renderChatLineRecords forwards the message category to the renderer');
  assert(remembered && remembered.lines[0].eventId === '1', 'renderChatLineRecords remembers canonical event metadata');
}

{
  const appended = [];
  const panel = {
    _el: { chatLog: { innerHTML: '' } },
    activeSessionView: 'room',
    activeChannel: 'room',
    resolveChatChannelKey,
    normalizeChatLineRecord,
    normalizeChatLineRecords,
    appendChatLine(speaker, message) {
      appended.push({ speaker, message });
    },
    rememberChatLines() {},
  };

  renderChatLineRecords.call(panel, [
    {
      speaker: 'B',
      message: 'second',
      type: 'gm',
      lineId: 'encounter-event-2',
      created: 1000,
      source: 'encounter-event',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
      eventId: '2',
    },
    {
      speaker: 'A',
      message: 'first',
      type: 'gm',
      lineId: 'encounter-event-1',
      created: 1000,
      source: 'encounter-event',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
      eventId: '1',
    },
    {
      speaker: 'C',
      message: 'earlier',
      type: 'gm',
      lineId: 'encounter-event-3',
      created: 900,
      source: 'encounter-event',
      authority: 'authoritative',
      messageClass: 'authoritative_transcript',
      eventId: '3',
    },
    {
      speaker: 'Local',
      message: 'no timestamp',
      type: 'system',
      lineId: 'local-1',
      created: 0,
      source: 'local-ui',
      authority: 'local',
      messageClass: 'local_ui_notice',
    },
  ], 'room', {
    context: { roomId: 'room-1' },
    channelKey: 'room',
  });

  const order = appended.map((line) => line.speaker).join(',');
  assert(order === 'C,A,B,Local', 'renderChatLineRecords sorts by (created,eventId) with stable handling for missing timestamps');
}

{
  let remembered = null;
  let rendered = null;
  let summary = null;
  let persistedEncounterHistoryCalls = 0;
  let context = { campaignId: 22, roomId: 'room-9' };
  const panel = {
    activeSessionView: 'room',
    activeChannel: 'room',
    resolveChatChannelKey,
    getChatContext() {
      return context;
    },
    rememberRoomTurnSequence() {},
    rememberChatLines(view, lines) {
      remembered = { view, lines };
      return lines;
    },
    renderChatLineRecords(lines, view) {
      rendered = { lines, view };
    },
    updateChatSummary(lines) {
      summary = lines;
    },
    renderPersistedEncounterEventHistory() {
      persistedEncounterHistoryCalls += 1;
    },
    scrollChatToBottom() {},
    syncChatTurnStatus() {},
    resolvePinnedChatRoomTarget() {
      return null;
    },
    bus: { emit() {} },
  };

  renderRoomChatHistory.call(panel, {
    success: true,
    data: {
      messages: [{
        speaker: 'Guide',
        message: 'Stay close.',
        type: 'npc',
        timestamp: '2026-06-01T12:00:00Z',
      }],
      turn_sequence: [],
    },
  });

  assert(remembered?.view === 'room', 'room history is remembered in the room view');
  assert(remembered?.lines[0]?.source === 'room-history', 'room history lines are tagged with the room-history source');
  assert(remembered?.lines[0]?.authority === 'authoritative', 'room history lines are authoritative');
  assert(remembered?.lines[0]?.messageClass === 'authoritative_transcript', 'room history lines are tagged as authoritative transcript');
  assert(rendered?.lines[0]?.channel === 'room' && rendered?.lines[0]?.view === 'room', 'room history lines preserve the room view/channel metadata');
  assert(Array.isArray(summary) && summary.length === 1, 'room history summary still operates on the normalized lines');
  assert(persistedEncounterHistoryCalls === 0, 'room history rendering does not trigger persisted encounter transcript fetches directly');

  renderRoomChatHistory.call(panel, {
    success: true,
    data: {
      messages: [{
        speaker: 'Guide',
        message: 'Still here.',
        type: 'npc',
        timestamp: '2026-06-01T12:00:05Z',
      }],
      turn_sequence: [],
    },
  });
  assert(persistedEncounterHistoryCalls === 0, 'repeat room history render keeps persisted encounter fetch count unchanged');

  context = { campaignId: 22, roomId: 'room-10' };
  renderRoomChatHistory.call(panel, {
    success: true,
    data: {
      messages: [{
        speaker: 'Guide',
        message: 'Welcome next room.',
        type: 'npc',
        timestamp: '2026-06-01T12:00:10Z',
      }],
      turn_sequence: [],
    },
  });
  assert(persistedEncounterHistoryCalls === 0, 'room context changes do not trigger persisted encounter transcript fetches from this renderer');
}

{
  let remembered = null;
  let rendered = false;
  const panel = {
    activeSessionView: 'room',
    activeChannel: 'room',
    resolveChatChannelKey,
    getChatContext() {
      return { campaignId: 77, roomId: 'room-a' };
    },
    rememberChatLines(view, lines, options) {
      remembered = { view, lines, options };
      return lines;
    },
    renderChatLineRecords() {
      rendered = true;
    },
    updateChatSummary() {},
    renderPersistedEncounterEventHistory() {},
    scrollChatToBottom() {},
    syncChatTurnStatus() {},
    resolvePinnedChatRoomTarget() {
      return null;
    },
    bus: { emit() {} },
  };

  renderRoomChatHistory.call(panel, {
    success: true,
    data: {
      channel: 'whisper:npc-1',
      messages: [{
        speaker: 'Scout',
        message: 'Keep quiet.',
        type: 'npc',
        timestamp: '2026-06-01T12:00:00Z',
      }],
      turn_sequence: [],
    },
  });

  assert(remembered?.options?.channelKey === 'room', 'room history remembers entries using the currently active room channel key');
  assert(rendered === true, 'room history rendering refreshes the visible transcript for the active room view');
}

{
  let remembered = null;
  let rendered = null;
  const panel = {
    resolveChatChannelKey,
    getChatContext() {
      return { campaignId: 17, roomId: 'room-4' };
    },
    resolveSessionLineType(msg) {
      return msg.type || 'player';
    },
    rememberChatLines(view, lines) {
      remembered = { view, lines };
      return lines;
    },
    renderChatLineRecords(lines, view) {
      rendered = { lines, view };
    },
    updateChatSummary() {},
    getRememberedChatLines() {
      return [];
    },
    appendChatLine() {},
    scrollChatToBottom() {},
  };

  renderSessionViewData.call(panel, 'party', {
    messages: [{
      id: 11,
      source_message_id: 7,
      speaker: 'Scout',
      message: 'I hear footsteps.',
      type: 'player',
      created: 123,
    }],
  });

  assert(remembered?.view === 'party', 'session view messages are remembered under their session view');
  assert(remembered?.lines[0]?.source === 'session-view:party', 'session view messages are tagged with the session-view source');
  assert(remembered?.lines[0]?.authority === 'authoritative', 'session view messages are authoritative');
  assert(remembered?.lines[0]?.messageClass === 'authoritative_transcript', 'session view messages are tagged as authoritative transcript');
  assert(rendered?.lines[0]?.channel === 'party' && rendered?.lines[0]?.view === 'party', 'session view messages preserve canonical view/channel metadata');
}

{
  let remembered = null;
  let rendered = null;
  const panel = {
    resolveChatChannelKey,
    getChatContext() {
      return { campaignId: 17, roomId: 'room-4' };
    },
    resolveSessionLineType(msg) {
      return msg.type || 'player';
    },
    rememberChatLines(view, lines, options = {}) {
      remembered = { view, lines, options };
      return lines;
    },
    renderChatLineRecords(lines, view) {
      rendered = { lines, view };
    },
    updateChatSummary() {},
    getRememberedChatLines() {
      return [];
    },
    appendChatLine() {},
    scrollChatToBottom() {},
  };

  renderSessionViewData.call(panel, 'system-log', {
    messages: [
      {
        id: 71,
        speaker: 'Aria',
        speaker_type: 'player',
        message: 'I open the chest.',
        message_type: 'dialogue',
        type: 'player',
        created: 100,
      },
      {
        id: 72,
        speaker: 'System',
        speaker_type: 'system',
        message: 'Perception check: d20 + 4 = 18',
        message_type: 'mechanical',
        type: 'system',
        metadata: { dice_rolls: [{ total: 18 }] },
        created: 101,
      },
    ],
  });

  assert(remembered?.view === 'system-log', 'system view remembers messages under system-log');
  assert(remembered?.options?.replace === true, 'system view replaces cached records on fresh payloads');
  assert((remembered?.lines?.length || 0) === 1, 'system view filters out non-system dialogue lines');
  assert(remembered?.lines?.[0]?.speaker === 'System', 'system view retains only system/mechanical content');
  assert(rendered?.lines?.length === 1 && rendered?.view === 'system-log', 'system view renders only the filtered system/mechanical lines');
}

assert(
  source.includes("this.bus.emit('user:session-view-requested', { view, options });"),
  'session view loading emits a scoped session-view request on the event bus'
);
assert(
  source.includes("const isReadOnly = view === 'system-log';"),
  'system-log view is enforced as read-only in the chat input controls'
);
assert(
  postSessionViewMessageSource.includes("case 'system-log':"),
  'session post flow explicitly handles system-log as a non-postable view'
);
assert(
  postSessionViewMessageSource.includes('this.prefetchSessionViews();'),
  'session post flow refreshes sibling session views after writes'
);

console.log(`\nPassed: ${passed}`);
if (failed > 0) {
  console.error(`Failed: ${failed}`);
  process.exit(1);
}
console.log('All chat-line contract tests passed.');
