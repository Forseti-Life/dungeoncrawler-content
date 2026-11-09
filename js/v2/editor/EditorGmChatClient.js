/**
 * Shared browser contract for every editor-embedded GM assistant.
 *
 * Surface shells retain only domain-specific context rendering and post-apply
 * refresh behavior. Composer keys, tool parsing/rendering, request envelopes,
 * transcript messages, and approval controls resolve here.
 */

export function shouldSubmitEditorGmKey(event) {
  return event.key === 'Enter' && !event.shiftKey && !event.isComposing;
}

export function isKnownEditorGmTool(manifest, name) {
  return !!manifest
    && Object.values(manifest.families || {})
      .some((tools) => tools.some((tool) => tool.name === name));
}

export function parseEditorGmMessage(raw, manifest) {
  const text = String(raw || '').trim();
  if (!text) {
    throw new Error('Enter a request, or a tool name to run directly.');
  }
  const match = text.match(/^([a-z][a-z0-9_]*)\s*([\s\S]*)$/);
  if (!match || !isKnownEditorGmTool(manifest, match[1])) {
    return { kind: 'natural_language', utterance: text };
  }
  const rest = match[2].trim();
  let args = {};
  if (rest) {
    try {
      args = JSON.parse(rest);
    } catch (_error) {
      throw new Error('Tool arguments must be a JSON object.');
    }
    if (!args || typeof args !== 'object' || Array.isArray(args)) {
      throw new Error('Tool arguments must be a JSON object.');
    }
  }
  return { kind: 'tool_call', toolName: match[1], args };
}

function requiredArgumentDefault(type) {
  if (type === 'integer') return 0;
  if (type === 'array') return [];
  if (type === 'boolean') return false;
  if (type === 'object') return {};
  return '';
}

export function renderEditorGmToolset(container, manifest, options = {}) {
  if (!container) return;
  container.replaceChildren();
  if (!manifest) {
    const empty = document.createElement('p');
    empty.className = options.hintClass || 'room-editor__hint';
    empty.textContent = options.emptyText || 'Toolset unavailable.';
    container.appendChild(empty);
    return;
  }
  Object.entries(manifest.families || {}).forEach(([family, tools]) => {
    const heading = document.createElement('p');
    heading.className = options.familyClass || 'room-editor__gm-tool-family';
    heading.textContent = family;
    container.appendChild(heading);

    const list = document.createElement('ul');
    list.className = options.listClass || 'room-editor__gm-tool-list';
    tools.forEach((tool) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `${options.toolClass || 'room-editor__gm-tool'}${tool.mutating ? ` ${options.mutatingClass || 'room-editor__gm-tool--mutating'}` : ''}`;
      button.textContent = tool.name;
      button.title = `${tool.summary}\n${tool.authority}`;
      button.dataset.gmTool = tool.name;
      const template = {};
      (tool.arguments || []).filter((argument) => argument.required).forEach((argument) => {
        template[argument.name] = requiredArgumentDefault(argument.type);
      });
      button.dataset.gmTemplate = JSON.stringify(template);
      item.appendChild(button);
      list.appendChild(item);
    });
    container.appendChild(list);
  });
}

export function selectEditorGmTool(event, input) {
  const button = event.target.closest('[data-gm-tool]');
  if (!button || !input) return false;
  input.value = `${button.getAttribute('data-gm-tool')} ${button.getAttribute('data-gm-template') || '{}'}`;
  input.focus();
  return true;
}

