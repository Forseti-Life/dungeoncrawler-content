/**
 * @file
 * Contract test: the chat tab mirrors the map tab's backend-wait notification.
 *
 * The map tab renders a "Hydrating encounter state..." banner that escalates to
 * "... Still waiting; the backend may be busy." once the request exceeds the slow
 * threshold. The chat tab must show the identical notification so a user reading
 * the transcript is not left staring at a silent, frozen log.
 *
 * Run with:
 *   node tests/chat_backend_wait_banner_contract_test.js
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

// ---------------------------------------------------------------------------
// Minimal DOM stub (no jsdom in this repo).
// ---------------------------------------------------------------------------

class StubElement {
  constructor(tagName = 'div') {
    this.tagName = String(tagName).toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.dataset = {};
    this.style = {};
    this.attributes = {};
    this.hidden = false;
    this._className = '';
    this._textContent = '';
    this._innerHTML = '';
    this.classList = {
      _set: new Set(),
      add: (c) => this.classList._set.add(c),
      remove: (c) => this.classList._set.delete(c),
      contains: (c) => this.classList._set.has(c),
      toggle: (c, force) => {
        const on = force === undefined ? !this.classList._set.has(c) : Boolean(force);
        if (on) this.classList._set.add(c); else this.classList._set.delete(c);
        return on;
      },
    };
  }

  get isConnected() {
    let node = this;
    while (node.parentNode) node = node.parentNode;
    return node === global.document.documentElement;
  }

  get className() { return this._className; }

  set className(value) {
    this._className = String(value || '');
    this.classList._set = new Set(this._className.split(/\s+/).filter(Boolean));
  }

  get textContent() { return this._textContent; }

  set textContent(value) { this._textContent = String(value ?? ''); }

  get innerHTML() { return this._innerHTML; }

  set innerHTML(value) {
    this._innerHTML = String(value ?? '');
    // Materialize the single [data-backend-wait-label] span the panel relies on.
    this.children = [];
    if (this._innerHTML.includes('data-backend-wait-label')) {
      const spinner = new StubElement('span');
      spinner.className = 'backend-wait-banner__spinner';
      this.appendChild(spinner);
      const label = new StubElement('span');
      label.dataset.backendWaitLabel = '';
      label.attributes['data-backend-wait-label'] = '';
      this.appendChild(label);
    }
  }

  setAttribute(name, value) { this.attributes[name] = String(value); }

  getAttribute(name) { return this.attributes[name] ?? null; }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  prepend(child) {
    child.parentNode = this;
    this.children.unshift(child);
    return child;
  }

  insertBefore(child, ref) {
    child.parentNode = this;
    const index = this.children.indexOf(ref);
    if (index < 0) this.children.push(child);
    else this.children.splice(index, 0, child);
    return child;
  }

  contains(node) {
    if (node === this) return true;
    return this.children.some((child) => child.contains(node));
  }

  _descendants() {
    return this.children.flatMap((child) => [child, ...child._descendants()]);
  }

  _matches(selector) {
    const sel = String(selector).trim();
    let m = sel.match(/^\[data-status="([^"]+)"\]$/);
    if (m) return this.dataset.status === m[1];
    m = sel.match(/^\.([A-Za-z0-9_-]+)$/);
    if (m) return this.classList.contains(m[1]);
    m = sel.match(/^#([A-Za-z0-9_-]+)$/);
    if (m) return this.attributes.id === m[1];
    m = sel.match(/^\[([A-Za-z0-9-]+)\]$/);
    if (m) return Object.prototype.hasOwnProperty.call(this.attributes, m[1]);
    return false;
  }

  querySelector(selector) {
    // Support only the simple/descendant selectors the panel actually uses.
    const parts = String(selector).trim().split(/\s+(?![^[]*\])/);
    const last = parts[parts.length - 1];
    return this._descendants().find((node) => {
      if (!node._matches(last)) return false;
      if (parts.length === 1) return true;
      let ancestor = node.parentNode;
      while (ancestor) {
        if (ancestor._matches(parts[0])) return true;
        ancestor = ancestor.parentNode;
      }
      return false;
    }) || null;
  }
}

function buildDom({ includeChatBanner }) {
  const documentElement = new StubElement('html');

  // Map tab: initiative tracker + status host + backend-wait banner.
  const tracker = new StubElement('div');
  tracker.attributes.id = 'map-initiative-tracker';
  tracker.className = 'map-initiative-tracker';
  const statusHost = new StubElement('div');
  statusHost.className = 'map-initiative-status';
  const mapBanner = new StubElement('div');
  mapBanner.className = 'backend-wait-banner';
  mapBanner.dataset.status = 'backend-wait';
  mapBanner.hidden = true;
  mapBanner.innerHTML = '<span class="backend-wait-banner__spinner" aria-hidden="true"></span><span data-backend-wait-label>Waiting for backend response...</span>';
  statusHost.appendChild(mapBanner);
  tracker.appendChild(statusHost);
  const list = new StubElement('div');
  list.className = 'initiative-list';
  tracker.appendChild(list);

  // Chat tab: chat body + log (banner presence is parameterized).
  const chatBody = new StubElement('div');
  chatBody.attributes.id = 'hexmap-chat-body';
  let chatBanner = null;
  if (includeChatBanner) {
    const chatStatusHost = new StubElement('div');
    chatStatusHost.className = 'chat-panel-status';
    chatBanner = new StubElement('div');
    chatBanner.className = 'backend-wait-banner';
    chatBanner.dataset.status = 'chat-backend-wait';
    chatBanner.hidden = true;
    chatBanner.innerHTML = '<span class="backend-wait-banner__spinner" aria-hidden="true"></span><span data-backend-wait-label>Waiting for backend response...</span>';
    chatStatusHost.appendChild(chatBanner);
    chatBody.appendChild(chatStatusHost);
  }
  const chatLog = new StubElement('div');
  chatLog.attributes.id = 'chat-log';
  chatBody.appendChild(chatLog);

  const container = new StubElement('div');
  container.className = 'hexmap-container';
  container.appendChild(tracker);
  container.appendChild(chatBody);
  documentElement.appendChild(container);

  const byId = { 'map-initiative-tracker': tracker, 'hexmap-chat-body': chatBody, 'chat-log': chatLog };

  global.HTMLElement = StubElement;
  global.document = {
    documentElement,
    createElement: (tag) => new StubElement(tag),
    getElementById: (id) => byId[id] || documentElement.querySelector(`#${id}`) || null,
    querySelector: (sel) => documentElement.querySelector(sel),
  };
  const timers = new Map();
  let nextTimer = 1;
  global.window = {
    setTimeout: (fn, ms) => { const id = nextTimer++; timers.set(id, { fn, ms }); return id; },
    clearTimeout: (id) => { timers.delete(id); },
  };

  return { container, mapBanner, chatBanner, chatBody };
}

function makeBus() {
  const handlers = new Map();
  return {
    handlers,
    on(event, fn) {
      if (!handlers.has(event)) handlers.set(event, []);
      handlers.get(event).push(fn);
      return () => {};
    },
    emit(event, data) {
      (handlers.get(event) || []).forEach((fn) => fn(data));
    },
  };
}

function labelTextOf(banner) {
  const label = banner.querySelector('[data-backend-wait-label]');
  return label ? label.textContent : banner.textContent;
}

(async () => {
  console.log('\n=== Chat tab backend-wait notification contracts ===');

  const templateSource = fs.readFileSync(path.resolve(__dirname, '../templates/hexmap-v2.html.twig'), 'utf8');
  const statusPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/StatusPanel.js'), 'utf8');
  const cssSource = fs.readFileSync(path.resolve(__dirname, '../css/hexmap.css'), 'utf8');

  assert(
    templateSource.includes('class="chat-panel-status"')
      && templateSource.includes('data-status="chat-backend-wait"')
      && templateSource.includes('<span data-backend-wait-label>{{ \'Waiting for backend response...\'|t }}</span>'),
    'chat tab template renders a backend-wait banner host mirroring the map tab'
  );

  assert(
    cssSource.includes('.chat-panel-status .backend-wait-banner {')
      && cssSource.includes('.chat-panel-status:empty {'),
    'chat backend-wait banner is docked inline via chat-panel-status styling'
  );

  assert(
    statusPanelSource.includes('_collectBackendWaitElements()')
      && statusPanelSource.includes('_resolveChatBackendWaitElement()')
      && statusPanelSource.includes('_ensureChatBackendWaitElement()')
      && statusPanelSource.includes('_ensureChatStatusHost()'),
    'StatusPanel resolves and renders both map and chat backend-wait banners from one state machine'
  );

  const { StatusPanel } = await import('../js/v2/panels/StatusPanel.js');

  // --- Behavior: banner present in template ---------------------------------
  {
    const dom = buildDom({ includeChatBanner: true });
    const bus = makeBus();
    const panel = new StatusPanel(dom.container, bus);
    panel.init();

    assert(
      dom.chatBanner.hidden === true && dom.mapBanner.hidden === true,
      'both banners start hidden when no backend request is in flight'
    );

    bus.emit('game:backend-request-start', { requestId: 'req-1', label: 'Hydrating encounter state...' });

    assert(
      dom.chatBanner.hidden === false && labelTextOf(dom.chatBanner) === 'Hydrating encounter state...',
      'chat banner shows the same "Hydrating encounter state..." label as the map banner'
    );
    assert(
      labelTextOf(dom.mapBanner) === labelTextOf(dom.chatBanner),
      'map and chat banners render identical text while waiting'
    );

    // Force the slow-backend escalation.
    panel._backendRequests.get('req-1').startedAt = Date.now() - (panel._backendWaitThresholdMs + 1000);
    panel._renderBackendWait();

    assert(
      labelTextOf(dom.chatBanner) === 'Hydrating encounter state... Still waiting; the backend may be busy.',
      'chat banner escalates to the slow-backend notification after the threshold'
    );
    assert(
      dom.chatBanner.classList.contains('backend-wait-banner--slow')
        && dom.mapBanner.classList.contains('backend-wait-banner--slow'),
      'both banners flag the slow-backend state'
    );

    bus.emit('game:backend-request-end', { requestId: 'req-1' });

    assert(
      dom.chatBanner.hidden === true && dom.mapBanner.hidden === true,
      'both banners hide once the backend request completes'
    );
    assert(
      !dom.chatBanner.classList.contains('backend-wait-banner--slow'),
      'chat banner clears the slow-backend flag on completion'
    );
  }

  // --- Behavior: template markup missing (graceful degradation) -------------
  {
    const dom = buildDom({ includeChatBanner: false });
    const bus = makeBus();
    const panel = new StatusPanel(dom.container, bus);
    panel.init();

    bus.emit('game:backend-request-start', { requestId: 'req-2', label: 'Hydrating encounter state...' });
    const created = dom.chatBody.querySelector('[data-status="chat-backend-wait"]');

    assert(
      created && created.hidden === false && labelTextOf(created) === 'Hydrating encounter state...',
      'StatusPanel creates the chat banner when template markup is absent'
    );
  }

  console.log('\n===============================================');
  console.log(`Passed: ${passed}`);
  console.log(`Failed: ${failed}`);
  if (failed > 0) {
    console.error('SOME TESTS FAILED');
    process.exit(1);
  }
  console.log('ALL TESTS PASSED');
})();
