/**
 * @file panels/ChatPanel.js
 *
 * Multi-channel chat log with GM message rendering, pending turn status,
 * session view tabs, round-order lines, and scroll behaviour.
 *
 * The panel is PURE UI. It renders server-pushed messages from the bus.
 * No game logic, no server calls.
 *
 * DOM bindings (via [data-chat="key"] on descendant elements):
 *   log           — scrollable message list
 *   input         — text input for composing
 *   send          — submit button
 *   turn-status   — pending-turn indicator area
 *   channel-tabs  — tab list container
 *   session-tabs  — session-view tab list container (optional)
 *
 * Channels (built-in):
 *   room          — in-character room dialogue
 *   ooc           — out-of-character
 *   gm            — GM responses / narration
 *   combat-log    — combat results / system log
 *
 * Line types styled via CSS class  chat-line--<type>:
 *   say, emote, roll, system, gm, ooc, combat
 *
 * Subscribes to bus events:
 *   chat:message-received      — { line: LineRecord, channel }
 *   chat:history-loaded        — { lines: LineRecord[], channel }
 *   chat:turn-status-changed   — { status, pending }
 *   chat:pending-request       — { requestId, summary }
 *   chat:request-settled       — { requestId, result }
 *   room:changed               — clear room-channel message cache
 *
 * Fires bus events:
 *   user:chat-submitted  — { message, channel }
 *
 * LineRecord shape:
 *   { lineId?, speaker?, message, type, channel?, created? }
 */

/** @private Max messages to keep per channel before dropping oldest. */
const MAX_MESSAGES_PER_CHANNEL = 200;

/** @private Channels rendered as tabs (ordered). */
const CHANNELS = [
  { key: 'room',       label: 'Room' },
  { key: 'ooc',        label: 'OOC' },
  { key: 'gm',         label: 'GM' },
  { key: 'combat-log', label: 'Combat' },
];