export function describeEditorGmToolCall(toolName, args = {}) {
  const name = String(toolName || '').trim();
  const family = String(args?.family || '').trim();
  const definitionId = String(args?.definition_id || '').trim();
  const roomId = String(args?.room_id || args?.room_slot_id || '').trim();
  const humanize = (suffix) => String(suffix || '').replace(/_/g, ' ').trim();
  const lookupTarget = (rawName) => {
    const label = humanize(rawName.replace(/^(get|load|list|find|inspect|describe|summarize|validate|check|explain|diff)_/, ''));
    if (family && definitionId) return `${label} (${family} '${definitionId}')`;
    if (family) return `${label} (${family})`;
    return label;
  };

  if (name === 'governing_harness_context') return 'Now looking up current editor context…';
  if (name === 'clarification') return 'Now evaluating request options…';
  if (name === 'route_to_editor') return 'Now looking up target editor route…';
  if (name === 'generate_canonical_definition') {
    if (family && definitionId) return `Now generating canonical ${family} definition '${definitionId}'…`;
    return family ? `Now generating canonical ${family} definition…` : 'Now generating canonical definition…';
  }
  if (name === 'create_canonical_definition') {
    return family && definitionId
      ? `Now creating canonical ${family} definition '${definitionId}'…`
      : 'Now creating canonical definition…';
  }
  if (name === 'update_canonical_definition') {
    return family && definitionId
      ? `Now updating canonical ${family} definition '${definitionId}'…`
      : 'Now updating canonical definition…';
  }
  if (name === 'generate_room_layout') {
    return roomId ? `Now generating room layout for '${roomId}'…` : 'Now generating room layout…';
  }
  if (name === 'generate_dungeon_layout') return 'Now generating dungeon layout…';
  if (name.startsWith('generate_')) return `Now generating ${humanize(name.slice(9))}…`;
  if (/^(get|load|list|find|inspect|describe|summarize)_/.test(name)) {
    return `Now looking up ${lookupTarget(name)}…`;
  }
  if (/^(validate|check)_/.test(name)) {
    return `Now validating ${lookupTarget(name)}…`;
  }
  if (/^(explain|diff)_/.test(name)) {
    return `Now analyzing ${lookupTarget(name)}…`;
  }
  if (name.startsWith('preview_')) return `Now previewing ${humanize(name.slice(8))}…`;
  if (name.startsWith('plan_')) return `Now planning ${humanize(name.slice(5))}…`;
  if (name.startsWith('apply_')) return `Now applying ${humanize(name.slice(6))}…`;
  if (name.startsWith('publish_')) return `Now publishing ${humanize(name.slice(8))}…`;
  return `Now running ${humanize(name || 'tool')}…`;
}

export function renderEditorGmToolCalls(transcript, toolCalls, options = {}) {
  if (!transcript || !Array.isArray(toolCalls) || toolCalls.length === 0) {
    return [];
  }
  const skipMessages = options.skipMessages instanceof Set ? options.skipMessages : new Set();
  const rendered = [];
  toolCalls.forEach((call) => {
    const message = typeof call === 'string'
      ? call.trim()
      : String(call?.message || (call?.tool ? describeEditorGmToolCall(call.tool, call.arguments || {}) : '')).trim();
    if (!message || skipMessages.has(message)) {
      return;
    }
    skipMessages.add(message);
    const item = appendEditorGmMessage(transcript, 'tool', message);
    if (item) {
      rendered.push(item);
    }
  });
  return rendered;
}

export function restoreEditorGmConversation(transcript, messages, options = {}) {
  if (!transcript || !Array.isArray(messages) || messages.length === 0) {
    return;
  }
  messages.forEach((message) => {
    const metadata = message?.metadata || {};
    if (metadata.editor_gm_role === 'author') {
      appendEditorGmMessage(transcript, 'user', String(message.message || ''));
      return;
    }
    const response = metadata.editor_gm_response;
    const result = response?.tool_result;
    if (!result || typeof result !== 'object') {
      return;
    }
    renderEditorGmToolCalls(transcript, response?.tool_calls || result?.tool_calls || []);
    if (result.intent === 'clarification') {
      renderEditorGmClarification(transcript, result, options);
      return;
    }
    appendEditorGmMessage(transcript, 'assistant', result.assistant_response || result.reasoning || 'Assistant response', result);
  });
}

