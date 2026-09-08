/**
 * GM panel controller for schema-driven canonical definition pages.
 */

function makeEl(tag, className = null, text = null) {
  const el = document.createElement(tag);
  if (className) el.className = className;
  if (text !== null && text !== undefined) el.textContent = String(text);
  return el;
}

function clearElement(el) {
  while (el?.firstChild) el.removeChild(el.firstChild);
}

export class DefinitionEditorShell {
  constructor(root, settings = {}) {
    this.root = root;
    this.settings = settings;
    this.urls = { gm: String(settings.gmUrl || '') };
    this.csrfToken = String(settings.csrfToken || '');
    this.scope = settings.scope || { family: 'creature' };
    this._handlers = [];
    this._busy = false;
    this._gm = { context: null, manifest: null, proposal: null };
  }

  async init() {
    this._cacheDom();
    this._bindEvents();
    await this._refreshGmContext();
  }

  destroy() {
    this._handlers.forEach(([el, type, fn]) => el.removeEventListener(type, fn));
    this._handlers = [];
  }

  async _refreshGmContext() {
    if (!this.urls.gm) {
      this._setGmState('GM endpoint is not configured.', 'error');
      return;
    }
    const url = new URL(this.urls.gm, window.location.origin);
    url.searchParams.set('profile', 'editing');
    url.searchParams.set('family', this.scope.family || 'creature');
    if (this.scope.definition_id) {
      url.searchParams.set('definition_id', this.scope.definition_id);
    }
    try {
      const result = await this._getJson(url.toString());
      this._gm.context = result?.data?.context_snapshot || null;
      this._gm.manifest = this._gm.context?.tools || null;
      this._renderGmContext();
      this._renderGmToolset();
      this._setGmState(`Grounded on ${this._gm.context?.scope?.family || this.scope.family}.`, 'success');
    } catch (err) {
      this._appendGmMessage('error', `Context unavailable: ${err.message}${err.code ? ` (${err.code})` : ''}`, err.findings);
      this._setGmState('Context unavailable.', 'error');
    }
  }

  _renderGmContext() {
    clearElement(this._dom.gmContext);
    const context = this._gm.context;
    if (!context) {
      this._dom.gmContext.appendChild(makeEl('p', 'room-editor__hint', 'No grounded context loaded.'));
      return;
    }
    const rows = [
      ['Surface', context.tool_id],
      ['Family', context.scope?.family],
      ['Definition', context.scope?.definition_id || '(family list)'],
      ['Version', context.definition?.version || '-'],
      ['Family total', context.family?.total],
      ['Validation', `${context.validation_summary?.warning_count || 0} warnings, ${context.validation_summary?.error_count || 0} errors`],
      ['Blast radius', `${(context.definition?.affected_published_rooms || []).length} published room version(s)`],
      ['Authority', context.authority_boundary?.mutation_gateway || '?'],
      ['May mutate from natural language', 'never'],
    ];
    const list = makeEl('dl', 'room-editor__gm-context-list');
    rows.forEach(([term, value]) => {
      list.appendChild(makeEl('dt', null, term));
      list.appendChild(makeEl('dd', null, value ?? ''));
    });
    this._dom.gmContext.appendChild(list);
  }

  _renderGmToolset() {
    clearElement(this._dom.gmTools);
    const manifest = this._gm.manifest;
    if (!manifest) {
      this._dom.gmTools.appendChild(makeEl('p', 'room-editor__hint', 'Toolset unavailable.'));
      return;
    }
    Object.keys(manifest.families || {}).forEach((family) => {
      this._dom.gmTools.appendChild(makeEl('p', 'room-editor__gm-tool-family', family));
      const list = makeEl('ul', 'room-editor__gm-tool-list');
      (manifest.families[family] || []).forEach((tool) => {
        const item = makeEl('li');
        const button = makeEl('button', `room-editor__gm-tool${tool.mutating ? ' room-editor__gm-tool--mutating' : ''}`, tool.name);
        button.type = 'button';
        button.title = `${tool.summary}\n${tool.authority}`;
        button.setAttribute('data-gm-tool', tool.name);
        const template = {};
        (tool.arguments || []).filter((arg) => arg.required).forEach((arg) => {
          template[arg.name] = arg.type === 'integer' ? 0 : (arg.type === 'array' ? [] : (arg.type === 'boolean' ? false : (arg.type === 'object' ? {} : '')));
        });
        button.setAttribute('data-gm-template', JSON.stringify(template));
        item.appendChild(button);
        list.appendChild(item);
      });
      this._dom.gmTools.appendChild(list);
    });
  }

