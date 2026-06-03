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

function loadClass(relPath, className) {
  let src = fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
  src = src.replace(/^import[\s\S]*?;\s*$/gm, '');
  // Remove export keyword for node eval
  src = src.replace(/^export\s+/gm, '');
  return new Function(src + `\nreturn { ${className} };`)()[className];
}

const ChatPanel    = loadClass('../js/v2/panels/ChatPanel.js', 'ChatPanel');
const GameEventBus = loadClass('../js/v2/GameEventBus.js',    'GameEventBus');

// ---------------------------------------------------------------------------
// DOM stub
// ---------------------------------------------------------------------------

function makeLog() {
  const children = [];
  return {
    _children: children,
    innerHTML: '',
    scrollTop: 0,
    get scrollHeight() { return children.length * 20; },
    appendChild(el) { children.push(el); this.innerHTML += el.outerHTML ?? ''; },
    querySelectorAll(sel) { return []; },
  };
}

function makeInput() {
  return { value: '', _listeners: {}, addEventListener(e, f) { (this._listeners[e] = this._listeners[e] || []).push(f); } };
}

function makeBtn() {
  return { _listeners: {}, addEventListener(e, f) { (this._listeners[e] = this._listeners[e] || []).push(f); }, classList: { add(){}, toggle(){} } };
}

function makeTabs() {
  const btns = [];
  return {
    _btns: btns,
    innerHTML: '',
    _listeners: {},
    addEventListener(e, f) { (this._listeners[e] = this._listeners[e] || []).push(f); },
    querySelectorAll(sel) {
      if (sel.includes('data-channel')) return btns;
      return [];
    },
    querySelector(sel) {
      const m = sel.match(/\[data-channel="([^"]+)"\]/);
      if (m) return btns.find((b) => b.dataset && b.dataset.channel === m[1]) || null;
      return null;
    },
  };
}

function makeTurnStatus() {
  return { hidden: false, textContent: '', dataset: {} };
}

function makeContainer() {
  const elements = {
    log:          makeLog(),
    input:        makeInput(),
    send:         makeBtn(),
    'turn-status': makeTurnStatus(),
    'channel-tabs': makeTabs(),
    'session-tabs': null,
  };
  return {
    querySelector(sel) {
      const m = sel.match(/\[data-chat="([^"]+)"\]/);
      return m ? (elements[m[1]] ?? null) : null;
    },
    _elements: elements,
  };
}

function makeSimpleElement() {
  return {
    outerHTML: '<div></div>',
    className: '',
    dataset: {},
    innerHTML: '',
    appendChild() {},
  };
}

// Patch document.createElement used inside _appendLineToEl
global.document = {
  createElement(tag) {
    const el = {
      tag,
      className: '',
      dataset: {},
      innerHTML: '',
      outerHTML: '',
    };
    // Update outerHTML when innerHTML is set
    Object.defineProperty(el, 'innerHTML', {
      get() { return this._html || ''; },
      set(v) { this._html = v; el.outerHTML = `<${tag} class="${el.className}">${v}</${tag}>`; },
    });
    return el;
  },
};

// CSS.escape stub
global.CSS = { escape: (s) => String(s).replace(/[!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~]/g, '\\$&').replace(/^-/, '\\-') };

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

console.log('\n=== ChatPanel — init creates channel tabs ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  const tabsEl = container._elements['channel-tabs'];
  assert(tabsEl.innerHTML.includes('Room'), 'Room tab rendered');
  assert(tabsEl.innerHTML.includes('OOC'), 'OOC tab rendered');
  assert(tabsEl.innerHTML.includes('GM'), 'GM tab rendered');
  assert(tabsEl.innerHTML.includes('Combat'), 'Combat tab rendered');
  assert(tabsEl.innerHTML.includes('chat-channel-tab--active'), 'active tab has class');
}

console.log('\n=== ChatPanel — chat:message-received appends to log ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:message-received', {
    channel: 'room',
    line: { speaker: 'Aria', message: 'Hello!', type: 'say' },
  });

  const log = container._elements['log'];
  assert(log._children.length === 1, 'one line appended');
  assert(log._children[0].innerHTML.includes('Aria'), 'speaker in line');
  assert(log._children[0].innerHTML.includes('Hello!'), 'message in line');
}

console.log('\n=== ChatPanel — message for inactive channel does not append ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:message-received', {
    channel: 'ooc',
    line: { speaker: 'Bob', message: 'Test', type: 'ooc' },
  });

  const log = container._elements['log'];
  assert(log._children.length === 0, 'no line added to log for inactive channel');
  // But message should be stored
  assert(panel._messages.get('ooc').length === 1, 'message stored in ooc bucket');
}

console.log('\n=== ChatPanel — chat:history-loaded replaces channel messages ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  // Pre-load some messages
  bus.emit('chat:message-received', { channel: 'room', line: { speaker: 'Old', message: 'stale', type: 'say' } });

  // Load full history
  bus.emit('chat:history-loaded', {
    channel: 'room',
    lines: [
      { speaker: 'Aria', message: 'First', type: 'say' },
      { speaker: 'Bard', message: 'Second', type: 'say' },
    ],
  });

  assert(panel._messages.get('room').length === 2, 'history replaces old messages');
  assert(panel._messages.get('room')[0].speaker === 'Aria', 'first history line correct');
}

