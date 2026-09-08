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
    this._waitingOnGenerator = false;
    this._gm = { context: null, manifest: null, proposal: null };
  }

  async init() {
    this._cacheDom();
    this._bindEvents();
    this._renderGenerationForm();
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

  async _sendGmRequest(intent, dryRun, label, scopeOverride = null) {
    if (this._busy) {
      this._appendGmMessage('error', 'Another request is still running.');
      return null;
    }
    const body = {
      schema_version: 'editor-gm-request-v1',
      tool_context: { tool_id: 'definition_editor', validation_profile: 'editing', scope: scopeOverride || this.scope },
      intent,
      options: { dry_run: !!dryRun },
    };
    this._busy = true;
    this._setGmState(`Running ${label}...`);
    this._renderBusyState();
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
      this._waitingOnGenerator = false;
      this._renderBusyState();
    }
  }

  _renderProposal(result) {
    const proposal = result?.proposed_execution;
    if (!proposal) return;
    this._gm.proposal = proposal;
    clearElement(this._dom.gmPlanList);
    const generatedBy = proposal.arguments?.payload?.metadata?.generated_by || result.metadata?.generated_by || null;
    if (result.generation_type || result.proposal_summary) {
      const summary = result.proposal_summary || {};
      this._dom.gmPlanList.appendChild(makeEl('li', null, `${result.generation_type || 'definition'}: ${summary.definition_id || '(new definition)'} ${summary.name || ''}`.trim()));
    }
    if (generatedBy) {
      this._dom.gmPlanList.appendChild(makeEl(
        'li',
        null,
        `Generated by ${generatedBy.tool || '?'} using ${generatedBy.model || 'unknown model'}; seed ${generatedBy.seed ?? '?'}; ${String(generatedBy.prompt_hash || '').slice(0, 19)}…; ${generatedBy.generated_at || ''}`.trim()
      ));
    }
    (result.field_changes || []).forEach((change) => {
      this._dom.gmPlanList.appendChild(makeEl('li', null, `${change.field}: ${JSON.stringify(change.before)} → ${JSON.stringify(change.after)}`));
    });
    if (!(result.field_changes || []).length && !result.generation_type && !result.proposal_summary) {
      this._dom.gmPlanList.appendChild(makeEl('li', null, 'No field changes.'));
    }
    this._dom.gmPlan.hidden = false;
    this._dom.gmPlan.focus?.();
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

  _renderGenerationForm() {
    const supportedFamilies = ['item', 'creature', 'actor'];
    const scopeFamily = this.scope.family || 'creature';
    const family = supportedFamilies.includes(this._dom.generateFamily.value)
      ? this._dom.generateFamily.value
      : (supportedFamilies.includes(scopeFamily) ? scopeFamily : 'item');
    this._dom.generateSection.hidden = false;
    this._dom.generateUnavailable.hidden = true;
    this._dom.generateFamily.value = family;
    this._dom.generateFamily.disabled = false;
    this._dom.generateRarityField.hidden = family === 'actor';
    this._dom.generateRoleField.hidden = family === 'item';
    this._dom.generateAttitudeField.hidden = family !== 'actor';
    this._dom.generateLevel.min = family === 'item' ? '0' : '-1';
    this._dom.generateLevel.max = family === 'actor' ? '30' : '25';
    this._renderBusyState();
  }

  async _generateDefinition() {
    const family = this._dom.generateFamily.value || this.scope.family || 'creature';
    const prompt = String(this._dom.generatePrompt.value || '').trim();
    if (!prompt) {
      this._appendGmMessage('error', 'Enter a generation prompt first.');
      return;
    }
    const args = { prompt };
    let level;
    let seed;
    try {
      level = this._numberOrNull(this._dom.generateLevel.value);
      seed = this._numberOrNull(this._dom.generateSeed.value);
    } catch (err) {
      this._appendGmMessage('error', err.message);
      return;
    }
    if (level !== null) args.level = level;
    if (seed !== null) args.seed = seed;
    if (family === 'item') {
      args.rarity = this._dom.generateRarity.value || 'common';
    } else if (family === 'creature') {
      const role = String(this._dom.generateRole.value || '').trim();
      if (role) args.role = role;
      args.rarity = ['common', 'uncommon', 'rare', 'unique'].includes(this._dom.generateRarity.value) ? this._dom.generateRarity.value : 'common';
    } else if (family === 'actor') {
      const role = String(this._dom.generateRole.value || '').trim();
      if (role) args.role = role;
      if (this._dom.generateAttitude.value) args.attitude = this._dom.generateAttitude.value;
    } else {
      this._appendGmMessage('error', 'Generation is available only for item, creature, and actor/NPC scopes.');
      return;
    }
    const tool = family === 'item' ? 'generate_item_definition' : (family === 'creature' ? 'generate_creature_definition' : 'generate_npc_definition');
    this._waitingOnGenerator = true;
    await this._sendGmRequest({ type: 'tool_call', tool_name: tool, arguments: args }, false, tool, { family });
  }

  _numberOrNull(value) {
    if (value === null || value === undefined || String(value).trim() === '') return null;
    const parsed = Number(value);
    if (!Number.isInteger(parsed)) throw new Error('Generation numeric fields must be integers.');
    return parsed;
  }

  _renderBusyState() {
    const disabled = this._busy;
    const label = disabled && this._waitingOnGenerator ? 'Waiting on generator (LLM)…' : 'Generate definition';
    this._dom.generateButton.disabled = disabled;
    this._dom.generateButton.textContent = label;
    this._dom.previewButton.disabled = disabled;
    this._dom.applyButton.disabled = disabled;
    this._dom.discardButton.disabled = disabled;
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
      generateSection: q('[data-definition-editor-generation]'),
      generateFamily: q('[data-definition-editor-generation-family]'),
      generatePrompt: q('[data-definition-editor-generation-prompt]'),
      generateLevel: q('[data-definition-editor-generation-level]'),
      generateRarity: q('[data-definition-editor-generation-rarity]'),
      generateRarityField: q('[data-definition-editor-generation-rarity-field]'),
      generateRole: q('[data-definition-editor-generation-role]'),
      generateRoleField: q('[data-definition-editor-generation-role-field]'),
      generateAttitude: q('[data-definition-editor-generation-attitude]'),
      generateAttitudeField: q('[data-definition-editor-generation-attitude-field]'),
      generateSeed: q('[data-definition-editor-generation-seed]'),
      generateButton: q('[data-definition-editor-action="generate-definition"]'),
      generateUnavailable: q('[data-definition-editor-generation-unavailable]'),
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
      if (action === 'generate-definition') this._generateDefinition();
      if (action === 'gm-preview-definition') this._previewProposal();
      if (action === 'gm-apply-definition') this._applyProposal();
      if (action === 'gm-discard-definition') {
        this._gm.proposal = null;
        this._dom.gmPlan.hidden = true;
      }
    });
    this._on(this._dom.generateFamily, 'change', () => this._renderGenerationForm());
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
