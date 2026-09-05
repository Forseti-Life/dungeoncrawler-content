/**
 * @file
 * Editor suite hub shell.
 *
 * Renders the hub from one summary call and hosts the `editor_suite` GM
 * surface. The summary is authoritative for every count and every link: a
 * failed fetch shows the failure, never zeros. The assistant here owns no
 * draft, so there is no plan/apply path — only read and routing tools.
 */

function clearElement(el) {
  while (el && el.firstChild) {
    el.removeChild(el.firstChild);
  }
}

function makeEl(tag, className, text) {
  const el = document.createElement(tag);
  if (className) {
    el.className = className;
  }
  if (text !== undefined && text !== null) {
    el.textContent = text;
  }
  return el;
}

function formatTime(seconds) {
  if (!Number.isInteger(seconds)) {
    return '';
  }
  return new Date(seconds * 1000).toLocaleString();
}

export class EditorSuiteShell {
  constructor(root, settings) {
    this.root = root;
    this.settings = settings || {};
    this.urls = this.settings.urls || {};
    this.csrfToken = this.settings.csrfToken || '';
    this.summary = null;
    this._gm = { context: null, manifest: null };
    this._busy = false;
    this._dom = {};
    this._destroyed = false;
    this._handlers = [];
  }

  init() {
    if (!this.urls.summary) {
      throw new Error('editor_suite_summary_url_missing');
    }
    if (!this.urls.gm) {
      throw new Error('editor_suite_gm_url_missing');
    }
    this._cacheDom();
    this._bindEvents();
    return this.refresh();
  }

  destroy() {
    this._destroyed = true;
    this._handlers.forEach(([el, type, fn]) => el.removeEventListener(type, fn));
    this._handlers = [];
  }

  async refresh() {
    this._setStatus('Loading suite…');
    try {
      const [summary, gm] = await Promise.all([
        this._getJson(this.urls.summary),
        this._getJson(`${this.urls.gm}?profile=editing`),
      ]);
      this.summary = summary.data;
      this._gm.context = gm.data?.context_snapshot || null;
      this._gm.manifest = this._gm.context?.tools || null;
    } catch (err) {
      this._setStatus(`Suite unavailable: ${err.message}${err.code ? ` (${err.code})` : ''}`, 'error');
      this._setGmState('Assistant unavailable.', 'error');
      return;
    }
    this._render();
    this._renderGmContext();
    this._renderGmToolset();
    this._setGmState(`Grounded on suite state from ${this.summary.generated_at}.`, 'success');
    const attention = this.summary.attention.length;
    this._setStatus(attention ? `${attention} item(s) need attention.` : 'Nothing needs attention.', attention ? 'warning' : 'success');
  }

  // ---------------------------------------------------------------------------
  // Console rendering
  // ---------------------------------------------------------------------------

  _render() {
    this._renderRecent();
    this._renderTiles();
    this._renderAttention();
  }

  _renderRecent() {
    const list = this._dom.recent;
    clearElement(list);
    const recent = this.summary.recent || [];
    if (!recent.length) {
      list.appendChild(makeEl('li', 'editor-suite__empty', 'No active drafts. Open an editor to start one.'));
      return;
    }
    recent.forEach((entry) => {
      const item = makeEl('li', 'editor-suite__recent-item');
      item.setAttribute('data-kind', entry.kind);
      const label = makeEl('div', 'editor-suite__recent-label');
      label.appendChild(makeEl('strong', null, entry.label));
      const bits = [entry.kind, entry.state, `rev ${entry.revision}`];
      if (Number.isInteger(entry.placement_count)) {
        bits.push(`${entry.placement_count} placement(s)`);
      }
      bits.push(formatTime(entry.updated_at));
      label.appendChild(makeEl('span', 'editor-suite__meta', bits.filter(Boolean).join(' · ')));
      item.appendChild(label);
      const link = makeEl('a', 'room-editor__button room-editor__button--primary', 'Resume');
      link.href = entry.route;
      item.appendChild(link);
      list.appendChild(item);
    });
  }