export class ChatPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];

    /** @type {Map<string, Array<object>>} channel → LineRecord[] */
    this._messages = new Map(CHANNELS.map((c) => [c.key, []]));

    /** @type {string} Currently active channel key */
    this._activeChannel = 'room';

    /** @type {Map<string, string>} requestId → summary */
    this._pendingRequests = new Map();

    /** @type {object} References to bound DOM elements */
    this._el = {};
  }

  init() {
    this._bindElements();
    this._renderChannelTabs();
    this._bindInputEvents();
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._messages.clear();
    this._pendingRequests.clear();
  }

  // ---------------------------------------------------------------------------
  // DOM binding
  // ---------------------------------------------------------------------------

  _bindElements() {
    const s = (key) => this.container.querySelector(`[data-chat="${key}"]`);
    this._el = {
      log:         s('log'),
      input:       s('input'),
      send:        s('send'),
      turnStatus:  s('turn-status'),
      channelTabs: s('channel-tabs'),
      sessionTabs: s('session-tabs'),
    };
  }

  // ---------------------------------------------------------------------------
  // Channel tabs
  // ---------------------------------------------------------------------------

  _renderChannelTabs() {
    const tabs = this._el.channelTabs;
    if (!tabs) return;
    tabs.innerHTML = CHANNELS.map((c) => {
      const active = c.key === this._activeChannel ? ' chat-channel-tab--active' : '';
      return `<button class="chat-channel-tab${active}" data-channel="${_esc(c.key)}">${_esc(c.label)}</button>`;
    }).join('');
    tabs.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-channel]');
      if (btn) this._switchChannel(btn.dataset.channel);
    });
  }

  _switchChannel(key) {
    if (!this._messages.has(key)) return;
    this._activeChannel = key;
    // Update tab active state
    const tabs = this._el.channelTabs;
    if (tabs) {
      tabs.querySelectorAll('[data-channel]').forEach((btn) => {
        btn.classList.toggle('chat-channel-tab--active', btn.dataset.channel === key);
      });
    }
    this._renderLog();
  }

  // ---------------------------------------------------------------------------
  // Input events
  // ---------------------------------------------------------------------------

  _bindInputEvents() {
    const { input, send } = this._el;
    if (send) {
      send.addEventListener('click', () => this._submitMessage());
    }
    if (input) {
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this._submitMessage();
        }
      });
    }
  }

  _submitMessage() {
    const { input } = this._el;
    const message = (input?.value ?? '').trim();
    if (!message) return;
    this.bus.emit('user:chat-submitted', { message, channel: this._activeChannel });
    if (input) input.value = '';
  }

  // ---------------------------------------------------------------------------
  // Bus subscriptions
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init',                (data) => this._onGameInit(data)),
      this.bus.on('chat:message-received',    (data) => this._onMessageReceived(data)),
      this.bus.on('chat:history-loaded',      (data) => this._onHistoryLoaded(data)),
      this.bus.on('chat:turn-status-changed', (data) => this._onTurnStatusChanged(data)),
      this.bus.on('chat:pending-request',     (data) => this._onPendingRequest(data)),
      this.bus.on('chat:request-settled',     (data) => this._onRequestSettled(data)),
      this.bus.on('room:changed',             ()     => this._clearRoomMessages()),
    );
  }

  // ---------------------------------------------------------------------------
  // Event handlers
  // ---------------------------------------------------------------------------

  _onGameInit({ launchContext } = {}) {
    const campaignId = launchContext?.campaign_id ?? '';
    const msg = campaignId ? `Campaign ${campaignId} loaded. Ready.` : 'Session loaded. Ready.';
    this._appendLine({ speaker: 'System', message: msg, type: 'system' });
  }

  _onMessageReceived({ line, channel } = {}) {
    const ch = channel ?? line?.channel ?? 'room';
    if (!this._messages.has(ch)) this._messages.set(ch, []);
    const bucket = this._messages.get(ch);
    bucket.push(line);
    if (bucket.length > MAX_MESSAGES_PER_CHANNEL) {
      bucket.splice(0, bucket.length - MAX_MESSAGES_PER_CHANNEL);
    }
    if (ch === this._activeChannel) {
      this._appendLine(line);
      this._scrollToBottom();
    }
    this._badgeChannel(ch);
  }

  _onHistoryLoaded({ lines = [], channel } = {}) {
    const ch = channel ?? 'room';
    if (!this._messages.has(ch)) this._messages.set(ch, []);
    this._messages.set(ch, lines.slice(-MAX_MESSAGES_PER_CHANNEL));
    if (ch === this._activeChannel) {
      this._renderLog();
    }
  }

  _onTurnStatusChanged({ status, pending } = {}) {
    const el = this._el.turnStatus;
    if (!el) return;
    el.hidden  = !pending;
    el.dataset.status   = status ?? '';
    el.textContent = pending ? (status ?? 'pending') : '';
  }

  _onPendingRequest({ requestId, summary } = {}) {
    if (!requestId) return;
    this._pendingRequests.set(requestId, summary ?? '');
    this._appendSystemLine(`⏳ ${_esc(summary ?? requestId)}`);
  }

  _onRequestSettled({ requestId, result } = {}) {
    if (!requestId) return;
    this._pendingRequests.delete(requestId);
    const label = result === 'success' ? '✅' : result === 'error' ? '❌' : 'ℹ️';
    this._appendSystemLine(`${label} Request settled`);
  }

  _clearRoomMessages() {
    this._messages.set('room', []);
    if (this._activeChannel === 'room') this._renderLog();
  }

  // ---------------------------------------------------------------------------
  // Rendering
  // ---------------------------------------------------------------------------

  _renderLog() {
    const log = this._el.log;
    if (!log) return;
    log.innerHTML = '';
    const lines = this._messages.get(this._activeChannel) ?? [];
    lines.forEach((line) => this._appendLineToEl(log, line));
    this._scrollToBottom();
  }

  /** Append a single line to the active-channel log (does not scroll). */
  _appendLine(line) {
    const log = this._el.log;
    if (!log) return;
    this._appendLineToEl(log, line);
  }

  _appendLineToEl(logEl, line) {
    const type    = line.type ?? 'say';
    const speaker = line.speaker ?? '';
    const message = line.message ?? '';
    const div = document.createElement('div');
    div.className = `chat-line chat-line--${_esc(type)}`;
    if (line.lineId) div.dataset.lineId = line.lineId;
    div.innerHTML = speaker
      ? `<span class="chat-line__speaker">${_esc(speaker)}</span><span class="chat-line__message">${_esc(message)}</span>`
      : `<span class="chat-line__message">${_esc(message)}</span>`;
    logEl.appendChild(div);
  }

  _appendSystemLine(html) {
    const log = this._el.log;
    if (!log) return;
    const div = document.createElement('div');
    div.className = 'chat-line chat-line--system';
    div.innerHTML = `<span class="chat-line__message">${html}</span>`;
    log.appendChild(div);
    this._scrollToBottom();
  }

  _scrollToBottom() {
    const log = this._el.log;
    if (log) log.scrollTop = log.scrollHeight;
  }

  _badgeChannel(ch) {
    const tabs = this._el.channelTabs;
    if (!tabs || ch === this._activeChannel) return;
    const btn = tabs.querySelector(`[data-channel="${CSS.escape(ch)}"]`);
    if (btn) btn.classList.add('chat-channel-tab--has-new');
  }
}

// ---------------------------------------------------------------------------
// Utility
// ---------------------------------------------------------------------------

/** HTML-escape a string for safe injection into innerHTML. */
function _esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