export function renderEditorGmClarification(transcript, result, options = {}) {
  if (!transcript || !result || result.intent !== 'clarification') {
    return null;
  }
  const item = document.createElement('li');
  item.className = 'room-editor__gm-message room-editor__gm-message--assistant';
  const prompt = document.createElement('p');
  prompt.textContent = String(result.question || result.assistant_response || 'Choose a direction.');
  item.appendChild(prompt);
  const choices = Array.isArray(result.options) ? result.options : [];
  choices.forEach((option, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'room-editor__button';
    const label = String(option.label || `Option ${index + 1}`);
    const description = String(option.description || '');
    const tradeoff = String(option.tradeoff || '');
    button.textContent = `${index + 1}. ${label}${description ? ` — ${description}` : ''}${tradeoff ? ` (${tradeoff})` : ''}`;
    if (typeof options.onSelectOption === 'function') {
      button.addEventListener('click', () => {
        item.querySelectorAll('button').forEach((btn) => {
          btn.disabled = true;
        });
        options.onSelectOption(`I select option ${index + 1}, ${label}. Use this direction: ${option.selection || description || label}`);
      });
    }
    item.appendChild(button);
  });
  transcript.appendChild(item);
  transcript.scrollTop = transcript.scrollHeight;
  return item;
}

export function appendEditorGmMessage(transcript, kind, text, detail = undefined) {
  if (!transcript) return null;
  const item = document.createElement('li');
  item.className = `room-editor__gm-message room-editor__gm-message--${kind}`;
  item.textContent = text;
  if (detail !== undefined && detail !== null) {
    if (typeof detail === 'object' && typeof detail.assistant_response === 'string' && detail.assistant_response.trim() !== '') {
      item.textContent = detail.assistant_response.trim();
      const steps = Array.isArray(detail.next_steps)
        ? detail.next_steps.filter((step) => typeof step === 'string' && step.trim() !== '')
        : [];
      if (steps.length > 0) {
        const nextBlock = document.createElement('div');
        nextBlock.className = 'room-editor__gm-next-steps';
        const heading = document.createElement('strong');
        heading.textContent = 'Next steps';
        const list = document.createElement('ol');
        steps.forEach((step) => {
          const li = document.createElement('li');
          li.textContent = step.trim();
          list.appendChild(li);
        });
        nextBlock.append(heading, list);
        item.appendChild(nextBlock);
      }
    } else {
      const pre = document.createElement('pre');
      pre.textContent = typeof detail === 'string' ? detail : JSON.stringify(detail, null, 2);
      item.appendChild(pre);
    }
  }
  transcript.appendChild(item);
  transcript.scrollTop = transcript.scrollHeight;
  return item;
}

export function buildEditorGmRequest({
  toolId,
  draftId = null,
  validationProfile = 'editing',
  scope = null,
  intent,
  dryRun = false,
}) {
  const toolContext = {
    tool_id: toolId,
    validation_profile: validationProfile,
  };
  if (draftId) toolContext.draft_id = draftId;
  if (scope) toolContext.scope = scope;
  return {
    schema_version: 'editor-gm-request-v1',
    tool_context: toolContext,
    intent,
    options: { dry_run: !!dryRun },
  };
}

export function renderEditorGmProposal(transcript, result, options) {
  const proposal = result?.proposed_execution;
  if (!transcript || !proposal || result.intent !== 'proposed_execution') {
    return null;
  }
  const item = appendEditorGmMessage(
    transcript,
    'warning',
    `Proposed: ${proposal.tool_name} (${proposal.authority})`,
    proposal.arguments || {},
  );
  const actions = document.createElement('div');
  actions.className = options.actionsClass || 'room-editor__gm-plan-actions';
  const approve = document.createElement('button');
  approve.type = 'button';
  approve.className = options.approveClass || 'room-editor__button room-editor__button--primary';
  approve.textContent = options.approveLabel || 'Approve and run';
  approve.addEventListener('click', () => {
    approve.disabled = true;
    options.onApprove(proposal);
  });
  const reject = document.createElement('button');
  reject.type = 'button';
  reject.className = options.rejectClass || 'room-editor__button';
  reject.textContent = options.rejectLabel || 'Reject';
  reject.addEventListener('click', () => item.remove());
  actions.append(approve, reject);
  item.appendChild(actions);
  return item;
}