console.log('\n=== ChatPanel — messages HTML-escaped ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:message-received', {
    channel: 'room',
    line: { speaker: '<script>', message: '<b>bold</b>', type: 'say' },
  });

  const log = container._elements['log'];
  assert(log._children[0].innerHTML.includes('&lt;script&gt;'), 'speaker HTML-escaped');
  assert(log._children[0].innerHTML.includes('&lt;b&gt;'), 'message HTML-escaped');
}

console.log('\n=== ChatPanel — scrolls to bottom on new message ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:message-received', {
    channel: 'room',
    line: { speaker: 'X', message: 'Hi', type: 'say' },
  });

  const log = container._elements['log'];
  assert(log.scrollTop === log.scrollHeight, 'scrollTop set to scrollHeight');
}

console.log('\n=== ChatPanel — chat:turn-status-changed updates turn status ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:turn-status-changed', { status: 'waiting', pending: true });
  const ts = container._elements['turn-status'];
  assert(ts.hidden === false, 'turn-status visible when pending');
  assert(ts.dataset.status === 'waiting', 'status data attribute set');
  assert(ts.textContent === 'waiting', 'status text shown');

  bus.emit('chat:turn-status-changed', { status: '', pending: false });
  assert(ts.hidden === true, 'turn-status hidden when not pending');
}

console.log('\n=== ChatPanel — chat:pending-request shows system line ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:pending-request', { requestId: 'r1', summary: 'Thinking...' });
  const log = container._elements['log'];
  assert(log._children.length === 1, 'system line appended');
  assert(log._children[0].innerHTML.includes('Thinking'), 'summary in system line');
  assert(panel._pendingRequests.has('r1'), 'request tracked');
}

console.log('\n=== ChatPanel — chat:request-settled removes pending request ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:pending-request',  { requestId: 'r1', summary: 'Doing stuff' });
  bus.emit('chat:request-settled', { requestId: 'r1', result: 'success' });

  assert(!panel._pendingRequests.has('r1'), 'pending request removed after settled');
  const log = container._elements['log'];
  assert(log._children.length === 2, 'settled system line appended');
  assert(log._children[1].innerHTML.includes('✅'), 'success icon shown');
}

console.log('\n=== ChatPanel — room:changed clears room messages ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:message-received', { channel: 'room', line: { message: 'hi', type: 'say' } });
  assert(panel._messages.get('room').length === 1, 'message present before room:changed');

  bus.emit('room:changed', {});
  assert(panel._messages.get('room').length === 0, 'room messages cleared after room:changed');
}

console.log('\n=== ChatPanel — user:chat-submitted fires on send click ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  let emitted = null;
  bus.on('user:chat-submitted', (data) => { emitted = data; });

  const input = container._elements['input'];
  const send  = container._elements['send'];
  input.value = 'Attack the goblin!';
  // Trigger click listener
  send._listeners['click'].forEach((fn) => fn());

  assert(emitted !== null, 'user:chat-submitted fired');
  assert(emitted.message === 'Attack the goblin!', 'message payload correct');
  assert(emitted.channel === 'room', 'default channel is room');
  assert(input.value === '', 'input cleared after submit');
}

console.log('\n=== ChatPanel — empty message not submitted ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  let count = 0;
  bus.on('user:chat-submitted', () => count++);

  const input = container._elements['input'];
  const send  = container._elements['send'];
  input.value = '   '; // whitespace only
  send._listeners['click'].forEach((fn) => fn());

  assert(count === 0, 'whitespace-only message not submitted');
}

console.log('\n=== ChatPanel — message capped at MAX per channel ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  for (let i = 0; i < 210; i++) {
    panel._messages.get('room').push({ message: `line${i}`, type: 'say' });
  }
  // Trigger trim via next message received
  bus.emit('chat:message-received', { channel: 'room', line: { message: 'overflow', type: 'say' } });

  assert(panel._messages.get('room').length <= 200, 'messages capped at 200');
}

console.log('\n=== ChatPanel — line without speaker renders message only ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();

  bus.emit('chat:message-received', {
    channel: 'room',
    line: { message: 'System notification', type: 'system' },
  });

  const log = container._elements['log'];
  assert(!log._children[0].innerHTML.includes('chat-line__speaker'), 'no speaker span when no speaker');
  assert(log._children[0].innerHTML.includes('System notification'), 'message still rendered');
}

console.log('\n=== ChatPanel — destroy unsubscribes ===');
{
  const bus = new GameEventBus();
  const container = makeContainer();
  const panel = new ChatPanel(container, bus);
  panel.init();
  panel.destroy();

  bus.emit('chat:message-received', { channel: 'room', line: { message: 'ghost', type: 'say' } });
  assert(panel._messages.size === 0, 'messages cleared on destroy');
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
