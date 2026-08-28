/**
 * @file
 * Unit tests for ChatPanel (Phase 7).
 *
 * Run with:
 *   node tests/chat_panel_test.js
 */

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${msg}`);
  } else {
    failed++;
    console.error(`  ✗ FAIL: ${msg}`);
  }
}

const fs   = require('fs');
const path = require('path');

const { loadModuleExport } = require('./helpers/js-module.js');
const { installDom, makeScopedContainer } = require('./helpers/fake-dom.js');

function loadClass(relPath, className) {
  return loadModuleExport(relPath, className);
}

const ChatPanel    = loadClass('../js/v2/panels/ChatPanel.js', 'ChatPanel');
const GameEventBus = loadClass('../js/v2/GameEventBus.js',    'GameEventBus');

// ---------------------------------------------------------------------------
// DOM
//
// ChatPanel resolves every element by global id and builds lines with
// createElement()/appendChild(), so the shared fake DOM models it directly.
// ---------------------------------------------------------------------------

const CHAT_IDS = [
  'hexmap-chat',
  'chat-panel-title',
  'chat-log',
  'map-initiative-chat-log',
  'chat-summary',
  'chat-input',
  'chat-send',
  'chat-form',
  'chat-channel-tabs',
  'chat-channel-indicator',
  'chat-channel-label',
  'chat-session-tabs',
  'chat-quick-actions',
];

// Short assertion keys -> real element ids.
const ALIASES = {
  log: 'chat-log',
  input: 'chat-input',
  send: 'chat-send',
  'channel-tabs': 'chat-channel-tabs',
  'session-tabs': 'chat-session-tabs',
  summary: 'chat-summary',
  shell: 'hexmap-chat',
};

function makeContainer() {
  const dom = installDom(CHAT_IDS);
  const container = makeScopedContainer('chat', ['log', 'input', 'send'], dom.document);
  dom.document.body.appendChild(container);
  container._elements = new Proxy({}, {
    get(_t, key) {
      return dom.document.getElementById(ALIASES[key] || key);
    },
  });
  return container;
}

global.CSS = { escape: (s) => String(s).replace(/[!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~]/g, '\\$&').replace(/^-/, '\\-') };

function mountChatPanel() {
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();
  return { bus, container, panel };
}

// ---------------------------------------------------------------------------
// Tests
//
// Channel definitions are server-driven (loaded into `panel.channels`), so tab
// rendering is exercised through renderChannelTabs() rather than init().
// Message state lives in the rendered log rather than a `_messages` map, so
// assertions read the DOM the panel actually produces.
// ---------------------------------------------------------------------------

console.log('\n=== ChatPanel — renders a tab per active channel ===');
{
  const { container, panel } = mountChatPanel();
  panel.channels = {
    room:   { key: 'room',   label: 'Room',   type: 'room',   active: true },
    ooc:    { key: 'ooc',    label: 'OOC',    type: 'ooc',    active: true },
    gm:     { key: 'gm',     label: 'GM',     type: 'gm',     active: true },
    hidden: { key: 'hidden', label: 'Hidden', type: 'ooc',    active: false },
  };
  panel.renderChannelTabs();

  const html = container._elements['channel-tabs'].innerHTML;
  assert(html.includes('Room'), 'Room tab rendered');
  assert(html.includes('OOC'),  'OOC tab rendered');
  assert(html.includes('GM'),   'GM tab rendered');
  assert(!html.includes('Hidden'), 'inactive channels are not rendered');
  assert(html.includes('chat-channel-tab--active'), 'active tab has class');
  assert(html.includes('data-channel="room"'), 'tab carries its channel key');
}

console.log('\n=== ChatPanel — non-room tabs get a close control ===');
{
  const { container, panel } = mountChatPanel();
  panel.channels = {
    room: { key: 'room', label: 'Room', type: 'room', active: true },
    ooc:  { key: 'ooc',  label: 'OOC',  type: 'ooc',  active: true },
  };
  panel.renderChannelTabs();

  const tabs = container._elements['channel-tabs'];
  const roomTab = tabs.querySelector('[data-channel="room"]');
  const oocTab  = tabs.querySelector('[data-channel="ooc"]');
  assert(roomTab && !roomTab.querySelector('.chat-channel-tab__close'), 'room tab has no close control');
  assert(oocTab && !!oocTab.querySelector('.chat-channel-tab__close'), 'ooc tab has a close control');
}

console.log('\n=== ChatPanel — chat:message-received appends to log ===');
{
  const { bus, container } = mountChatPanel();
  bus.emit('chat:message-received', {
    channel: 'room',
    line: { speaker: 'Aria', message: 'Hello!', type: 'say' },
  });

  const log = container._elements['log'];
  assert(log.children.length === 1, 'one line appended');
  const html = log.innerHTML;
  assert(html.includes('Aria'),   'speaker in line');
  assert(html.includes('Hello!'), 'message in line');
  assert(html.includes('data-channel="room"'), 'line tagged with its channel');
}

console.log('\n=== ChatPanel — lines without speaker and message are ignored ===');
{
  const { bus, container } = mountChatPanel();
  bus.emit('chat:message-received', { channel: 'room', line: { type: 'say' } });

  assert(container._elements['log'].children.length === 0, 'incomplete line not appended');
}

console.log('\n=== ChatPanel — message content is escaped, never live markup ===');
{
  const { bus, container } = mountChatPanel();
  bus.emit('chat:message-received', {
    channel: 'room',
    line: { speaker: '<script>', message: '<b>bold</b>', type: 'say' },
  });

  const log = container._elements['log'];
  const html = log.innerHTML;
  assert(!html.includes('<script>'), 'speaker markup not emitted raw');
  assert(!html.includes('<b>bold</b>'), 'message markup not emitted raw');
  assert(log.textContent.includes('<b>bold</b>'), 'message preserved as literal text');
}

console.log('\n=== ChatPanel — multiple messages accumulate in order ===');
{
  const { bus, container } = mountChatPanel();
  bus.emit('chat:message-received', { channel: 'room', line: { speaker: 'Aria', message: 'First',  type: 'say' } });
  bus.emit('chat:message-received', { channel: 'room', line: { speaker: 'Bard', message: 'Second', type: 'say' } });

  const log = container._elements['log'];
  assert(log.children.length === 2, 'both lines appended');
  const html = log.innerHTML;
  assert(html.indexOf('First') < html.indexOf('Second'), 'lines retain arrival order');
}

console.log('\n=== ChatPanel — destroy unsubscribes ===');
{
  const { bus, container, panel } = mountChatPanel();
  panel.destroy();

  bus.emit('chat:message-received', {
    channel: 'room',
    line: { speaker: 'Ghost', message: 'Should not appear', type: 'say' },
  });

  assert(container._elements['log'].children.length === 0, 'destroyed panel appends nothing');
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed === 0) console.log('ALL TESTS PASSED');
else console.error(`${failed} TESTS FAILED`);
console.log('===================================\n');

process.exit(failed > 0 ? 1 : 0);