  _parseGmMessage(raw) {
    const text = String(raw || '').trim();
    if (!text) throw new Error('Enter a request, or a tool name to run directly.');
    const match = text.match(/^([a-z][a-z0-9_]*)\s*([\s\S]*)$/);
    if (!match || !this._isKnownGmTool(match[1])) {
      return { kind: 'natural_language', utterance: text };
    }
    const rest = match[2].trim();
    let args = {};
    if (rest) {
      args = JSON.parse(rest);
      if (!args || typeof args !== 'object' || Array.isArray(args)) {
        throw new Error('Tool arguments must be a JSON object.');
      }
    }
    return { kind: 'tool_call', toolName: match[1], args };
  }

  _isKnownGmTool(name) {
    return !!this._gm.manifest
      && Object.values(this._gm.manifest.families || {}).some((tools) => tools.some((t) => t.name === name));
  }

  async _submitGmMessage() {
    let parsed;
    const raw = this._dom.gmInput.value || '';
    try {
      parsed = this._parseGmMessage(raw);
    } catch (err) {
      this._appendGmMessage('error', err.message);
      return;
    }
    if (parsed.kind === 'natural_language' && !this._gm.context?.assistant?.natural_language_available) {
      this._appendGmMessage('error', 'Natural-language requests are unavailable. Run a tool directly, e.g. "describe_definition_schema".');
      return;
    }
    this._appendGmMessage('user', raw.trim());
    this._dom.gmInput.value = '';
    const intent = parsed.kind === 'natural_language'
      ? { type: 'natural_language', utterance: parsed.utterance }
      : { type: 'tool_call', tool_name: parsed.toolName, arguments: parsed.args };
    await this._sendGmRequest(intent, false, parsed.kind === 'natural_language' ? 'assistant' : parsed.toolName);
  }

  async _sendGmRequest(intent, dryRun, label) {
    if (this._busy) {
      this._appendGmMessage('error', 'Another request is still running.');
      return null;
    }
    const body = {
      schema_version: 'editor-gm-request-v1',
      tool_context: { tool_id: 'definition_editor', validation_profile: 'editing', scope: this.scope },
      intent,
      options: { dry_run: !!dryRun },
    };
    this._busy = true;
    this._setGmState(`Running ${label}...`);
    try {
      const result = await this._postJson(this.urls.gm, body);
      const envelope = result?.data || {};
      this._gm.context = envelope.context_snapshot || this._gm.context;
      this._gm.manifest = this._gm.context?.tools || this._gm.manifest;
      this._renderGmContext();
      this._renderGmToolset();
      this._appendGmMessage('success', `${label} → ${envelope.route_family}`, envelope.tool_result);
      this._renderProposal(envelope.tool_result);
      this._renderBlastRadius(envelope.tool_result);
      (envelope.messages || []).forEach((message) => this._appendGmMessage(message.level === 'error' ? 'error' : 'info', message.text));
      this._setGmState(`Grounded on ${this._gm.context?.scope?.family || this.scope.family}.`, 'success');
      return envelope;
    } catch (err) {
      this._appendGmMessage('error', `${label} failed: ${err.message}${err.code ? ` (${err.code})` : ''}`, err.findings);
      this._setGmState('Last request failed.', 'error');
      return null;
    } finally {
      this._busy = false;
    }
  }

  _renderProposal(result) {
    const proposal = result?.proposed_execution;
    if (!proposal) return;
    this._gm.proposal = proposal;
    clearElement(this._dom.gmPlanList);
    (result.field_changes || []).forEach((change) => {
      this._dom.gmPlanList.appendChild(makeEl('li', null, `${change.field}: ${JSON.stringify(change.before)} → ${JSON.stringify(change.after)}`));
    });
    if (!(result.field_changes || []).length) {
      this._dom.gmPlanList.appendChild(makeEl('li', null, 'No field changes.'));
    }
    this._dom.gmPlan.hidden = false;
    this._renderBlastRadius(result);
  }

  async _previewProposal() {
    const proposal = this._gm.proposal;
    if (!proposal?.arguments?.payload) {
      this._appendGmMessage('error', 'There is no proposed definition update to preview.');
      return;
    }
    await this._sendGmRequest({
      type: 'tool_call',
      tool_name: 'validate_definition',
      arguments: { family: proposal.arguments.family || this.scope.family, definition_id: proposal.arguments.definition_id, payload: proposal.arguments.payload },
    }, false, 'preview_definition_update');
  }