  _renderTiles() {
    const wrap = this._dom.tiles;
    clearElement(wrap);
    const surfaces = this.summary.surfaces || [];
    if (!surfaces.length) {
      wrap.appendChild(makeEl('p', 'editor-suite__empty', 'You do not hold permission for any editor.'));
      return;
    }
    surfaces.forEach((surface) => {
      const tile = makeEl('article', 'editor-suite__tile');
      tile.setAttribute('data-surface', surface.id);
      tile.appendChild(makeEl('h3', 'editor-suite__tile-title', surface.label));
      const counts = makeEl('ul', 'editor-suite__tile-counts');
      const rows = [];
      if (Number.isInteger(surface.published_count)) {
        rows.push(`${surface.published_count} published of ${surface.total_count}`);
      }
      if (Number.isInteger(surface.draft_count)) {
        rows.push(`${surface.draft_count} active draft(s)`);
      }
      if (Number.isInteger(surface.family_count)) {
        rows.push(`${surface.family_count} families`);
        rows.push(`${surface.definition_count} definitions`);
      }
      rows.push(surface.attention_count ? `${surface.attention_count} need attention` : 'nothing needs attention');
      rows.forEach((row) => counts.appendChild(makeEl('li', null, row)));
      tile.appendChild(counts);
      const actions = makeEl('div', 'editor-suite__tile-actions');
      const open = makeEl('a', 'room-editor__button room-editor__button--primary', 'Open');
      open.href = surface.route;
      actions.appendChild(open);
      tile.appendChild(actions);
      wrap.appendChild(tile);
    });
  }

  _renderAttention() {
    const list = this._dom.attention;
    clearElement(list);
    const attention = this.summary.attention || [];
    if (!attention.length) {
      list.appendChild(makeEl('li', 'editor-suite__empty', 'Nothing needs attention.'));
      return;
    }
    attention.forEach((row) => {
      const item = makeEl('li', 'editor-suite__attention-item');
      item.setAttribute('data-severity', row.severity);
      item.setAttribute('data-code', row.code);
      const message = makeEl('div', 'editor-suite__attention-message');
      message.appendChild(makeEl('strong', null, `${row.count} × ${row.code}`));
      message.appendChild(makeEl('span', 'editor-suite__meta', row.message));
      item.appendChild(message);
      if (row.action_route) {
        const link = makeEl('a', 'room-editor__button', row.action_label || 'Open');
        link.href = row.action_route;
        item.appendChild(link);
      }
      list.appendChild(item);
    });
  }

  // ---------------------------------------------------------------------------
  // GM assistant (editor_suite surface: read and routing tools only)
  // ---------------------------------------------------------------------------

  _renderGmContext() {
    const body = this._dom.gmContext;
    clearElement(body);
    const context = this._gm.context;
    if (!context) {
      body.appendChild(makeEl('p', 'room-editor__hint', 'No grounded context.'));
      return;
    }
    const list = makeEl('dl', 'room-editor__gm-context-list');
    const rows = [
      ['Surface', context.tool_id],
      ['Surfaces', (context.suite?.surfaces || []).map((s) => `${s.label} (${s.attention_count})`).join(', ')],
      ['Recent drafts', String((context.suite?.recent || []).length)],
      ['Attention', (context.suite?.attention || []).map((a) => `${a.code}×${a.count}`).join(', ') || 'none'],
      ['Natural language', context.assistant?.natural_language_available ? 'available' : 'unavailable'],
      ['May mutate', 'never (no draft, no revision token)'],
    ];
    rows.forEach(([term, value]) => {
      list.appendChild(makeEl('dt', null, term));
      list.appendChild(makeEl('dd', null, value));
    });
    body.appendChild(list);
  }