  async _applyProposal() {
    const proposal = this._gm.proposal;
    if (!proposal?.tool_name) {
      this._appendGmMessage('error', 'There is no proposed definition update to apply.');
      return;
    }
    const envelope = await this._sendGmRequest({
      type: 'tool_call',
      tool_name: proposal.tool_name,
      arguments: proposal.arguments || {},
    }, false, proposal.tool_name);
    if (envelope) {
      this._gm.proposal = null;
      this._dom.gmPlan.hidden = true;
      await this._refreshGmContext();
    }
  }

  _renderBlastRadius(result) {
    const rooms = result?.affected_published_rooms || result?.affected_rooms || result?.blast_radius || [];
    if (!Array.isArray(rooms) || rooms.length === 0) return;
    this._appendGmMessage('info', `Blast radius: ${rooms.length} published room version(s) still pin the prior definition.`, rooms);
  }

  _appendGmMessage(kind, text, detail = null) {
    const item = makeEl('li', `room-editor__gm-message room-editor__gm-message--${kind}`, text);
    if (detail !== null && detail !== undefined) {
      item.appendChild(makeEl('pre', null, typeof detail === 'string' ? detail : JSON.stringify(detail, null, 2)));
    }
    this._dom.gmTranscript.appendChild(item);
    this._dom.gmTranscript.scrollTop = this._dom.gmTranscript.scrollHeight;
  }

  _setGmState(text, level = 'info') {
    this._dom.gmState.textContent = text;
    this._dom.gmState.setAttribute('data-status-level', level);
  }

  _cacheDom() {
    const q = (sel) => this.root.querySelector(sel);
    this._dom = {
      gmState: q('[data-definition-editor-gm-state]'),
      gmContext: q('[data-definition-editor-gm-context]'),
      gmTools: q('[data-definition-editor-gm-tools]'),
      gmTranscript: q('[data-definition-editor-gm-transcript]'),
      gmForm: q('[data-definition-editor-gm-form]'),
      gmInput: q('[data-definition-editor-gm-input]'),
      gmPlan: q('[data-definition-editor-gm-plan]'),
      gmPlanList: q('[data-definition-editor-gm-plan-list]'),
      previewButton: q('[data-definition-editor-action="gm-preview-definition"]'),
      applyButton: q('[data-definition-editor-action="gm-apply-definition"]'),
      discardButton: q('[data-definition-editor-action="gm-discard-definition"]'),
    };
    Object.entries(this._dom).forEach(([key, el]) => {
      if (!el) throw new Error(`definition_editor_dom_missing:${key}`);
    });
  }

  _bindEvents() {
    this._on(this._dom.gmForm, 'submit', (event) => {
      event.preventDefault();
      this._submitGmMessage();
    });
    this._on(this.root, 'click', (event) => {
      const tool = event.target.closest('[data-gm-tool]');
      if (tool) {
        this._dom.gmInput.value = `${tool.getAttribute('data-gm-tool')} ${tool.getAttribute('data-gm-template') || '{}'}`;
        this._dom.gmInput.focus();
        return;
      }
      const action = event.target.closest('[data-definition-editor-action]')?.getAttribute('data-definition-editor-action');
      if (action === 'gm-toggle-context') this._toggle(event.target.closest('button'), this._dom.gmContext);
      if (action === 'gm-toggle-tools') this._toggle(event.target.closest('button'), this._dom.gmTools);
      if (action === 'gm-send') this._submitGmMessage();
      if (action === 'gm-preview-definition') this._previewProposal();
      if (action === 'gm-apply-definition') this._applyProposal();
      if (action === 'gm-discard-definition') {
        this._gm.proposal = null;
        this._dom.gmPlan.hidden = true;
      }
    });
    this._on(this._dom.gmInput, 'keydown', (event) => {
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        this._submitGmMessage();
      }
    });
  }

  _toggle(button, body) {
    const open = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', open ? 'false' : 'true');
    body.hidden = open;
  }

  _on(el, type, fn) {
    el.addEventListener(type, fn);
    this._handlers.push([el, type, fn]);
  }

  async _getJson(url) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    return this._readJsonResponse(response);
  }

  async _postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfToken },
      body: JSON.stringify(body),
    });
    return this._readJsonResponse(response);
  }

  async _readJsonResponse(response) {
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.error) {
      const error = new Error(payload.error?.message || `HTTP ${response.status}`);
      error.status = response.status;
      error.code = payload.error?.code;
      error.findings = payload.error?.findings;
      throw error;
    }
    return payload;
  }
}