  _renderGmToolset() {
    const body = this._dom.gmTools;
    clearElement(body);
    const manifest = this._gm.manifest;
    if (!manifest) {
      body.appendChild(makeEl('p', 'room-editor__hint', 'Toolset unavailable.'));
      return;
    }
    Object.keys(manifest.families || {}).forEach((family) => {
      body.appendChild(makeEl('p', 'room-editor__gm-tool-family', family));
      const list = makeEl('ul', 'room-editor__gm-tool-list');
      (manifest.families[family] || []).forEach((tool) => {
        const item = makeEl('li');
        const button = makeEl('button', 'room-editor__gm-tool', tool.name);
        button.type = 'button';
        button.title = `${tool.summary}\n${tool.authority}`;
        button.setAttribute('data-gm-tool', tool.name);
        const template = {};
        (tool.arguments || []).filter((arg) => arg.required).forEach((arg) => {
          template[arg.name] = arg.type === 'integer' ? 0 : (arg.type === 'boolean' ? false : '');
        });
        button.setAttribute('data-gm-template', JSON.stringify(template));
        item.appendChild(button);
        list.appendChild(item);
      });
      body.appendChild(list);
    });
  }

  _parseGmMessage(raw) {
    const text = String(raw || '').trim();
    if (!text) {
      throw new Error('Enter a request, or a tool name to run directly.');
    }
    const match = text.match(/^([a-z][a-z0-9_]*)\s*([\s\S]*)$/);
    if (!match || !this._isKnownGmTool(match[1])) {
      return { kind: 'natural_language', utterance: text };
    }
    const rest = match[2].trim();
    let args = {};
    if (rest) {
      let parsed = null;
      try {
        parsed = JSON.parse(rest);
      } catch (_err) {
        throw new Error('Tool arguments must be a JSON object.');
      }
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error('Tool arguments must be a JSON object.');
      }
      args = parsed;
    }
    return { kind: 'tool_call', toolName: match[1], args };
  }

  _isKnownGmTool(name) {
    return !!this._gm.manifest
      && Object.values(this._gm.manifest.families || {}).some((tools) => tools.some((t) => t.name === name));
  }

  async _submitGmMessage() {
    const raw = this._dom.gmInput.value || '';
    let parsed = null;
    try {
      parsed = this._parseGmMessage(raw);
    } catch (err) {
      this._appendGmMessage('error', err.message);
      return;
    }
    if (parsed.kind === 'natural_language' && !this._gm.context?.assistant?.natural_language_available) {
      this._appendGmMessage('error', 'Natural-language requests are unavailable. Run a tool directly, e.g. "list_attention_items".');
      return;
    }
    this._appendGmMessage('user', raw.trim());
    this._dom.gmInput.value = '';
    const intent = parsed.kind === 'natural_language'
      ? { type: 'natural_language', utterance: parsed.utterance }
      : { type: 'tool_call', tool_name: parsed.toolName, arguments: parsed.args };
    await this._sendGmRequest(intent, parsed.kind === 'natural_language' ? 'assistant' : parsed.toolName);
  }

  async _sendGmRequest(intent, label) {
    if (this._busy) {
      this._appendGmMessage('error', 'Another request is still running.');
      return null;
    }
    const body = {
      schema_version: 'editor-gm-request-v1',
      tool_context: { tool_id: 'editor_suite', validation_profile: 'editing' },
      intent,
      options: { dry_run: false },
    };
    this._busy = true;
    this._setGmState(`Running ${label}...`);
    let envelope = null;
    try {
      const result = await this._postJson(this.urls.gm, body);
      envelope = result?.data || {};
    } catch (err) {
      this._busy = false;
      this._appendGmMessage('error', `${label} failed: ${err.message}${err.code ? ` (${err.code})` : ''}`);
      this._setGmState('Last request failed.', 'error');
      return null;
    }
    this._busy = false;
    this._gm.context = envelope.context_snapshot || this._gm.context;
    this._gm.manifest = this._gm.context?.tools || this._gm.manifest;
    this._renderGmContext();
    this._renderGmToolset();
    this._appendGmMessage('success', `${label} → ${envelope.route_family}`, envelope.tool_result);
    if (typeof envelope.tool_result?.route === 'string') {
      const item = this._dom.gmTranscript.lastElementChild;
      const link = makeEl('a', 'room-editor__button', 'Go there');
      link.href = envelope.tool_result.route;
      item.appendChild(link);
    }
    (envelope.messages || []).forEach((message) => {
      this._appendGmMessage(message.level === 'error' ? 'error' : 'info', message.text);
    });
    this._setGmState(`Grounded on suite state from ${envelope.context_snapshot?.suite?.generated_at || 'now'}.`, 'success');
    return envelope;
  }

  _appendGmMessage(kind, text, detail) {
    const item = makeEl('li', `room-editor__gm-message room-editor__gm-message--${kind}`, text);
    if (detail !== undefined && detail !== null) {
      item.appendChild(makeEl('pre', null, typeof detail === 'string' ? detail : JSON.stringify(detail, null, 2)));
    }
    this._dom.gmTranscript.appendChild(item);
    this._dom.gmTranscript.scrollTop = this._dom.gmTranscript.scrollHeight;
  }

  _setGmState(text, level = 'info') {
    this._dom.gmState.textContent = text;
    this._dom.gmState.setAttribute('data-status-level', level);
  }

  // ---------------------------------------------------------------------------
  // DOM / events / transport
  // ---------------------------------------------------------------------------

  _cacheDom() {
    const q = (sel) => this.root.querySelector(sel);
    this._dom = {
      status: q('[data-editor-suite-status]'),
      recent: q('[data-editor-suite-recent]'),
      tiles: q('[data-editor-suite-tiles]'),
      attention: q('[data-editor-suite-attention]'),
      gmState: q('[data-editor-suite-gm-state]'),
      gmContext: q('[data-editor-suite-gm-context]'),
      gmTools: q('[data-editor-suite-gm-tools]'),
      gmTranscript: q('[data-editor-suite-gm-transcript]'),
      gmForm: q('[data-editor-suite-gm-form]'),
      gmInput: q('[data-editor-suite-gm-input]'),
    };
    Object.entries(this._dom).forEach(([key, el]) => {
      if (!el) {
        throw new Error(`editor_suite_dom_missing:${key}`);
      }
    });
  }

  _on(el, type, fn) {
    el.addEventListener(type, fn);
    this._handlers.push([el, type, fn]);
  }

  _bindEvents() {
    this._on(this._dom.gmForm, 'submit', (event) => {
      event.preventDefault();
      this._submitGmMessage();
    });
    this._on(this._dom.gmInput, 'keydown', (event) => {
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        this._submitGmMessage();
      }
    });
    this._on(this.root, 'click', (event) => {
      const tool = event.target.closest('[data-gm-tool]');
      if (tool) {
        this._dom.gmInput.value = `${tool.getAttribute('data-gm-tool')} ${tool.getAttribute('data-gm-template') || '{}'}`;
        this._dom.gmInput.focus();
        return;
      }
      const action = event.target.closest('[data-editor-suite-action]');
      if (!action) {
        return;
      }
      switch (action.getAttribute('data-editor-suite-action')) {
        case 'gm-toggle-context':
          this._toggleDisclosure(action, this._dom.gmContext);
          break;
        case 'gm-toggle-tools':
          this._toggleDisclosure(action, this._dom.gmTools);
          break;
        default:
          break;
      }
    });
  }

  _toggleDisclosure(button, body) {
    const open = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', open ? 'false' : 'true');
    body.hidden = open;
  }

  _setStatus(text, level = 'info') {
    this._dom.status.textContent = text;
    this._dom.status.setAttribute('data-status-level', level);
  }

  async _getJson(url) {
    const res = await fetch(url, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    return this._parseResponse(res);
  }

  async _postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': this.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    });
    return this._parseResponse(res);
  }

  async _parseResponse(res) {
    let json = null;
    try {
      json = await res.json();
    } catch (_err) {
      json = null;
    }
    if (!res.ok) {
      const err = new Error(json?.error?.message || `Request failed with status ${res.status}.`);
      err.code = json?.error?.code || `http_${res.status}`;
      err.status = res.status;
      throw err;
    }
    return json || {};
  }
}
